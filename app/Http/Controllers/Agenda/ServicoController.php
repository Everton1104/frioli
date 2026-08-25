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
        $totalMinutos    = ($request->duracao_h * 60) + $request->duracao_m;

        if ($totalMinutos < 15) {
            return redirect()->back()->withErrors([
                'duracao_m' => 'O tempo mínimo é de 15 minutos.',
            ])->withInput($request->all());
        }

        ServicosModel::create([
            'descricao'       => $request->descricao,
            'duracao'         => $request->duracao_h . ':' . $request->duracao_m . ':00',
            'visivel_cliente' => $visivelCliente,
            'recorrente'      => $recorrente,
            // Combo mensal: o valor é a SOMA da composição (preços com desconto das
            // visitas). Serviço comum: usa o campo "valor" digitado.
            'valor'           => $recorrente ? $this->somaComposicao($composicao, $distribuicao) : ($request->filled('valor') ? $request->valor : null),
            'repasse_percent' => $request->filled('repasse_percent') ? $request->repasse_percent : null,
            'composicao'      => $composicao,
            'distribuicao'    => $distribuicao,
        ]);

        return redirect()->back()->with('msg', 'Serviço criado com sucesso!');
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

        $servico->update([
            'descricao'       => $request['descricao_edt_servico'],
            'duracao'         => $request['duracao_h_edt_servico'] . ':' . $request->duracao_m_edt_servico . ':00',
            'status'          => $request['status_servico'],
            'visivel_cliente' => $visivelCliente,
            'recorrente'      => $recorrente,
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
