<?php

namespace App\Services;

use App\Models\AgendamentoModel;
use App\Models\AssinaturaMensal;
use App\Models\OrdemPagamento;
use App\Models\PlanoMensal;
use App\Models\PlanoMensalServico;
use App\Models\ServicosModel;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/*
 * Criação/renovação de um CICLO de plano mensal multi-serviço.
 *
 * Centraliza a lógica comum ao staff (OrdemPagamentoController::mensalStore) e ao
 * cliente indicado (AgendaPublicaController::mensalComprar): find-or-create da
 * assinatura do slot fixo + ciclo com itens + ordem de pagamento. Lança
 * ValidationException em conflito de slot ou itens inválidos (form POST → redirect
 * com erros/input, igual ao fluxo manual anterior).
 */
class PlanoMensalService
{
    /**
     * @param array $args
     *   - user_id: int
     *   - funcionario_id: int
     *   - dia_semana: int (0-6)
     *   - hora: string "HH:MM"
     *   - mes: Carbon (1º do mês-alvo)
     *   - servico_base_id: int (combo master, recorrente=1 — nome/duração do slot)
     *   - itens: Collection de [servico_id, quantidade] (>0)
     *   - cap: int|null (limite de visitas "4 em mês de 5")
     *   - recorrente: bool
     *   - criado_por: int
     *   - origem: string (evento da ordem)
     * @return OrdemPagamento
     */
    public static function criarCiclo(array $args): OrdemPagamento
    {
        $user = User::whereKey($args['user_id'])->where('excluido', 0)->first();
        if (!$user) {
            throw ValidationException::withMessages(['user_id' => 'Cliente inválido.']);
        }

        $funcionario = User::barbeiros()->find($args['funcionario_id']);
        if (!$funcionario) {
            throw ValidationException::withMessages(['funcionario_id' => 'Barbeiro inválido.']);
        }

        $dia  = (int) $args['dia_semana'];
        $hora = $args['hora'] . ':00';

        // Combo master (serviço mensal) — dá nome e duração ao slot reservado.
        $base = ServicosModel::where('excluido', 0)
            ->where('status', 1)
            ->where('recorrente', 1)
            ->where('valor', '>', 0)
            ->find($args['servico_base_id']);
        if (!$base) {
            throw ValidationException::withMessages(['servico_base_id' => 'Combo (serviço mensal) inválido.']);
        }

        // Fase 5: combo master com composição definida no cadastro — deriva tudo dele
        // (itens, preços com desconto, distribuição e a 5ª visita, que repete a 1ª).
        $comboComposicao = $base->temComposicao();

        // Assinatura JÁ existente neste slot (renovação): define a FASE quinzenal do
        // cliente — as semanas dele continuam alternadas na mesma paridade.
        $assinaturaExistente = AssinaturaMensal::where('user_id', $user->id)
            ->where('funcionario_id', $funcionario->id)
            ->where('dia_semana', $dia)
            ->where('hora', $hora)
            ->where('status', AssinaturaMensal::STATUS_ATIVO)
            ->orderByDesc('id')
            ->first();
        $faseAtual = ($base->quinzenal && $assinaturaExistente) ? $assinaturaExistente->quinzenal_fase : null;

        // Quinzenal SEM fase definida (1º ciclo do cliente no slot): as semanas do
        // cliente seriam as da próxima ocorrência do dia. Se o slot já tem um
        // quinzenal nessas semanas, este cliente vira o "complementar" — adota a
        // fase OPOSTA (começa na semana seguinte), e os dois dividem o horário.
        if ($base->quinzenal && $faseAtual === null) {
            $faseAtual = self::faseDisponivelNoSlot($funcionario->id, $dia, $hora, $user->id);
        }

        // Itens inclusos (path legado — combo sem composição definida no cadastro).
        $itens = collect($args['itens'] ?? [])->filter(function ($i) {
            $qtd = is_array($i) ? ($i['quantidade'] ?? $i[1] ?? 0) : ($i->quantidade ?? 0);
            return (int) $qtd > 0;
        })->values();
        if (!$comboComposicao && $itens->isEmpty() && empty($args['distribuicao'])) {
            throw ValidationException::withMessages(['itens' => 'Informe ao menos 1 serviço incluso (ou monte a distribuição).']);
        }

        // Cálculo do ciclo: se veio um calc pré-computado (janela ancorada no
        // dia_renovacao, de PlanoMensal::calcularProximoCiclo), usa-o; senão, mês-calendário.
        // Com distribuição semanal (Fase 3), o nº de visitas e o valor vêm da agenda
        // definida pelo staff — substitui o "cap" e o abate uniforme.
        $calc = $args['calc'] ?? null;
        $distribuicao = !empty($args['distribuicao']) ? collect($args['distribuicao']) : null;
        if ($comboComposicao && !$calc) {
            // Vinculação LENDO o combo: a partir de HOJE, no mês atual — só as semanas
            // restantes (início no meio do mês = ciclo parcial, valor proporcional).
            $hoje = Carbon::now()->startOfDay();
            $calc = PlanoMensal::calcularComboDeComboAPartirDe($base, $dia, $hoje, $faseAtual);
            $mes  = $calc['data_inicio'] ? Carbon::parse($calc['data_inicio'])->startOfMonth() : $hoje->copy()->startOfMonth();
            if ($calc['unidades'] < 1 || empty($calc['data_inicio'])) {
                throw ValidationException::withMessages(['dia_semana' => 'Não há ocorrências suficientes neste mês para o combo.']);
            }
        } elseif ($calc) {
            $mes = $calc['data_inicio']->copy()->startOfMonth();
        } elseif ($distribuicao) {
            $mes  = $args['mes']; // Carbon startOfMonth (criação staff)
            $calc = PlanoMensal::calcularComboDistribuido($distribuicao, $mes, $dia, $args['valores_extra'] ?? []);
            if ($calc['unidades'] < 1 || empty($calc['data_inicio'])) {
                throw ValidationException::withMessages(['dia_semana' => 'Não há ocorrências suficientes neste mês para a distribuição.']);
            }
        } else {
            $mes  = $args['mes']; // Carbon startOfMonth
            $calc = PlanoMensal::calcularCombo($itens, $mes, $dia, $args['cap'] ?? null);
            if ($calc['unidades'] < 1) {
                throw ValidationException::withMessages(['dia_semana' => 'Não há ocorrências suficientes neste mês.']);
            }
            foreach ($calc['itens'] as $it) {
                if ($it['quantidade'] > $calc['unidades']) {
                    throw ValidationException::withMessages(['itens' => 'A quantidade de um serviço não pode exceder o nº de visitas do ciclo.']);
                }
            }
        }

        // 1 cliente por slot: sobreposição de janela (ou mês, p/ legados sem janela).
        // QUINZENAL: um ciclo de fase OPOSTA no mesmo slot NÃO conflita — os dois
        // dividem o horário (cada um nas suas semanas).
        $dI = $calc['data_inicio'];
        $dF = $calc['data_fim'];
        $faseCandidata = $base->quinzenal
            ? ($faseAtual ?? ($calc['data_inicio'] ? PlanoMensal::faseSemana(Carbon::parse($calc['data_inicio'])) : null))
            : null;

        $conflitos = PlanoMensal::whereIn('status', [PlanoMensal::STATUS_ATIVO, PlanoMensal::STATUS_AGUARDANDO_PAGAMENTO])
            ->where('funcionario_id', $funcionario->id)
            ->where('dia_semana', $dia)
            ->where('hora', $hora)
            ->where(function ($q) use ($dI, $dF, $mes) {
                $q->where(function ($qq) use ($dI, $dF) {
                    $qq->whereNotNull('data_inicio')->where('data_inicio', '<=', $dF)->where('data_fim', '>=', $dI);
                })->orWhere(function ($qq) use ($mes) {
                    $qq->whereNull('data_inicio')->where('mes', $mes->toDateString());
                });
            })
            ->with(['servico:id,quinzenal', 'assinatura:id,quinzenal_fase'])
            ->get()
            ->filter(function ($c) use ($faseCandidata) {
                $faseOcupante = $c->faseAssinatura();
                return $faseOcupante === null || $faseCandidata === null || $faseOcupante === $faseCandidata;
            });
        if ($conflitos->isNotEmpty()) {
            throw ValidationException::withMessages(['funcionario_id' => 'Esse horário fixo já tem um ciclo ativo neste período. Escolha outro.']);
        }

        // Slot fixo de OUTRO mensalista: a assinatura ativa dele reserva o dia/hora
        // SEMANALMENTE — mesmo que uma semana específica pareça livre (remarcação
        // pontual do dono), outro plano fixo não pode assumir o slot; só avulsos e
        // remarcações pontuais podem usar a semana vaga. Vale também para ciclo em
        // vigor de assinatura já cancelada (segue valendo até o fim).
        // (Quinzenais de fase oposta podem DIVIDIR o slot — regra acima.)
        $donoSlot = AssinaturaMensal::slotFixoOcupado($funcionario->id, $dia, $hora, $user->id, $faseCandidata)
            ?? PlanoMensal::slotOcupadoPorCiclo($funcionario->id, $dia, $hora, $user->id, $faseCandidata);
        if ($donoSlot) {
            throw ValidationException::withMessages([
                'funcionario_id' => 'Este dia/horário é o slot fixo de ' . ($donoSlot->user->name ?? 'outro cliente') . '. Escolha outro horário.',
            ]);
        }

        return DB::transaction(function () use ($user, $funcionario, $dia, $hora, $mes, $base, $calc, $args, $faseCandidata) {
            // Find-or-create da assinatura ativa do slot fixo.
            $assinatura = AssinaturaMensal::where('user_id', $user->id)
                ->where('funcionario_id', $funcionario->id)
                ->where('dia_semana', $dia)
                ->where('hora', $hora)
                ->where('status', AssinaturaMensal::STATUS_ATIVO)
                ->orderByDesc('id')
                ->first();
            if (!$assinatura) {
                $assinatura = AssinaturaMensal::create([
                    'user_id'         => $user->id,
                    'funcionario_id'  => $funcionario->id,
                    'dia_semana'       => $dia,
                    'hora'            => $hora,
                    'servico_base_id' => $base->id,
                    'dia_renovacao'   => $args['dia_renovacao'] ?? null,
                    'status'          => AssinaturaMensal::STATUS_ATIVO,
                    'quinzenal_fase'  => $faseCandidata,
                ]);
            } else {
                // Redefine o dia de pagamento (Fase 5) e a fase quinzenal (migração
                // de um combo semanal para quinzenal no mesmo slot).
                $dirty = false;
                if (!empty($args['dia_renovacao']) && (int) $assinatura->dia_renovacao !== (int) $args['dia_renovacao']) {
                    $assinatura->dia_renovacao = $args['dia_renovacao'];
                    $dirty = true;
                }
                if ($assinatura->quinzenal_fase !== $faseCandidata) {
                    $assinatura->quinzenal_fase = $faseCandidata;
                    $dirty = true;
                }
                if ($dirty) {
                    $assinatura->save();
                }
            }

            $plano = PlanoMensal::create([
                'user_id'         => $user->id,
                'servico_id'      => $base->id,
                'funcionario_id'  => $funcionario->id,
                'dia_semana'       => $dia,
                'hora'            => $hora,
                'mes'             => $mes,
                'data_inicio'     => $calc['data_inicio'],
                'data_fim'        => $calc['data_fim'],
                'unidades_total'  => $calc['unidades'],
                'unidades_usadas' => 0,
                'valor_total'     => $calc['valor_total'],
                'status'          => PlanoMensal::STATUS_AGUARDANDO_PAGAMENTO,
                'recorrente'      => (bool) ($args['recorrente'] ?? false),
                'assinatura_id'   => $assinatura->id,
                'distribuicao'    => $base->temComposicao()
                    ? ($calc['distribuicao'] ?? null)
                    : (!empty($args['distribuicao']) ? $args['distribuicao'] : null),
                'valores_extra'   => !empty($args['valores_extra']) ? $args['valores_extra'] : null,
            ]);

            foreach ($calc['itens'] as $it) {
                PlanoMensalServico::create([
                    'plano_mensal_id' => $plano->id,
                    'servico_id'      => $it['servico_id'],
                    'quantidade'      => $it['quantidade'],
                    'usados'          => 0,
                    'valor_unitario'  => $it['valor_unitario'],
                ]);
            }

            // "Consumir 1 unidade no ato": o cliente já usou um serviço presencialmente
            // ao criar o pacote. Abate a 1ª visita (posição 0 da distribuição, ou 1 de
            // cada item no legado) — independente do status (ainda aguardando pagamento).
            if (!empty($args['consumir_no_ato'])) {
                $plano->unidades_usadas = 1;
                $pos0 = $plano->temDistribuicao()
                    ? array_map('intval', $plano->distribuicao[0] ?? [])
                    : null;
                foreach ($plano->itens as $item) {
                    if ((int) $item->usados < (int) $item->quantidade
                        && ($pos0 === null || in_array((int) $item->servico_id, $pos0, true))) {
                        $item->usados = (int) $item->usados + 1;
                        $item->save();
                    }
                }
                $plano->save();
            }

            $o = OrdemPagamento::create([
                'user_id'            => $user->id,
                'criado_por'         => $args['criado_por'] ?? $user->id,
                'plano_mensal_id'    => $plano->id,
                'valor'              => $calc['valor_total'],
                'descricao'          => $plano->descricaoItens() . ' (mensal ' . $calc['unidades'] . 'x)',
                'max_parcelas'       => OrdemPagamento::MAX_PARCELAS,
                'status'             => 'aberta',
                'external_reference' => (string) Str::uuid(),
            ]);
            $o->eventos()->create(['status' => 'aberta', 'origem' => $args['origem'] ?? 'manual']);

            $plano->ordem_pagamento_id = $o->id;
            $plano->save();

            return $o;
        });
    }

