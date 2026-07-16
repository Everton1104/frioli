<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\WhatsappController;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Cadastro público por OTP — porta de entrada do cliente da internet no booking.
 * Ao contrário do login (AuthenticatedSessionController), que exige conta prévia
 * ("Número não encontrado"), AQUI o visitante é CRIADO se não existir, validado por
 * OTP do WhatsApp, e termina como User verificado e logado. Reusa o primitivo
 * enviarCodigoVerificacao e a mesma regra de cooldown/validação do login.
 */
class PublicRegistrationController extends Controller
{
    // Etapa 1 — formulário (nome + WhatsApp)
    public function entrar(): View
    {
        return view('agendar.entrar');
    }

    // Etapa 1 (POST) — cria/recupera o User e dispara o OTP
    public function enviarCodigo(Request $request): RedirectResponse
    {
        $request->validate(
            [
                'name'     => ['required', 'string', 'min:3', 'max:255'],
                'whatsapp' => ['required', 'string'],
            ],
            [
                'name.required'     => 'Informe seu nome.',
                'name.min'          => 'Nome muito curto.',
                'whatsapp.required' => 'Informe seu WhatsApp.',
            ]
        );

        $key = 'register-otp:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return back()->withErrors(['whatsapp' => "Muitas tentativas. Tente novamente em {$seconds}s."])
                ->withInput();
        }

        $numero = preg_replace('/\D/', '', $request->whatsapp);
        if (strlen($numero) <= 11) {
            $numero = '55' . $numero;
        }
        if (strlen($numero) < 12) {
            return back()->withErrors(['whatsapp' => 'Número inválido.'])->withInput();
        }

        // Equipe (adm/func) não entra por aqui — usa o login com senha.
        $existente = User::where('whatsapp', $numero)->where('excluido', 0)->first();
        if ($existente && ($existente->adm || $existente->func)) {
            return redirect()->route('login')
                ->with('status', 'Este número já é cadastrado como equipe. Use o login.');
        }

        $user = $existente ?? User::create([
            'name'     => $request->name,
            'whatsapp' => $numero,
            'password' => Hash::make(Str::random(16)), // sem login por senha
        ]);

        if ($existente && trim($request->name) && $existente->name !== $request->name) {
            $existente->name = $request->name;
            $existente->save();
        }

        // Cooldown de ~60s (mesma regra do login).
        if ($user->whatsapp_code_expires_at && now()->lt($user->whatsapp_code_expires_at)) {
            $aguardar = max(0, (int) $user->whatsapp_code_expires_at->diffInSeconds(now()) - 540);
            if ($aguardar > 0) {
                $request->session()->put('register_user_id', $user->id);
                return redirect()->route('agendar.verificar')
                    ->with('error', "Aguarde {$aguardar}s antes de solicitar um novo código.");
            }
        }

        WhatsappController::enviarCodigoVerificacao($user);
        RateLimiter::hit($key);
        $request->session()->put('register_user_id', $user->id);

        return redirect()->route('agendar.verificar')
            ->with('status', 'Código enviado para seu WhatsApp!');
    }

    // Etapa 2 — formulário do código
    public function verificar(Request $request): mixed
    {
        $userId = $request->session()->get('register_user_id');
        if (!$userId) {
            return redirect()->route('agendar.entrar');
        }

        $user = User::find($userId);
        if (!$user) {
            return redirect()->route('agendar.entrar');
        }

        $aguardar = 0;
        if ($user->whatsapp_code_expires_at && now()->lt($user->whatsapp_code_expires_at)) {
            $aguardar = max(0, (int) $user->whatsapp_code_expires_at->diffInSeconds(now()) - 540);
        }

        return view('agendar.verificar', compact('user', 'aguardar'));
    }

    // Etapa 2 (POST) — valida o código, verifica o WhatsApp e autentica
    public function confirmar(Request $request): RedirectResponse
    {
        $userId = $request->session()->get('register_user_id');
        if (!$userId) {
            return redirect()->route('agendar.entrar');
        }

        $request->validate(
            ['codigo' => ['required', 'string', 'size:6']],
            [
                'codigo.required' => 'Digite o código recebido.',
                'codigo.size'     => 'O código deve ter 6 dígitos.',
            ]
        );

        $user = User::find($userId);
        if (!$user) {
            return redirect()->route('agendar.entrar');
        }

        $invalido = !$user->whatsapp_code_expires_at
            || $user->whatsapp_code !== $request->codigo
            || now()->gt($user->whatsapp_code_expires_at);

        if ($invalido) {
            return back()->withErrors(['codigo' => 'Código inválido ou expirado.']);
        }

        $user->whatsapp_code            = null;
        $user->whatsapp_code_expires_at = null;
        if (!$user->whatsapp_verified_at) {
            $user->whatsapp_verified_at = now();
        }
        $user->save();

        Auth::login($user, true);
        $request->session()->regenerate();
        $request->session()->forget('register_user_id');

        return redirect()->intended(route('agendar.index', absolute: false));
    }

    public function reenviar(Request $request): RedirectResponse
    {
        $userId = $request->session()->get('register_user_id');
        if (!$userId) {
            return redirect()->route('agendar.entrar');
        }

        $user = User::find($userId);
        if (!$user) {
            return redirect()->route('agendar.entrar');
        }

        $aguardar = ($user->whatsapp_code_expires_at && now()->lt($user->whatsapp_code_expires_at))
            ? max(0, (int) $user->whatsapp_code_expires_at->diffInSeconds(now()) - 540)
            : 0;

        if ($aguardar > 0) {
            return redirect()->route('agendar.verificar')
                ->with('error', "Aguarde {$aguardar}s antes de reenviar.");
        }

        WhatsappController::enviarCodigoVerificacao($user);

        return redirect()->route('agendar.verificar')->with('status', 'Código reenviado!');
    }
}
