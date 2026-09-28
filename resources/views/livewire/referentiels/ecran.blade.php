<div>
    @include('livewire.referentiels.onglets')

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-semibold">{{ $titrePage }}</h1>

        <button wire:click="nouveau" type="button"
            class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">
            Ajouter
        </button>
    </div>

    @if ($statut !== '')
        <p class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-900">
            {{ $statut }}
        </p>
    @endif

    @error('ligne')
        <p class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-800">{{ $message }}</p>
    @enderror

    @if ($editionId !== null)
        <form wire:submit="enregistrer" class="mb-8 rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 font-semibold">{{ $editionId === 0 ? 'Nouvelle ligne' : 'Modifier' }}</h2>

            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($champsFormulaire as $champ => $def)
                    <div wire:key="champ-{{ $champ }}" @class(['flex flex-col justify-end' => $def['type'] === 'checkbox'])>
                        @if ($def['type'] === 'checkbox')
                            <label class="flex items-center gap-2 text-sm text-stone-700">
                                <input wire:model="donnees.{{ $champ }}" type="checkbox" class="rounded border-stone-300 text-emerald-700">
                                {{ $def['libelle'] }}
                            </label>
                        @else
                            <label for="champ-{{ $champ }}" class="mb-1 block text-sm font-medium text-stone-700">{{ $def['libelle'] }}</label>

                            @if ($def['type'] === 'select')
                                <select wire:model="donnees.{{ $champ }}" id="champ-{{ $champ }}"
                                    class="block w-full rounded-md border border-stone-300 px-3 py-2 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 focus:outline-none">
                                    <option value="">— Choisir —</option>
                                    @foreach ($def['options'] ?? [] as $valeur => $libelle)
                                        <option value="{{ $valeur }}">{{ $libelle }}</option>
                                    @endforeach
                                </select>
                            @else
                                <input wire:model="donnees.{{ $champ }}" id="champ-{{ $champ }}"
                                    type="{{ $def['type'] }}" @if ($def['type'] === 'number') step="any" @endif
                                    class="block w-full rounded-md border border-stone-300 px-3 py-2 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 focus:outline-none">
                            @endif
                        @endif

                        @if (isset($def['aide']))
                            <p class="mt-1 text-xs text-stone-500">{{ $def['aide'] }}</p>
                        @endif
                        @error('donnees.'.$champ)
                            <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                        @enderror
                    </div>
                @endforeach
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
                    @foreach (array_keys($colonnesListe) as $libelle)
                        <th class="px-4 py-3 font-medium">{{ $libelle }}</th>
                    @endforeach
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($lignes as $ligne)
                    <tr wire:key="ligne-{{ $ligne->getKey() }}" @class(['text-stone-400' => $ligne->getAttribute('actif') === false])>
                        @foreach ($colonnesListe as $valeur)
                            <td class="px-4 py-3">{{ $valeur($ligne) }}</td>
                        @endforeach
                        <td class="whitespace-nowrap px-4 py-3 text-right">
                            @foreach ($actionsParLigne[$ligne->getKey()] ?? [] as $methode => $libelle)
                                <button type="button" wire:click="{{ $methode }}({{ $ligne->getKey() }})"
                                    wire:confirm="{{ $libelle }} : confirmer ?"
                                    class="rounded-md px-3 py-1 text-amber-800 hover:bg-amber-50">
                                    {{ $libelle }}
                                </button>
                            @endforeach
                            @if (in_array($ligne->getKey(), $modifiables, true))
                                <button type="button" wire:click="modifier({{ $ligne->getKey() }})"
                                    class="rounded-md px-3 py-1 text-emerald-800 hover:bg-emerald-50">
                                    Modifier
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($colonnesListe) + 1 }}" class="px-4 py-8 text-center text-stone-500">
                            Aucune ligne pour l'instant.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