    /**
     * Fase quinzenal (0/1) a adotar por um NOVO cliente num slot: a das semanas da
     * próxima ocorrência do dia — a menos que outro quinzenal já as ocupe, caso em
     * que retorna a fase OPOSTA (clientes "complementares" dividem o horário).
     * Retorna null se as DUAS fases já estiverem tomadas (a validação de slot
     * bloqueia a criação em seguida).
     */
    private static function faseDisponivelNoSlot(int $funcionarioId, int $dia, string $hora, int $userId): ?int
    {
        // Próxima ocorrência do dia a partir de hoje.
        $prox = now()->startOfDay();
        while ((int) $prox->format('w') !== $dia) {
            $prox->addDay();
        }
        $fase = PlanoMensal::faseSemana($prox);

        $ocupadas = collect(); // fases dos OUTROS quinzenais no slot

        // Quinzenais com assinatura ativa no slot (outros clientes).
        AssinaturaMensal::query()
            ->where('status', AssinaturaMensal::STATUS_ATIVO)
            ->where('funcionario_id', $funcionarioId)
            ->where('dia_semana', $dia)
            ->where('hora', 'like', substr($hora, 0, 5) . '%')
            ->where('user_id', '!=', $userId)
            ->with('servicoBase:id,quinzenal')
            ->get()
            ->filter(fn ($a) => $a->servicoBase?->quinzenal && $a->quinzenal_fase !== null)
            ->each(fn ($a) => $ocupadas[] = (int) $a->quinzenal_fase);

        // Ciclos quinzenais em vigor sem assinatura ativa (assinatura cancelada,
        // ciclo pago segue valendo até o fim).
        PlanoMensal::whereIn('status', [PlanoMensal::STATUS_ATIVO, PlanoMensal::STATUS_AGUARDANDO_PAGAMENTO])
            ->where('funcionario_id', $funcionarioId)
            ->where('dia_semana', $dia)
            ->where('hora', 'like', substr($hora, 0, 5) . '%')
            ->where('user_id', '!=', $userId)
            ->where(function ($q) {
                $q->whereNull('data_fim')->orWhereDate('data_fim', '>=', today());
            })
            ->with(['servico:id,quinzenal', 'assinatura:id,quinzenal_fase'])
            ->get()
            ->filter(fn ($c) => $c->ehQuinzenal())
            ->each(fn ($c) => $ocupadas[] = (int) $c->faseAssinatura());

        if (!$ocupadas->unique()->contains($fase)) {
            return $fase; // ninguém nas semanas da próxima ocorrência
        }
        $oposta = 1 - $fase;
        return $ocupadas->unique()->contains($oposta) ? null : $oposta;
    }

