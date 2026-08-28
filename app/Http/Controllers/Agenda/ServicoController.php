<?php

namespace App\Http\Controllers\Agenda;

use App\Http\Controllers\Controller;
use App\Models\AgendamentoModel;
use App\Models\ServicosModel;
use Illuminate\Http\Request;

class ServicoController extends Controller
{
    public function store(Request $request)
    {
        abort_unless(auth()->user()->adm || auth()->user()->func, 403);

        $request->validate([
            'descricao'       => ['required', 'string', 'max:255', 'min:5'],
            'duracao_h'       => ['required', 'numeric'],
            'duracao_m'       => ['required', 'numeric'],
            'repasse_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'valor'           => ['nullable', 'numeric', 'min:0'],
            // Fase 5: composição (serviços inclusos + preço c/ desconto) e distribuição
            // (visitas por semana) do combo mensal — JSON enviado pelo editor do modal.
            'composicao'      => ['nullable', 'string'],
            'distribuicao'    => ['nullable', 'string'],
        ]);

        // "Serviço staff" foi descontinuado: o encaixe agora é a flag `especial` do
        // agendamento. Avulso é sempre visível ao cliente; MENSAL só aparece no
        // painel dele (valores + itens) se o adm marcar "mostrar aos clientes"
        // (checkbox do editor do combo — default desmarcado/oculto).
        $recorrente      = $request->boolean('recorrente');
        $composicao      = $recorrente ? $this->decodeJson($request->composicao) : null;
        $distribuicao    = $recorrente ? $this->decodeJson($request->distribuicao) : null;
        $visivelCliente  = $recorrente ? $request->boolean('mostrar_clientes') : true;

        // Quinzenal: idas iguais → distribuição gerada do zero (o editor não a
        // exibe). "Idas diferentes" → usa as 2 idas montadas no editor.
        if ($recorrente && $request->boolean('quinzenal')) {
            if ($request->boolean('idas_diferentes')) {
                $distribuicao = $this->decodeJson($request->distribuicao);
                $erroDist = $this->validarDistribuicao($distribuicao);
                if (!$erroDist && (!$distribuicao || count($distribuicao) !== 2)) {
                    $erroDist = 'O pacote quinzenal tem 2 idas por mês — monte a 1ª e a 2ª.';
                }
                if ($erroDist) {
                    return redirect()->back()->withErrors(['distribuicao' => $erroDist])->withInput($request->all());
                }
            } else {
                $distribuicao = $this->distribuicaoQuinzenal($composicao);
                if (!$distribuicao) {
                    return redirect()->back()->withErrors([
                        'composicao' => 'Defina o preço de ao menos 1 serviço na composição — é o que o cliente faz a cada ida (de 15 em 15 dias).',
                    ])->withInput($request->all());
                }
            }
        }
        $totalMinutos    = ($request->duracao_h * 60) + $request->duracao_m;

        if ($totalMinutos < 15) {
            return redirect()->back()->withErrors([
                'duracao_m' => 'O tempo mínimo é de 15 minutos.',
            ])->withInput($request->all());
        }

        // Toda visita do pacote precisa ter ao menos 1 serviço — distribuição com
        // posição vazia criaria um horário marcado SEM serviço. (Cliente que vem
        // a cada 15 dias é o tipo "Quinzenal", não semanas vazias na distribuição.)
        $erroDist = $this->validarDistribuicao($distribuicao);
        if ($erroDist) {
            return redirect()->back()->withErrors(['distribuicao' => $erroDist])->withInput($request->all());
        }

        ServicosModel::create([
            'descricao'       => $request->descricao,
            'duracao'         => $request->duracao_h . ':' . $request->duracao_m . ':00',
            'visivel_cliente' => $visivelCliente,
            'recorrente'      => $recorrente,
            // Quinzenal: cliente vem a cada 15 dias (semana sim, semana não) — as
            // semanas vazias do slot ficam livres, e outro quinzenal de fase oposta
            // pode dividir o mesmo horário.
            'quinzenal'       => $recorrente && $request->boolean('quinzenal'),
            // Combo mensal: o valor é a SOMA da composição (preços com desconto das
            // visitas). Serviço comum: usa o campo "valor" digitado.
            'valor'           => $recorrente ? $this->somaComposicao($composicao, $distribuicao) : ($request->filled('valor') ? $request->valor : null),
            'repasse_percent' => $request->filled('repasse_percent') ? $request->repasse_percent : null,
            'composicao'      => $composicao,
            'distribuicao'    => $distribuicao,
        ]);

        return redirect()->back()->with('msg', 'Serviço criado com sucesso!');
    }

