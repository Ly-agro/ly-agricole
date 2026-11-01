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

    @if ($statut !== '')
        <p class="mt-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-900">{{ $statut }}</p>
    @endif
    @error('actionCompte')
        <p class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-900">{{ $message }}</p>
    @enderror

    <section class="mt-8">
        <h2 class="mb-2 text-lg font-semibold">Comptes de trésorerie</h2>
        <p class="mb-4 text-sm text-stone-600">Seuls les comptes à solde nul peuvent être fermés. Un compte avec historique peut être fermé, mais ne peut pas être supprimé. La suppression est réservée aux comptes jamais utilisés.</p>

        <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white shadow-sm">
            <table class="min-w-full text-sm">
                <thead class="bg-stone-50 text-left text-stone-600">
                    <tr>
                        <th class="px-4 py-3 font-medium">Compte</th>
                        <th class="px-4 py-3 font-medium">Campagne</th>
                        <th class="px-4 py-3 text-right font-medium">Solde</th>
                        <th class="px-4 py-3 font-medium">État</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse ($comptesTresorerie as $compte)
                        <tr wire:key="compte-tresorerie-{{ $compte->id }}">
                            <td class="px-4 py-3 font-medium">{{ $compte->nom }}</td>
                            <td class="px-4 py-3">
                                @if ($compte->campagne)
                                    {{ $compte->campagne->produit->nom }} · {{ $compte->campagne->code }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ \App\Support\Format::fcfa($soldesComptes[$compte->id]) }}</td>
                            <td class="px-4 py-3">{{ $compte->actif ? 'Actif' : 'Fermé' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                @if ($soldesComptes[$compte->id] === 0)
                                    @if ($compte->actif)
                                        <button type="button" wire:click="preparerActionCompte({{ $compte->id }}, 'fermer')" class="rounded-md px-2 py-1 text-xs text-amber-800 hover:bg-amber-50">Fermer</button>
                                    @endif
                                    @if (in_array($compte->id, $comptesSupprimables, true))
                                        <button type="button" wire:click="preparerActionCompte({{ $compte->id }}, 'supprimer')" class="rounded-md px-2 py-1 text-xs text-red-800 hover:bg-red-50">Supprimer</button>
                                    @endif
                                @endif
                            </td>
                        </tr>
                        @if ($compteActionId === $compte->id)
                            <tr>
                                <td colspan="5" class="bg-stone-50 px-4 py-3">
                                    <p class="text-sm text-stone-800">
                                        @if ($actionCompte === 'fermer')
                                            Confirmer la fermeture de « {{ $compte->nom }} » ? Le compte restera consultable et son historique sera conservé.
                                        @else
                                            Confirmer la suppression définitive du compte jamais utilisé « {{ $compte->nom }} » ?
                                        @endif
                                    </p>
                                    @error('actionCompte') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                                    <div class="mt-2 flex gap-2">
                                        <button type="button" wire:click="confirmerActionCompte" class="rounded-md bg-red-700 px-3 py-1.5 text-xs font-medium text-white hover:bg-red-800">Confirmer</button>
                                        <button type="button" wire:click="annulerActionCompte" class="rounded-md px-3 py-1.5 text-xs text-stone-700 hover:bg-stone-200">Annuler</button>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-stone-500">Aucun compte de trésorerie.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
