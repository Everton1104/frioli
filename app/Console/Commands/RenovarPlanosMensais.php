<?php

namespace App\Console\Commands;

use App\Http\Controllers\WhatsappController;
use App\Models\OrdemPagamento;
use App\Models\PlanoMensal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
        $hoje      = now();
        $dia       = (int) $hoje->format('j');
        $diasNoMes = (int) $hoje->daysInMonth;

        // Janela: últimos 10 dias do mês (dá tempo do cliente pagar antes de virar).
        if ($dia < $diasNoMes - 9) {
            $this->info('Fora da janela de renovação (últimos 10 dias do mês). Nada a fazer hoje.');
            return self::SUCCESS;
        }

        $mesAtual = $hoje->copy()->startOfMonth();
        $proximo  = $hoje->copy()->startOfMonth()->addMonth();

        $planos = PlanoMensal::with(['servico', 'user'])
            ->where('recorrente', 1)
            ->where('status', PlanoMensal::STATUS_ATIVO)
            ->where('mes', $mesAtual->toDateString())
            ->get();

        $gerados = 0;
        foreach ($planos as $plano) {
            // Idempotente: já existe plano do próximo mês para este slot?
            $ja = PlanoMensal::where('user_id', $plano->user_id)
                ->where('servico_id', $plano->servico_id)
                ->where('funcionario_id', $plano->funcionario_id)
                ->where('dia_semana', $plano->dia_semana)
                ->where('hora', $plano->hora)
                ->where('mes', $proximo->toDateString())
                ->exists();
            if ($ja) {
                continue;
            }

            $servico = $plano->servico;
            if (!$servico) {
                continue;
            }
            $calc = PlanoMensal::calcular($servico, $proximo, (int) $plano->dia_semana);

            $ordem = DB::transaction(function () use ($plano, $servico, $proximo, $calc) {
                $novo = PlanoMensal::create([
                    'user_id'         => $plano->user_id,
                    'servico_id'      => $plano->servico_id,
                    'funcionario_id'  => $plano->funcionario_id,
                    'dia_semana'      => $plano->dia_semana,
                    'hora'            => $plano->hora,
                    'mes'             => $proximo,
                    'unidades_total'  => $calc['unidades'],
                    'unidades_usadas' => 0,
                    'valor_total'     => $calc['valor_total'],
                    'status'          => PlanoMensal::STATUS_AGUARDANDO_PAGAMENTO,
                    'recorrente'      => true,
                ]);

                $o = OrdemPagamento::create([
                    'user_id'            => $plano->user_id,
                    'criado_por'         => $plano->user_id,
                    'plano_mensal_id'    => $novo->id,
                    'valor'              => $calc['valor_total'],
                    'descricao'          => $servico->descricao . ' (mensal ' . $calc['unidades'] . 'x)',
                    'max_parcelas'       => OrdemPagamento::MAX_PARCELAS,
                    'status'             => 'aberta',
                    'external_reference' => (string) Str::uuid(),
                ]);
                $o->eventos()->create(['status' => 'aberta', 'origem' => 'renovacao']);

                $novo->ordem_pagamento_id = $o->id;
                $novo->save();

                return $o;
            });

            $this->avisar($plano->user, $ordem);
            $gerados++;
        }

        $this->info("Planos renovados para {$proximo->locale('pt_BR')->translatedFormat('F Y')}: {$gerados}");
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
                env('WHATSAPP_TEMPLATE_ORDEM_PAGAMENTO', 'ordem_pagamento_disponivel'),
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