    /**
     * Distribuição do QUINZENAL: o seletor de visitas é oculto no editor — cada
     * ida do cliente inclui TODOS os serviços com preço definido na composição,
     * e o ciclo fecha com 2 idas/mês (+1 extra em meses de 5 semanas, mesmo
     * mecanismo dos pacotes mensais: a extra repete a 1ª ida).
     */
    private function distribuicaoQuinzenal(?array $composicao): ?array
    {
        $keys = array_keys(array_filter($composicao ?: [], fn ($v) => (float) $v > 0));
        return $keys ? [$keys, $keys] : null;
    }

    /**
     * Distribuição válida = toda visita tem ao menos 1 serviço. Retorna a
     * mensagem de erro (com o nº da visita) ou null se estiver ok. Visita vazia
     * = horário agendado sem serviço — para "semana sim, semana não" existe o
     * tipo Quinzenal (checkbox do combo), não a distribuição com buracos.
     */
    private function validarDistribuicao(?array $distribuicao): ?string
    {
        if (!$distribuicao) {
            return null;
        }
        foreach (array_values($distribuicao) as $i => $visita) {
            if (empty($visita) || !array_filter((array) $visita)) {
                $n = $i + 1;
                return "A visita {$n} da distribuição está sem nenhum serviço — toda visita do pacote precisa ter ao menos 1 serviço. "
                    . 'Se a ideia é o cliente vir a cada 15 dias, marque o pacote como "Quinzenal" (não deixe visitas vazias).';
            }
        }
        return null;
    }

