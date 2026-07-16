<x-guest-layout>
    <x-auth-session-status class="mb-4" :status="session('status')" />

    @if(session('error'))
        <div class="text-red-600 text-sm mb-4">{{ session('error') }}</div>
    @endif

    <p class="text-sm text-gray-600 mb-4">
        Olá, <strong>{{ ucfirst($user->name) }}</strong>. Informe sua senha para entrar.
    </p>

    <form method="POST" action="{{ route('login.senha.check') }}">
        @csrf

        <div>
            <x-input-label for="senha" :value="'Senha'" />
            <x-text-input
                id="senha"
                class="block mt-1 w-full"
                type="password"
                name="senha"
                required
                autofocus
                autocomplete="current-password"
            />
            <x-input-error :messages="$errors->get('senha')" class="mt-2" />
        </div>

        <div class="mt-4">
            <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                Entrar
            </button>
        </div>
    </form>

    <div class="mt-4 text-center">
        <a href="{{ route('login') }}" class="text-sm text-gray-600 underline hover:text-gray-900">
            Usar outro número
        </a>
    </div>
</x-guest-layout>
