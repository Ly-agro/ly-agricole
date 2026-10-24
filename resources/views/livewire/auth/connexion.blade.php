<div>
    <h1 class="mb-6 text-lg font-semibold">Connexion</h1>

    <form wire:submit="connecter" class="space-y-5">
        <div>
            <label for="email" class="mb-1 block text-sm font-medium text-stone-700">Adresse e-mail</label>
            <input
                wire:model="email"
                id="email"
                type="email"
                autocomplete="username"
                autofocus
                required
                class="block w-full rounded-md border border-stone-300 px-3 py-2 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 focus:outline-none"
            >
            @error('email')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="mb-1 block text-sm font-medium text-stone-700">Mot de passe</label>
            <input
                wire:model="password"
                id="password"
                type="password"
                autocomplete="current-password"
                required
                class="block w-full rounded-md border border-stone-300 px-3 py-2 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 focus:outline-none"
            >
            @error('password')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <label class="flex items-center gap-2 text-sm text-stone-700">
            <input wire:model="remember" type="checkbox" class="rounded border-stone-300 text-emerald-700">
            Rester connecté sur cet ordinateur
        </label>

        <button
            type="submit"
            wire:loading.attr="disabled"
            class="w-full rounded-md bg-emerald-700 px-4 py-2 font-medium text-white hover:bg-emerald-800 disabled:opacity-60"
        >
            <span wire:loading.remove wire:target="connecter">Se connecter</span>
            <span wire:loading wire:target="connecter">Connexion…</span>
        </button>
    </form>

    <p class="mt-6 text-center text-xs text-stone-500">
        Mot de passe oublié ou compte bloqué : contactez l'administrateur.
    </p>
</div>