    /**
     * Recalcula os ciclos existentes (ativos/aguardando) de um combo a partir do combo
     * atual — "remove snapshots" (ambiente de testes). Atualiza composição (itens +
     * valor_unitario), distribuição, valor_total e a ordem aberta vinculada. Preserva o
     * consumo já registrado (unidades_usadas e usados por serviço, clampados ao novo
     * total). Retorna o nº de ciclos atualizados.
     */
    public static function recalcularCiclosDoCombo(ServicosModel $combo): int
    {
        if (!$combo->temComposicao()) {
            return 0;
        }

        $ciclos = PlanoMensal::whereIn('status', [PlanoMensal::STATUS_ATIVO, PlanoMensal::STATUS_AGUARDANDO_PAGAMENTO])
            ->where('servico_id', $combo->id)
            ->with(['itens', 'assinatura', 'ordemPagamento'])
            ->get();

        $n = 0;
        foreach ($ciclos as $plano) {
            $mes   = $plano->data_inicio ? Carbon::parse($plano->data_inicio)->startOfMonth() : Carbon::parse($plano->mes)->startOfMonth();
            // Quinzenal: preserva a FASE do cliente (as semanas dele no ciclo).
            $fase  = $combo->quinzenal ? $plano->faseAssinatura() : null;
            $calc  = PlanoMensal::calcularComboDeCombo($combo, $mes, (int) $plano->dia_semana, $fase);
            if (empty($calc['data_inicio'])) {
                continue;
            }

            DB::transaction(function () use ($plano, $calc) {
                $usadosAntigos = $plano->itens->keyBy('servico_id')->map(fn ($i) => (int) $i->usados);

                $plano->distribuicao   = $calc['distribuicao'];
                $plano->valor_total    = $calc['valor_total'];
                $plano->unidades_total = $calc['unidades'];
                $plano->unidades_usadas = min((int) $plano->unidades_usadas, $calc['unidades']);
                $plano->data_inicio    = $calc['data_inicio'];
                $plano->data_fim       = $calc['data_fim'];
                $plano->save();

                $plano->itens()->delete();
                foreach ($calc['itens'] as $it) {
                    PlanoMensalServico::create([
                        'plano_mensal_id' => $plano->id,
                        'servico_id'      => $it['servico_id'],
                        'quantidade'      => $it['quantidade'],
                        'usados'          => min($usadosAntigos[$it['servico_id']] ?? 0, $it['quantidade']),
                        'valor_unitario'  => $it['valor_unitario'],
                    ]);
                }

                if ($plano->ordemPagamento && $plano->ordemPagamento->status === 'aberta') {
                    $plano->ordemPagamento->update([
                        'valor'     => $calc['valor_total'],
                        'descricao' => $plano->descricaoItens() . ' (mensal ' . $calc['unidades'] . 'x)',
                    ]);
                }
            });
            $n++;
        }

        return $n;
    }

    /**
     * Desconta as visitas de plano mensal cuja data já passou (sem confirmação).
     * Chamado ao abrir a semana (salvarSemana) — substitui o antigo command diário
     * às 23:30. Idempotente: só processa consumo_plano NULL. O staff vê o saldo
     * já atualizado quando abre a agenda, e o aviso "sem saldo" dispara no momento.
     */
    public static function descontarVisitasVencidas(): int
    {
        $agora = now();

        $ags = AgendamentoModel::whereNotNull('plano_mensal_id')
            ->whereNull('consumo_plano')
            ->whereIn('status', [
                AgendamentoModel::STATUS_PAGO_AGUARDANDO,
                AgendamentoModel::STATUS_CONFIRMADO,
            ])
            ->where('data_inicio', '<', $agora)
            ->orderBy('data_inicio')
            ->get();

        $n = 0;
        foreach ($ags as $ag) {
            $plano = $ag->planoMensal;
            if (!$plano) {
                $ag->consumo_plano = [];
                $ag->save();
                continue;
            }
            $consumidos = $plano->descontarVisita($ag->plano_ordem);
            $ag->consumo_plano = $consumidos;
            $ag->save();
            $n++;
        }

        return $n;
    }
}
