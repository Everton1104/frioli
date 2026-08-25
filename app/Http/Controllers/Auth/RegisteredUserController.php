<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        abort_unless(auth()->user()->adm || auth()->user()->func, 403);
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->adm || auth()->user()->func, 403);

        $rules = [
            'nome'     => ['required', 'string', 'max:255'],
            'whatsapp' => ['required', 'string', 'max:20'],
        ];

        $isAdm = auth()->user()->adm;

        if ($isAdm && $request->filled('senha')) {
            $rules['senha']             = ['min:6', 'confirmed'];
            $rules['senha_confirmation'] = ['required'];
        }

        $request->validate($rules, [
            'nome.required'     => 'Informe o nome.',
            'whatsapp.required' => 'Informe o número de WhatsApp.',
            'senha.confirmed'   => 'As senhas não conferem.',
            'senha.min'         => 'A senha deve ter no mínimo 6 caracteres.',
        ]);

        $numero = preg_replace('/\D/', '', $request->whatsapp);
        if (strlen($numero) <= 11) {
            $numero = '55' . $numero;
        }

        // WhatsApp é a chave do login (OTP/senha) — número duplicado tornaria o
        // login ambíguo (o .first() da tela de login escolheria uma das contas).
        $duplicado = User::where('whatsapp', $numero)->where('excluido', 0)->first();
        if ($duplicado) {
            return redirect()->back()->withErrors([
                'whatsapp' => 'Este WhatsApp já pertence a outro usuário (' . $duplicado->name . ').',
            ])->withInput($request->all());
        }

        $senha = ($isAdm && $request->filled('senha'))
            ? $request->senha
            : 'senha@padrao';

        $user = User::create([
            'name'     => $request->nome,
            'password' => Hash::make($senha),
            'whatsapp' => $numero,
            'adm'      => $isAdm && $request->input('tipo') === 'adm' ? 1 : 0,
            'func'     => $isAdm && $request->input('tipo') === 'func' ? 1 : 0,
            // Equipe (adm/func) entra por senha e não tem fluxo de OTP — sem isso
            // o middleware whatsapp.verified prende a conta na tela "confirme seu
            // número", impossível pra contas de teste com número fictício.
            'whatsapp_verified_at' => ($isAdm && in_array($request->input('tipo'), ['adm', 'func'])) ? now() : null,
        ]);

        event(new Registered($user));

        return redirect()->back()->with('msg', 'Usuário adicionado com sucesso');
    }

    public function delete(Request $request)
    {
        abort_unless(auth()->user()->adm || auth()->user()->func, 403);

        $validation = Validator::make($request->all(), [
            'id' => 'required|integer|gt:1',
        ]);

        if ($validation->fails()) {
            return redirect()->back()->with('msgErro', 'Falha ao excluir usuário!');
        }

        $user = User::find($request['id']);
        if (!$user) {
            return redirect()->back()->with('msgErro', 'Usuário não encontrado!');
        }

        // Apenas o super admin (id=1) exclui (soft) um administrador.
        if ($user->adm && (int) auth()->id() !== 1) {
            return redirect()->back()->with('msgErro', 'Apenas o super administrador pode excluir outro administrador.');
        }

        $user->update(['excluido' => 1]);
        return redirect()->back()->with('msg', 'Usuario excluido com sucesso!');
    }

    /**
     * Exclusão DEFINITIVA (hard delete) — só admin. Diferente da exclusão normal
     * (que só marca excluido=1 e mantém todo o histórico), esta remove o usuário
     * e tudo que é cascateado pelo schema (creditos_servico, planos_mensais e
     * ordem_pagamentos onde é paciente). Trata antes as referências que bloqueariam
     * o DELETE (avisos, agendamentos sem FK) ou que devem ser preservadas (ordens
     * criadas por ele → reatribuídas ao admin que exclui). Irreversível.
     */
    public function hardDelete(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->adm, 403);

        $id = (int) $request->input('id');
        if ($id <= 1) {
            return redirect()->back()->with('msgErro', 'Conta protegida: não pode ser excluída definitivamente.');
        }
        if ($id === (int) auth()->id()) {
            return redirect()->back()->with('msgErro', 'Você não pode excluir definitivamente a própria conta.');
        }

        $user = User::find($id);
        if (!$user) {
            return redirect()->back()->with('msgErro', 'Usuário não encontrado (já removido?).');
        }

        // Apenas o super admin (id=1) exclui DEFINITIVAMENTE um administrador.
        if ($user->adm && (int) auth()->id() !== 1) {
            return redirect()->back()->with('msgErro', 'Apenas o super administrador pode excluir outro administrador.');
        }

        $nome = $user->name;
        $adminId = (int) auth()->id();

        DB::transaction(function () use ($id, $adminId) {
            // 1) Avisos do usuário (user_id é NOT NULL + RESTRICT).
            DB::table('avisos')->where('user_id', $id)->delete();
            // 2) Agendamentos em que é paciente (user_id é bigInteger SEM FK — ficariam
            //    órfãos). Lembretes (lembrete_consulta) cascateiam via agendamento_id.
            DB::table('agendamentos')->where('user_id', $id)->delete();
            // 3) Ordens que ele criou (criado_por NOT NULL + RESTRICT): reatribui ao admin
            //    que exclui, preservando o histórico financeiro. Só relevante se for staff.
            DB::table('ordem_pagamentos')->where('criado_por', $id)->update(['criado_por' => $adminId]);
            // 4) Hard delete — cascateia (por design do schema) creditos_servico,
            //    planos_mensais e ordem_pagamentos (paciente); anula funcionario_id,
            //    disponibilidades.created_by e servicos.user_id (nullOnDelete).
            User::where('id', $id)->delete();
        });

        return redirect()->back()->with('msg', 'Usuário "' . $nome . '" excluído definitivamente (histórico removido).');
    }

    public function editar(Request $request)
    {
        abort_unless(auth()->user()->adm || auth()->user()->func, 403);

        $request->validate([
            'id'           => 'required|integer',
            'nome_edt'     => 'required|string|max:255',
            'whatsapp_edt' => 'nullable|string|max:20',
        ]);

        $user = User::find($request['id']);
        if (!$user) {
            return redirect()->back()->with('msgErro', 'Usuário não encontrado!');
        }

        // Super admin (id=1) só pode ser editado por ele mesmo.
        if ((int) $user->id === 1 && (int) auth()->id() !== 1) {
            return redirect()->back()->with('msgErro', 'O super admin só pode ser editado por ele mesmo.');
        }

        // Apenas o super admin (id=1) edita informações de outro administrador.
        // Um adm comum só edita a si mesmo; funcionário nunca edita adm.
        if ($user->adm && (int) auth()->id() !== 1 && (int) $user->id !== (int) auth()->id()) {
            return redirect()->back()->with('msgErro', 'Apenas o super administrador pode editar outro administrador.');
        }

        $isAdm = auth()->user()->adm;

        // Só admin pode trocar a senha e alterar o perfil (adm/func). O funcionário
        // edita apenas nome/whatsapp (e adiciona créditos) — nunca rebaixar um admin
        // nem resetar a senha dele.
        if ($isAdm && $request->filled('senha_edt')) {
            if ($request['senha_edt'] != $request['senha_confirmation_edt']) {
                return redirect()->back()->with('msgErro', 'Senhas não conferem!');
            }
            $user->update([
                'password' => Hash::make($request['senha_edt']),
            ]);
        }

        $updateData = ['name' => $request['nome_edt']];

        if ($isAdm) {
            // Só o super admin (id=1) concede acesso de administrador (não dá pra
            // criar um adm "par" sem passar pelo super admin).
            if ($request['tipo_edt'] == 'adm' && (int) auth()->id() !== 1) {
                return redirect()->back()->with('msgErro', 'Apenas o super administrador pode conceder acesso de administrador.');
            }
            $updateData['adm']  = $request['tipo_edt'] == 'adm'  ? 1 : 0;
            $updateData['func'] = $request['tipo_edt'] == 'func' ? 1 : 0;
        }

        if ($request->filled('whatsapp_edt')) {
            $numero = preg_replace('/\D/', '', $request['whatsapp_edt']);
            if (strlen($numero) <= 11) {
                $numero = '55' . $numero;
            }
            // Mesma regra do store: número duplicado torna o login ambíguo
            // (ignora a própria conta e contas excluídas).
            $duplicado = User::where('whatsapp', $numero)->where('excluido', 0)
                ->where('id', '!=', $user->id)->first();
            if ($duplicado) {
                return redirect()->back()->with('msgErro', 'Este WhatsApp já pertence a outro usuário (' . $duplicado->name . ').');
            }
            $updateData['whatsapp'] = $numero;
        }

        $user->update($updateData);
        return redirect()->back()->with('msg', 'Usuario atualizado com sucesso!');
    }

    public function search(Request $request)
    {
        abort_unless(auth()->user()->adm || auth()->user()->func, 403);

        $q      = trim($request->q);
        $campos = ['id', 'name', 'whatsapp', 'adm', 'func', 'indicado'];

        $users = User::select($campos)
            ->where('excluido', 0)
            // ?clientes=1: seletor de CLIENTE (créditos/plano) — não lista staff.
            // Sem o parâmetro é a busca da tabela de gestão, que precisa ver todos.
            ->when($request->boolean('clientes'), fn ($query) => $query->where('adm', 0)->where('func', 0))
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($q2) use ($q) {
                    $q2->where('name', 'like', "%{$q}%")
                       ->orWhere('whatsapp', 'like', "%{$q}%");
                });
            })
            ->when($request->boolean('clientes'), fn ($query) => $query->orderBy('name'))
            ->when(!$request->boolean('clientes'), function ($query) {
                $query->orderBy('adm', 'desc')->orderBy('func', 'desc')->orderBy('name');
            })
            ->paginate(10);

        return response()->json($users);
    }
}