    /** Decodifica um JSON de composição/distribuição vindos do form (ou null). */
    private function decodeJson($value): ?array
    {
        if (empty($value)) {
            return null;
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : null;
    }

    /** Soma os preços (com desconto) das visitas da distribuição — o valor do combo. */
    private function somaComposicao(?array $composicao, ?array $distribuicao): ?float
    {
        if (!$composicao || !$distribuicao) {
            return null;
        }
        $total = 0.0;
        foreach ($distribuicao as $visita) {
            foreach ((array) $visita as $sid) {
                $total += (float) ($composicao[$sid] ?? 0);
            }
        }
        return round($total, 2);
    }

    /** Preview do combo master (composição + distribuição + total) p/ o form de criar plano. */
    public function composicao(ServicosModel $servico)
    {
        abort_unless(auth()->user()->adm || auth()->user()->func, 403);

        return response()->json([
            'id'             => $servico->id,
            'descricao'      => $servico->descricao,
            'tem_composicao' => $servico->temComposicao(),
            'quinzenal'      => (bool) $servico->quinzenal,
            'composicao'     => $servico->composicao ?: (object) [],
            'distribuicao'   => $servico->distribuicao ?: [],
            'total_base'     => $servico->precoTotalBase(),
            'servicos'       => ServicosModel::where('excluido', 0)
                ->where('recorrente', 0)->where('visivel_cliente', 1)
                ->orderBy('descricao')->get(['id', 'descricao']),
        ]);
    }

    public function delete(Request $request)
    {
        abort_unless(auth()->user()->adm || auth()->user()->func, 403);

        $request->validate([
            'excluir-servico-id' => 'required|integer',
        ]);

        $consultas = AgendamentoModel::with(['user', 'servico'])
            ->where('servico_id', '=', $request['excluir-servico-id'])
            ->get();

        if ($consultas->count() > 0) {
            return redirect()->back()->with('msgErro', 'Ainda existem clientes registrados com esse serviço!');
        }

        $servico = ServicosModel::find($request['excluir-servico-id']);
        if (!$servico) {
            return redirect()->back()->with('msgErro', 'Serviço não encontrado!');
        }

        try {
            $servico->update(['excluido' => 1]);
            return redirect()->back()->with('msg', 'Serviço excluído com sucesso!');
        } catch (\Throwable $th) {
            return redirect()->back()->with('msgErro', 'Erro ao excluir serviço!');
        }
    }

    public function editar(Request $request)
    {
        abort_unless(auth()->user()->adm || auth()->user()->func, 403);

        $request->validate([
            'id_edt_servico'          => 'required|numeric',
            'descricao_edt_servico'   => ['required', 'string', 'max:255', 'min:5'],
            'duracao_h_edt_servico'   => ['required', 'numeric'],
            'duracao_m_edt_servico'   => ['required', 'numeric'],
            'status_servico'          => ['required', 'numeric'],
            'repasse_percent_edt_servico' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'valor_edt_servico'       => ['nullable', 'numeric', 'min:0'],
            'composicao_edt_servico'  => ['nullable', 'string'],
            'distribuicao_edt_servico'=> ['nullable', 'string'],
        ]);

        // Serviço staff descontinuado (ver store): avulso sempre visível; mensal
        // só com o checkbox "mostrar aos clientes" marcado pelo adm (default oculto).
        $totalMinutos = ($request->duracao_h_edt_servico * 60) + $request->duracao_m_edt_servico;

        if ($totalMinutos < 15) {
            return redirect()->back()->withErrors([
                'duracao_m_edt_servico' => 'O tempo mínimo é de 15 minutos.',
            ])->withInput($request->all());
        }

        $servico = ServicosModel::find($request['id_edt_servico']);
        if (!$servico) {
            return redirect()->back()->with('msgErro', 'Serviço não encontrado!');
        }

        $recorrente      = $request->boolean('recorrente_edt_servico');
        $composicao      = $recorrente ? $this->decodeJson($request->composicao_edt_servico) : null;
        $distribuicao    = $recorrente ? $this->decodeJson($request->distribuicao_edt_servico) : null;
        $visivelCliente  = $recorrente ? $request->boolean('mostrar_clientes_edt_servico') : true;

        // Quinzenal: idas iguais → distribuição gerada; "idas diferentes" → as 2
        // idas montadas no editor.
        if ($recorrente && $request->boolean('quinzenal_edt_servico')) {
            if ($request->boolean('idas_diferentes_edt_servico')) {
                $distribuicao = $this->decodeJson($request->distribuicao_edt_servico);
                $erroDist = $this->validarDistribuicao($distribuicao);
                if (!$erroDist && (!$distribuicao || count($distribuicao) !== 2)) {
                    $erroDist = 'O pacote quinzenal tem 2 idas por mês — monte a 1ª e a 2ª.';
                }
                if ($erroDist) {
                    return redirect()->back()->withErrors(['distribuicao_edt_servico' => $erroDist])->withInput($request->all());
                }
            } else {
                $distribuicao = $this->distribuicaoQuinzenal($composicao);
                if (!$distribuicao) {
                    return redirect()->back()->withErrors([
                        'composicao_edt_servico' => 'Defina o preço de ao menos 1 serviço na composição — é o que o cliente faz a cada ida (de 15 em 15 dias).',
                    ])->withInput($request->all());
                }
            }
        }

        // Mesma regra do store: nenhuma visita vazia na distribuição.
        $erroDist = $this->validarDistribuicao($distribuicao);
        if ($erroDist) {
            return redirect()->back()->withErrors(['distribuicao_edt_servico' => $erroDist])->withInput($request->all());
        }

        $servico->update([
            'descricao'       => $request['descricao_edt_servico'],
            'duracao'         => $request['duracao_h_edt_servico'] . ':' . $request->duracao_m_edt_servico . ':00',
            'status'          => $request['status_servico'],
            'visivel_cliente' => $visivelCliente,
            'recorrente'      => $recorrente,
            'quinzenal'       => $recorrente && $request->boolean('quinzenal_edt_servico'),
            'valor'           => $recorrente ? $this->somaComposicao($composicao, $distribuicao) : ($request->filled('valor_edt_servico') ? $request->valor_edt_servico : null),
            'repasse_percent' => $request->filled('repasse_percent_edt_servico') ? $request->repasse_percent_edt_servico : null,
            'composicao'      => $composicao,
            'distribuicao'    => $distribuicao,
        ]);

        // Fase 5: ao editar o combo, propaga para os planos existentes (sem snapshot,
        // ambiente de testes) — recalcula composição/distribuição/valor dos ciclos ativos
        // e aguardando, preservando o consumo já registrado.
        if ($servico->fresh()->temComposicao()) {
            $recalculados = \App\Services\PlanoMensalService::recalcularCiclosDoCombo($servico->fresh());
            if ($recalculados > 0) {
                return redirect()->back()->with('msg', "Serviço atualizado. {$recalculados} plano(s) recálculado(s) a partir do combo.");
            }
        }

        return redirect()->back()->with('msg', 'Serviço atualizado com sucesso!');
    }
}
