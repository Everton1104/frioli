<x-guest-layout>
    <x-auth-session-status class="mb-4" :status="session('status')" />

    @if(!session('status'))
        <div class="mb-4 text-sm text-gray-600">
            Informe seu número de WhatsApp para receber um código de redefinição de senha.
        </div>

        <form method="POST" action="{{ route('password.email') }}">
            @csrf

            <div>
                <x-input-label for="whatsapp" :value="'WhatsApp'" />
                <x-text-input id="whatsapp" class="block mt-1 w-full" type="tel" name="whatsapp"
                    :value="old('whatsapp')" required autofocus placeholder="Ex: 11987654321" />
                <x-input-error :messages="$errors->get('whatsapp')" class="mt-2" />
            </div>

            <div class="flex items-center justify-end mt-4">
                <a class="underline text-sm text-gray-600 hover:text-gray-900 me-auto" href="{{ route('login') }}">
                    Voltar ao login
                </a>
                <x-primary-button>
                    Enviar código
                </x-primary-button>
            </div>
        </form>
    @endif
</x-guest-layout>
