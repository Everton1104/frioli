<x-guest-layout>
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <div class="mb-4 text-sm text-gray-600">
        Digite o código de 6 dígitos enviado para o seu WhatsApp.
    </div>

    <form method="POST" action="{{ route('password.whatsapp.check') }}">
        @csrf

        <div>
            <x-input-label for="codigo" :value="'Código de verificação'" />
            <x-text-input id="codigo" class="block mt-1 w-full tracking-widest text-center text-lg"
                type="text" name="codigo" required autofocus
                maxlength="6" placeholder="000000"
                inputmode="numeric" autocomplete="one-time-code" />
            <x-input-error :messages="$errors->get('codigo')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-gray-600 hover:text-gray-900 me-auto" href="{{ route('password.request') }}">
                Usar outro número
            </a>
            <x-primary-button>
                Confirmar código
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
