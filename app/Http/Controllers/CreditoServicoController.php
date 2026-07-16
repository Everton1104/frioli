<?php

namespace App\Http\Controllers;

use App\Models\AgendamentoModel;
use App\Models\CreditoServico;
use App\Models\ServicosModel;
use App\Models\User;
use Illuminate\Http\Request;

class CreditoServicoController extends Controller
{
    private function autorizar(): void
    {
        $u = auth()->user();
        abort_unless($u && ($u->adm || $u->func), 403);
    }

    // Lista os pacotes contratados de um cliente, com usadas/restantes.
    public function index($userId)
    {
        $this->autorizar();

        $creditos = CreditoServico::with('servico')
            ->withCount('agendamentos')
            ->where('user_id', $userId)
            ->orderBy('id')
            ->get()
            ->map(function (CreditoServico $c) {
                return [
                    'id'              => $c->id,
                    'servico_id'      => $c->servico_id,
                    'descricao'       => $c->servico->descricao ?? '—',
                    'visivel_cliente' => (bool) ($c->servico->visivel_cliente ?? true),
                    'quantidade'      => $c->quantidade,
                    'usadas'          => $c->usadas(),
                    'restantes'       => $c->restantes(),
                    'criado_em'       => $c->created_at?->format('d/m/Y'),
                ];
            })
            ->values();

        return response()->json($creditos);
    }

    // Adiciona um novo pacote de unidades de um serviço ao cliente.
    // Cada adição cria um pacote próprio (o cliente pode ter vários do mesmo serviço).
    public function store(Request $request, $userId)
    {
        $this->autorizar();

        $request->validate([
            'servico_id' => ['required', 'integer'],
            'quantidade' => ['required', 'integer', 'min:1', 'max:999'],
        ], [
            'servico_id.required' => 'Selecione o serviço.',
            'quantidade.required' => 'Informe a quantidade.',
            'quantidade.min'      => 'A quantidade deve ser no mínimo 1.',
        ]);

        $user    = User::findOrFail($userId);
        $servico = ServicosModel::where('excluido', 0)->findOrFail($request->servico_id);

        CreditoServico::create([
            'user_id'    => $user->id,
            'servico_id' => $servico->id,
            'quantidade' => (int) $request->quantidade,
        ]);

        return response()->json(['ok' => true]);
    }

    // Remove um pacote do cliente. Agendamentos que descontavam dele passam a livres.
    public function destroy($userId, $creditoId)
    {
        $this->autorizar();

        $credito = CreditoServico::where('user_id', $userId)->findOrFail($creditoId);

        AgendamentoModel::where('credito_servico_id', $credito->id)->update([
            'credito_servico_id' => null,
            'consome_credito'    => false,
        ]);

        $credito->delete();

        return response()->json(['ok' => true]);
    }
}
