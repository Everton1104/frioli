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
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    // Etapa 1: recebe o WhatsApp, envia o OTP
    public function sendCode(Request $request): RedirectResponse
    {
        $request->validate(
            ['whatsapp' => ['required', 'string']],
            ['whatsapp.required' => 'Informe seu WhatsApp.']
        );

        $key = 'login-otp:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return back()->withErrors([
                'whatsapp' => "Muitas tentativas. Tente novamente em {$seconds}s.",
            ]);
        }

        $numero = preg_replace('/\D/', '', $request->whatsapp);
        if (strlen($numero) <= 11) {
            $numero = '55' . $numero;
        }

        $user = User::where('whatsapp', $numero)->where('excluido', 0)->first();

        if (!$user) {
            RateLimiter::hit($key);
            return back()
                ->withErrors(['whatsapp' => 'Número não encontrado.'])
                ->withInput();
        }

        // Adm e funcionário usam senha
        if ($user->adm || $user->func) {
            $request->session()->put('login_user_id', $user->id);
            return redirect()->route('login.senha');
        }

        // Cliente: fluxo OTP — respeita cooldown de 60s
        if ($user->whatsapp_code_expires_at && now()->lt($user->whatsapp_code_expires_at)) {
            $aguardar = max(0, (int) $user->whatsapp_code_expires_at->diffInSeconds(now()) - 540);
            if ($aguardar > 0) {
                $request->session()->put('login_user_id', $user->id);
                return redirect()->route('login.verify')
                    ->with('error', "Aguarde {$aguardar}s antes de solicitar um novo código.");
            }
        }

        WhatsappController::enviarCodigoVerificacao($user);
        RateLimiter::hit($key);

        $request->session()->put('login_user_id', $user->id);

        return redirect()->route('login.verify')
            ->with('status', 'Código enviado para seu WhatsApp!');
    }

    // Etapa 2: exibe formulário de código
    public function showVerify(Request $request): mixed
    {
        $userId = $request->session()->get('login_user_id');
        if (!$userId) {
            return redirect()->route('login');
        }

        $user = User::find($userId);
        if (!$user) {
            return redirect()->route('login');
        }

        $aguardar = 0;
        if ($user->whatsapp_code_expires_at && now()->lt($user->whatsapp_code_expires_at)) {
            $aguardar = max(0, (int) $user->whatsapp_code_expires_at->diffInSeconds(now()) - 540);
        }

        return view('auth.login-verify', compact('user', 'aguardar'));
    }

    // Etapa 2: valida o código e autentica
    public function verify(Request $request): RedirectResponse
    {
        $userId = $request->session()->get('login_user_id');
        if (!$userId) {
            return redirect()->route('login');
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
            return redirect()->route('login');
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

        // Sessão sempre persistente (remember = true)
        Auth::login($user, true);
        $request->session()->regenerate();
        $request->session()->forget('login_user_id');

        return redirect()->intended(route('dashboard', absolute: false));
    }

    // Reenviar código OTP
    public function resend(Request $request): RedirectResponse
    {
        $userId = $request->session()->get('login_user_id');
        if (!$userId) {
            return redirect()->route('login');
        }

        $user = User::find($userId);
        if (!$user) {
            return redirect()->route('login');
        }

        $aguardar = ($user->whatsapp_code_expires_at && now()->lt($user->whatsapp_code_expires_at))
            ? max(0, (int) $user->whatsapp_code_expires_at->diffInSeconds(now()) - 540)
            : 0;

        if ($aguardar > 0) {
            return redirect()->route('login.verify')
                ->with('error', "Aguarde {$aguardar}s antes de reenviar.");
        }

        WhatsappController::enviarCodigoVerificacao($user);

        return redirect()->route('login.verify')->with('status', 'Código reenviado!');
    }

    public function showSenha(Request $request): mixed
    {
        $userId = $request->session()->get('login_user_id');
        if (!$userId) return redirect()->route('login');

        $user = User::find($userId);
        if (!$user || (!$user->adm && !$user->func)) {
            return redirect()->route('login');
        }

        return view('auth.login-senha', compact('user'));
    }

    public function loginComSenha(Request $request): RedirectResponse
    {
        $userId = $request->session()->get('login_user_id');
        if (!$userId) return redirect()->route('login');

        $user = User::find($userId);
        if (!$user || (!$user->adm && !$user->func)) {
            return redirect()->route('login');
        }

        $request->validate(
            ['senha' => ['required', 'string']],
            ['senha.required' => 'Informe a senha.']
        );

        $key = 'login-senha:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return back()->withErrors(['senha' => "Muitas tentativas. Tente novamente em {$seconds}s."]);
        }

        if (!Hash::check($request->senha, $user->password)) {
            RateLimiter::hit($key);
            return back()->withErrors(['senha' => 'Senha incorreta.']);
        }

        RateLimiter::clear($key);
        Auth::login($user, true);
        $request->session()->regenerate();
        $request->session()->forget('login_user_id');

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
