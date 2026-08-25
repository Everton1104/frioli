<?php

namespace App\Console\Commands;

use App\Http\Controllers\WhatsappController;
use App\Models\AssinaturaMensal;
use App\Models\OrdemPagamento;
use App\Models\PlanoMensal;
use App\Services\PlanoMensalService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/*
 * Renovação automática de planos mensais recorrentes.
 *
 * Para cada plano recorrente ATIVO do mês corrente, nos últimos 10 dias do mês,
 * gera o plano do mês seguinte + uma ordem de pagamento (link avulso) e avisa o
 * cliente no WhatsApp. Ao pagar, o webhook da InfinitePay ativa o novo mês.
 *
 * Idempotente: se já existe um plano (mesmo cliente/serviço/barbeiro/dia/hora)
 * para o mês seguinte, não recriia. Não é "cartão salvo" — o cliente paga um
 * link por mês (a InfinitePay não tem webhook de assinatura).
 */
class RenovarPlanosMensais extends Command
{
    protected $signature = 'planos:renovar';
    protected $description = 'Gera o plano mensal do próximo mês (recorrentes ativos) + ordem de pagamento e avisa o cliente.';

    public function handle(): int
    {
        $hoje = now();

        // Assinaturas ativas com renovação automática (algum plano recorrente=1).
        $assinaturaIds = PlanoMensal::where('recorrente', 1)
            ->whereHas('assinatura', fn ($q) => $q->where('status', AssinaturaMensal::STATUS_ATIVO))
            ->pluck('assinatura_id')
            ->unique();

        $gerados = 0;
        foreach ($assinaturaIds as $aid) {
            $assinatura = AssinaturaMensal::with(['servicoBase', 'planos.itens.servico', 'user'])->find($aid);
            if (!$assinatura || !$assinatura->user) {
                continue;
            }

            $cicloAtual = $assinatura->cicloAtual(); // ciclo mais recente
            if (!$cicloAtual) {
                continue;
            }

            // Próximo início e janela de geração.
            $aPartirDe = $hoje->copy()->startOfDay();
            if ($cicloAtual->data_fim) {
                $fim = Carbon::parse($cicloAtual->data_fim);
                // Só gera se o ciclo atual estiver prestes a acabar (<=14 dias) ou já acabou.
                if ($hoje->floatDiffInDays($fim) > 14) {
                    continue;
                }
                $aPartirDe = $fim->copy()->addDay();
            } elseif ($cicloAtual->mes) {
                // Legado sem janela: renova nos últimos 10 dias do mês-calendário.
                $fimMes = Carbon::parse($cicloAtual->mes)->endOfMonth();
                if ($hoje->floatDiffInDays($fimMes) > 9) {
                    continue;
                }
                $aPartirDe = Carbon::parse($cicloAtual->mes)->startOfMonth()->addMonth();
            } else {
                continue;
            }

            $itens = $cicloAtual->itens->map(fn ($i) => ['servico_id' => $i->servico_id, 'quantidade' => (int) $i->quantidade]);
            if ($itens->isEmpty()) {
                continue;
            }

            $args = [
                'user_id'         => $assinatura->user_id,
                'funcionario_id'  => $assinatura->funcionario_id,
                'dia_semana'      => (int) $assinatura->dia_semana,
                'hora'            => substr((string) $assinatura->hora, 0, 5),
                'servico_base_id' => $assinatura->servico_base_id,
                'itens'           => $itens,
                'recorrente'      => true,
                'criado_por'      => $assinatura->user_id,
                'origem'          => 'renovacao',
            ];

            $combo = \App\Models\ServicosModel::find($assinatura->servico_base_id);

            if ($combo && $combo->temComposicao()) {
                // Fase 5: renovação LENDO o combo master. A 5ª visita (em mês de 5
                // semanas) é automática — repete a 1ª visita da distribuição.
                $args['calc'] = PlanoMensal::calcularProximoCicloDeCombo(
                    $combo, (int) $assinatura->dia_semana, $aPartirDe
                );
                if ($assinatura->dia_renovacao) {
                    $args['dia_renovacao'] = (int) $assinatura->dia_renovacao;
                }
            } else {
                $distribuicao = $cicloAtual->distribuicao ? collect($cicloAtual->distribuicao) : null;
                $valoresExtra = $cicloAtual->valores_extra ?: [];

                if ($distribuicao && $distribuicao->isNotEmpty()) {
                    // Legado: distribuição semanal definida por ciclo.
                    $args['distribuicao'] = $distribuicao->all();
                    if (!empty($valoresExtra)) {
                        $args['valores_extra'] = $valoresExtra;
                    }
                    $args['calc'] = PlanoMensal::calcularProximoCicloDistribuido(
                        (int) $assinatura->dia_semana, $distribuicao, $aPartirDe, $valoresExtra
                    );
                    if ($assinatura->dia_renovacao) {
                        $args['dia_renovacao'] = (int) $assinatura->dia_renovacao;
                    }
                } elseif ($assinatura->dia_renovacao) {
                    // Legado: ciclo ancorado no dia preferido do cliente.
                    $args['calc']          = PlanoMensal::calcularProximoCiclo((int) $assinatura->dia_semana, $itens, $aPartirDe, (int) $assinatura->dia_renovacao);
                    $args['dia_renovacao'] = (int) $assinatura->dia_renovacao;
                } else {
                    $args['mes'] = $aPartirDe->copy()->startOfMonth();
                }
            }

            try {
                $ordem = PlanoMensalService::criarCiclo($args);
            } catch (ValidationException $e) {
                continue; // já existe ciclo sobreposto (idempotente)
            }

            $this->avisar($assinatura->user, $ordem);
            $gerados++;
        }

        $this->info("Planos renovados (renovação automática): {$gerados}");
        return self::SUCCESS;
    }

    private function avisar($cliente, OrdemPagamento $ordem): void
    {
        if (!$cliente || !$cliente->whatsapp) {
            return;
        }
        $valor    = 'R$ ' . number_format((float) $ordem->valor, 2, ',', '.');
        $parcelas = (string) min((int) $ordem->max_parcelas, OrdemPagamento::MAX_SEM_JUROS);
        $link     = rtrim((string) env('APP_URL', config('app.url')), '/') . '/pagamentos/' . $ordem->id . '/pagar';
        $nome     = ucfirst($cliente->name ?? 'você');

        try {
            WhatsappController::enviarModelo(
                env('PHONE_NUMBER_ID'),
                $cliente->whatsapp,
                env('WHATSAPP_TEMPLATE_ORDEM_PAGAMENTO', 'ordem_pagamento_disponivel_fr'),
                [
                    ['type' => 'text', 'text' => $nome],
                    ['type' => 'text', 'text' => $ordem->descricao],
                    ['type' => 'text', 'text' => $valor],
                    ['type' => 'text', 'text' => $parcelas],
                    ['type' => 'text', 'text' => $link],
                ]
            );
        } catch (\Throwable $e) {
            Log::channel('single')->warning('[RenovarPlanosMensais] falha ao avisar cliente', [
                'ordem' => $ordem->id, 'msg' => $e->getMessage(),
            ]);
        }
    }
}
