<x-guest-layout>
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div>
            <x-input-label for="whatsapp" :value="'WhatsApp'" />
            <x-text-input
                id="whatsapp"
                class="block mt-1 w-full"
                type="tel"
                name="whatsapp"
                :value="old('whatsapp')"
                required
                autofocus
                autocomplete="tel"
                placeholder="Ex: 11987654321"
            />
            <x-input-error :messages="$errors->get('whatsapp')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                Entrar
            </button>
        </div>
    </form>
</x-guest-layout>
