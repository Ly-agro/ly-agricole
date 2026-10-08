<div>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">{{ $agentsSeulement ? 'Comptes des agents' : 'Utilisateurs' }}</h1>
            @if ($agentsSeulement)
                <p class="mt-1 text-sm text-stone-500">Vous créez et gérez les comptes des agents de terrain. Les autres comptes sont gérés par l'administrateur.</p>
            @endif
        </div>

        <button
            wire:click="nouveau"
            type="button"
            class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800"
        >
            {{ $agentsSeulement ? 'Nouvel agent' : 'Nouveau compte' }}
        </button>
    </div>

    @if ($editionId !== null)
        <form wire:submit="enregistrer" class="mb-8 rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 font-semibold">
                {{ $editionId === 0 ? 'Nouveau compte' : 'Modifier le compte' }}
            </h2>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="nom" class="mb-1 block text-sm font-medium text-stone-700">Nom</label>
                    <input wire:model="nom" id="nom" type="text" required
                        class="block w-full rounded-md border border-stone-300 px-3 py-2 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 focus:outline-none">
                    @error('nom') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="role" class="mb-1 block text-sm font-medium text-stone-700">Rôle</label>
                    <select wire:model="role" id="role" required
                        class="block w-full rounded-md border border-stone-300 px-3 py-2 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 focus:outline-none">
                        <option value="">— Choisir —</option>
                        @foreach ($roles as $r)
                            <option value="{{ $r->value }}">{{ $r->libelle() }}</option>
                        @endforeach
                    </select>
                    @error('role') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="email" class="mb-1 block text-sm font-medium text-stone-700">Adresse e-mail</label>
                    <input wire:model="email" id="email" type="email" required autocomplete="off"
                        class="block w-full rounded-md border border-stone-300 px-3 py-2 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 focus:outline-none">
                    @error('email') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="telephone" class="mb-1 block text-sm font-medium text-stone-700">Téléphone <span class="font-normal text-stone-500">(facultatif)</span></label>
                    <input wire:model="telephone" id="telephone" type="tel"
                        class="block w-full rounded-md border border-stone-300 px-3 py-2 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 focus:outline-none">
                    @error('telephone') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="motDePasse" class="mb-1 block text-sm font-medium text-stone-700">
                        {{ $editionId === 0 ? 'Mot de passe' : 'Nouveau mot de passe' }}
                        @if ($editionId !== 0)
                            <span class="font-normal text-stone-500">(laisser vide pour ne pas changer)</span>
                        @endif
                    </label>
                    <input wire:model="motDePasse" id="motDePasse" type="password" autocomplete="new-password"
                        class="block w-full rounded-md border border-stone-300 px-3 py-2 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 focus:outline-none">
                    @error('motDePasse') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>

                <div class="flex flex-col justify-end">
                    <label class="flex items-center gap-2 text-sm text-stone-700">
                        <input wire:model="actif" type="checkbox" class="rounded border-stone-300 text-emerald-700">
                        Compte actif (peut se connecter)
                    </label>
                    @error('actif') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="mt-6 flex gap-3">
                <button type="submit" wire:loading.attr="disabled"
                    class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:opacity-60">
                    Enregistrer
                </button>
                <button type="button" wire:click="annuler"
                    class="rounded-md px-4 py-2 text-sm text-stone-700 hover:bg-stone-100">
                    Annuler
                </button>
            </div>
        </form>
    @endif

    <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-stone-50 text-left text-stone-600">
                <tr>
                    <th class="px-4 py-3 font-medium">Nom</th>
                    <th class="px-4 py-3 font-medium">Rôle</th>
                    <th class="px-4 py-3 font-medium">E-mail</th>
                    <th class="px-4 py-3 font-medium">Téléphone</th>
                    <th class="px-4 py-3 font-medium">État</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @foreach ($this->utilisateurs as $u)
                    <tr wire:key="utilisateur-{{ $u->id }}" @class(['text-stone-400' => ! $u->actif])>
                        <td class="px-4 py-3 font-medium">{{ $u->nom }}</td>
                        <td class="px-4 py-3">{{ $u->role?->libelle() ?? 'Aucun rôle' }}</td>
                        <td class="px-4 py-3">{{ $u->email }}</td>
                        <td class="px-4 py-3">{{ $u->telephone ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($u->actif)
                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-800">Actif</span>
                            @else
                                <span class="rounded-full bg-stone-100 px-2 py-0.5 text-xs text-stone-600">Désactivé</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button type="button" wire:click="modifier({{ $u->id }})"
                                class="rounded-md px-3 py-1 text-emerald-800 hover:bg-emerald-50">
                                Modifier
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
