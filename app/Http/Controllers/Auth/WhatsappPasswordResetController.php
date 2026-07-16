<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\WhatsappController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class WhatsappPasswordResetController extends Controller
{
    // Passo 1 — formulário WhatsApp
    public function create()
    {
        return view('auth.forgot-password');
    }

    // Passo 1 — envia código
    public function sendCode(Request $request)
    {
        $request->validate(
            ['whatsapp' => ['required', 'string']],
            ['whatsapp.required' => 'Informe seu número de WhatsApp.']
        );

        $numero = preg_replace('/\D/', '', $request->whatsapp);
        if (strlen($numero) <= 11) {
            $numero = '55' . $numero;
        }

        $user = User::where('whatsapp', $numero)->where('excluido', 0)->first();

        if ($user) {
            WhatsappController::enviarCodigoVerificacao($user);
            $request->session()->put('pwd_reset_whatsapp', $numero);
        }

        // Redireciona sempre para evitar enumeração de usuários
        return redirect()->route('password.whatsapp.verify')
            ->with('status', 'Se esse número estiver cadastrado, você receberá um código de 6 dígitos no WhatsApp.');
    }

    // Passo 2 — formulário do código
    public function showCodeForm(Request $request)
    {
        if (!$request->session()->has('pwd_reset_whatsapp')) {
            return redirect()->route('password.request');
        }

        return view('auth.reset-password-code');
    }

    // Passo 2 — verifica código
    public function verifyCode(Request $request)
    {
        $request->validate(
            ['codigo' => ['required', 'string', 'size:6']],
            [
                'codigo.required' => 'Digite o código de 6 dígitos.',
                'codigo.size'     => 'O código deve ter exatamente 6 dígitos.',
            ]
        );

        $numero = $request->session()->get('pwd_reset_whatsapp');
        if (!$numero) {
            return redirect()->route('password.request');
        }

        $user = User::where('whatsapp', $numero)->where('excluido', 0)->first();

        if (
            !$user
            || $user->whatsapp_code !== $request->codigo
            || !$user->whatsapp_code_expires_at
            || now()->gt($user->whatsapp_code_expires_at)
        ) {
            return back()->withErrors(['codigo' => 'Código inválido ou expirado.']);
        }

        $user->whatsapp_code            = null;
        $user->whatsapp_code_expires_at = null;
        $user->save();

        $request->session()->forget('pwd_reset_whatsapp');
        $request->session()->put('pwd_reset_user_id', $user->id);

        return redirect()->route('password.reset');
    }

    // Passo 3 — formulário nova senha
    public function showNewPasswordForm(Request $request)
    {
        if (!$request->session()->has('pwd_reset_user_id')) {
            return redirect()->route('password.request');
        }

        return view('auth.reset-password');
    }

    // Passo 3 — salva nova senha
    public function updatePassword(Request $request)
    {
        $request->validate(
            [
                'password' => ['required', 'confirmed', Password::defaults()],
            ],
            [
                'password.required'  => 'Digite a nova senha.',
                'password.confirmed' => 'As senhas não conferem.',
            ]
        );

        $userId = $request->session()->get('pwd_reset_user_id');
        if (!$userId) {
            return redirect()->route('password.request');
        }

        $user = User::find($userId);
        if (!$user) {
            return redirect()->route('password.request');
        }

        $user->password = Hash::make($request->password);
        $user->save();

        $request->session()->forget('pwd_reset_user_id');

        return redirect()->route('login')
            ->with('status', 'Senha redefinida com sucesso! Faça login.');
    }
}
