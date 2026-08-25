<x-guest-layout>
    <x-auth-session-status class="mb-4" :status="session('status')" />

    @if (session('error'))
        <div class="text-red-700 text-sm mb-4">{{ session('error') }}</div>
    @endif

    <p class="text-sm mb-4">
        Informe seus dados para <strong>agendar online</strong>. Enviamos um código de
        confirmação no WhatsApp.
    </p>

    <form method="POST" action="{{ route('agendar.entrar.codigo') }}">
        @csrf

        <div>
            <x-input-label for="name" :value="'Nome completo'" />
            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="whatsapp" :value="'WhatsApp'" />
            <x-text-input id="whatsapp" class="block mt-1 w-full" type="tel" name="whatsapp" :value="old('whatsapp')" required autocomplete="tel" placeholder="Ex: 11987654321" />
            <x-input-error :messages="$errors->get('whatsapp')" class="mt-2" />
        </div>

        <div class="mt-4">
            <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-offset-2 transition ease-in-out duration-150">
                Continuar
            </button>
        </div>
    </form>

    <div class="mt-4 text-center">
        <a href="{{ route('login') }}" class="text-sm underline">Já tem conta? Entrar</a>
    </div>
</x-guest-layout>
