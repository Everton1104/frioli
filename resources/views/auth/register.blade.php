<x-guest-layout>
    <form method="POST" action="{{ route('register') }}" id="register-form">
        @csrf

        <!-- Nome -->
        <div>
            <x-input-label for="nome" :value="'Nome completo'" />
            <x-text-input id="nome" class="block mt-1 w-full" type="text" name="nome" :value="old('nome')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('nome')" class="mt-2" />
        </div>

        <!-- WhatsApp -->
        <div class="mt-4">
            <x-input-label for="whatsapp" :value="'WhatsApp'" />
            <x-text-input id="whatsapp" class="block mt-1 w-full" type="tel" name="whatsapp" :value="old('whatsapp')" required placeholder="Ex: 11987654321" />
            <p class="text-xs text-gray-500 mt-1">Somente números, sem código do país.</p>
            <x-input-error :messages="$errors->get('whatsapp')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 me-auto" href="{{ route('dashboard') }}">
                Voltar
            </a>

            <x-primary-button>
                Cadastrar
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
