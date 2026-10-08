<div>
    @include('livewire.referentiels.onglets')

    <h1 class="mb-2 text-xl font-semibold">Paramètres</h1>
    <p class="mb-6 text-sm text-stone-500">
        « Non défini » n'est pas zéro : tant qu'un seuil n'est pas fixé, chaque opération concernée est validée (deux fois pour un prêt).
        Un plafond non défini ne bloque rien, la validation restant obligatoire.
    </p>

    <div class="divide-y divide-stone-100 rounded-xl border border-stone-200 bg-white shadow-sm">
        @foreach ($cles as $cle)
            @php($actuelle = $valeurs[$cle->value] ?? null)
            <div wire:key="parametre-{{ $cle->value }}" class="flex flex-wrap items-center justify-between gap-4 px-4 py-4">
                <div class="min-w-64 flex-1">
                    <p class="font-medium">{{ $cle->libelle() }}</p>
                    <p class="text-sm text-stone-500">{{ $cle->aide() }}</p>
                </div>

                @if ($edition === $cle->value)
                    <form wire:submit="enregistrer" class="flex flex-wrap items-start gap-2">
                        <div>
                            <div class="flex items-center gap-2">
                                @if ($cle->estUnChoix())
                                    <select wire:model="valeur" id="valeur-{{ $cle->value }}"
                                        class="rounded-md border border-stone-300 px-3 py-1.5 focus:border-emerald-600 focus:outline-none">
                                        <option value="">— Non défini —</option>
                                        @foreach ($cle->options() as $valeurOption => $libelleOption)
                                            <option value="{{ $valeurOption }}">{{ $libelleOption }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input wire:model="valeur" type="number" step="1" min="0" id="valeur-{{ $cle->value }}"
                                        class="w-40 rounded-md border border-stone-300 px-3 py-1.5 text-right focus:border-emerald-600 focus:outline-none">
                                    <span class="text-sm text-stone-600">{{ $cle->unite() }}</span>
                                @endif
                            </div>
                            @error('valeur') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                        </div>
                        <button type="submit" class="rounded-md bg-emerald-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-800">Enregistrer</button>
                        <button type="button" wire:click="annuler" class="rounded-md px-3 py-1.5 text-sm text-stone-700 hover:bg-stone-100">Annuler</button>
                    </form>
                @else
                    <div class="flex items-center gap-4">
                        <span @class(['font-medium', 'text-amber-700' => $actuelle === null])>
                            @if ($actuelle === null)
                                Non défini
                            @elseif ($cle->estUnChoix())
                                {{ $cle->options()[$actuelle] ?? $actuelle }}
                            @else
                                {{ str_replace(' FCFA', ' '.$cle->unite(), \App\Support\Format::fcfa((int) $actuelle)) }}
                            @endif
                        </span>
                        <button type="button" wire:click="modifier('{{ $cle->value }}')"
                            class="rounded-md px-3 py-1 text-sm text-emerald-800 hover:bg-emerald-50">Modifier</button>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>
