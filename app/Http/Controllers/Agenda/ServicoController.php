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
            'valor'           => ['nullable', 'numeric', 'min:0'],
        ]);

        // "Serviço staff" foi descontinuado: o encaixe agora é a flag `especial` do
        // agendamento. Todo serviço é normal/visível. A coluna é mantida por histórico.
        $visivelCliente = true;
        $totalMinutos   = ($request->duracao_h * 60) + $request->duracao_m;

        if ($totalMinutos <= 0) {
            return redirect()->back()->withErrors([
                'duracao_m' => 'O tempo mínimo é de 15 minutos.',
            ])->withInput($request->all());
        }

        if ($visivelCliente && $totalMinutos < 30) {
            return redirect()->back()->withErrors([
                'duracao_m' => 'Serviços visíveis para clientes precisam ter no mínimo 30 minutos.',
            ])->withInput($request->all());
        }

        ServicosModel::create([
            'descricao'       => $request->descricao,
            'duracao'         => $request->duracao_h . ':' . $request->duracao_m . ':00',
            'visivel_cliente' => $visivelCliente,
            'retirada'        => $request->boolean('retirada'),
            'valor'           => $request->filled('valor') ? $request->valor : null,
        ]);

        return redirect()->back()->with('msg', 'Serviço criado com sucesso!');
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
            'valor_edt_servico'       => ['nullable', 'numeric', 'min:0'],
        ]);

        // Serviço staff descontinuado (ver store): todo serviço é normal/visível.
        $visivelCliente = true;
        $totalMinutos   = ($request->duracao_h_edt_servico * 60) + $request->duracao_m_edt_servico;

        if ($totalMinutos <= 0) {
            return redirect()->back()->withErrors([
                'duracao_m_edt_servico' => 'O tempo mínimo é de 15 minutos.',
            ])->withInput($request->all());
        }

        if ($visivelCliente && $totalMinutos < 30) {
            return redirect()->back()->withErrors([
                'duracao_m_edt_servico' => 'Serviços visíveis para clientes precisam ter no mínimo 30 minutos.',
            ])->withInput($request->all());
        }

        $servico = ServicosModel::find($request['id_edt_servico']);
        if (!$servico) {
            return redirect()->back()->with('msgErro', 'Serviço não encontrado!');
        }

        $servico->update([
            'descricao'       => $request['descricao_edt_servico'],
            'duracao'         => $request['duracao_h_edt_servico'] . ':' . $request['duracao_m_edt_servico'] . ':00',
            'status'          => $request['status_servico'],
            'visivel_cliente' => $visivelCliente,
            'retirada'        => $request->boolean('retirada_edt_servico'),
            'valor'           => $request->filled('valor_edt_servico') ? $request->valor_edt_servico : null,
        ]);

        return redirect()->back()->with('msg', 'Serviço atualizado com sucesso!');
    }
}
