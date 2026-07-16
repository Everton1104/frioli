<x-guest-layout>
    <x-auth-session-status class="mb-4" :status="session('status')" />

    @if(session('error'))
        <div class="text-red-600 text-sm mb-4">{{ session('error') }}</div>
    @endif

    <p class="text-sm text-gray-600 mb-4">
        Enviamos um código de 6 dígitos para <strong>+{{ $user->whatsapp }}</strong>.<br>
        Digite-o abaixo para entrar.
    </p>

    <form method="POST" action="{{ route('login.verify.check') }}">
        @csrf

        <div>
            <x-input-label for="codigo" :value="'Código de verificação'" />
            <x-text-input
                id="codigo"
                class="block mt-1 w-full"
                type="text"
                name="codigo"
                maxlength="6"
                placeholder="000000"
                inputmode="numeric"
                autocomplete="one-time-code"
                autofocus
                required
            />
            <x-input-error :messages="$errors->get('codigo')" class="mt-2" />
        </div>

        <div class="mt-4">
            <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                Confirmar
            </button>
        </div>
    </form>

    <form method="POST" action="{{ route('login.verify.resend') }}" class="mt-3">
        @csrf
        <button id="btn-reenviar" type="submit"
            class="w-full text-sm text-gray-600 underline hover:text-gray-900 disabled:opacity-50 disabled:cursor-not-allowed disabled:no-underline">
            Reenviar código
        </button>
    </form>

    <div class="mt-4 text-center">
        <a href="{{ route('login') }}" class="text-sm text-gray-600 underline hover:text-gray-900">
            Usar outro número
        </a>
    </div>

    <script>
        (function () {
            const btn  = document.getElementById('btn-reenviar');
            let restam = {{ (int) $aguardar }};
            if (restam <= 0) return;
            btn.disabled = true;
            function tick() {
                if (restam <= 0) {
                    btn.disabled = false;
                    btn.textContent = 'Reenviar código';
                    return;
                }
                btn.textContent = 'Reenviar código (' + restam + 's)';
                restam--;
                setTimeout(tick, 1000);
            }
            tick();
        })();
    </script>
</x-guest-layout>
