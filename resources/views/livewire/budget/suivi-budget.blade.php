@php use App\Support\Format; @endphp

<div>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <h1 class="text-xl font-semibold">Budget de campagne</h1>
        <div class="flex flex-wrap items-end gap-3 text-sm">
            <div>
                <label for="campagneId" class="mb-1 block text-stone-600">Campagne</label>
                <select wire:model.live="campagneId" id="campagneId" class="rounded-md border border-stone-300 px-3 py-1.5 focus:border-emerald-600 focus:outline-none">
                    @foreach ($campagnes as $c)
                        <option value="{{ $c->id }}">{{ $c->produit->nom }} · {{ $c->code }}</option>
                    @endforeach
                </select>
            </div>
            @if ($modifiable)
                <button type="button" wire:click="ouvrir" class="rounded-md bg-emerald-700 px-4 py-2 font-medium text-white hover:bg-emerald-800">Prévoir un poste</button>
            @endif
        </div>
    </div>

    @if ($statut !== '')
        <p class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-900">{{ $statut }}</p>
    @endif

    @if ($campagne === null)
        <p class="rounded-md border border-stone-200 bg-white px-4 py-8 text-center text-sm text-stone-500">Aucune campagne.</p>
    @else
        @if ($formulaire)
            <form wire:submit="enregistrer" class="mb-6 space-y-4 rounded-xl border border-stone-200 bg-white p-5">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="poste" class="mb-1 block text-sm font-medium text-stone-700">Poste</label>
                        <select wire:model.live="poste" id="poste" class="block w-full rounded-md border border-stone-300 px-3 py-2 focus:border-emerald-600 focus:outline-none">
                            <option value="">— Choisir —</option>
                            <option value="achats">Achats de produit (argent payé)</option>
                            <option value="prets">Prêts aux producteurs (argent versé)</option>
                            <optgroup label="Dépenses par catégorie">
                                @foreach ($categories as $cat)
                                    <option value="categorie-{{ $cat->id }}">{{ $cat->nom }}</option>
                                @endforeach
                            </optgroup>
                        </select>
                        @error('poste') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="montant" class="mb-1 block text-sm font-medium text-stone-700">Montant prévu (FCFA)</label>
                        <input wire:model="montant" id="montant" type="text" inputmode="numeric" placeholder="1 500 000" class="block w-full rounded-md border border-stone-300 px-3 py-2 focus:border-emerald-600 focus:outline-none">
                        @error('montant') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div>
                    <label for="note" class="mb-1 block text-sm font-medium text-stone-700">Note (optionnel)</label>
                    <input wire:model="note" id="note" type="text" placeholder="Hypothèse : 40 t à 425 F/kg" class="block w-full rounded-md border border-stone-300 px-3 py-2 focus:border-emerald-600 focus:outline-none">
                    @error('note') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                @if ($modification)
                    <div>
                        <label for="motif" class="mb-1 block text-sm font-medium text-stone-700">Motif de la modification (optionnel)</label>
                        <input wire:model="motif" id="motif" type="text" placeholder="Prix bord-champ relevé à 450 F/kg" class="block w-full rounded-md border border-stone-300 px-3 py-2 focus:border-emerald-600 focus:outline-none">
                        @error('motif') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                @endif
                <p class="text-xs text-stone-500">Si le poste est déjà prévu, son montant est remplacé ; l'ancien reste au journal, avec le motif.</p>
                <div class="flex justify-end gap-3">
                    <button type="button" wire:click="$set('formulaire', false)" class="text-sm text-stone-600 hover:underline">Annuler</button>
                    <button type="submit" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">Enregistrer</button>
                </div>
            </form>
        @endif

        <div class="mb-6 grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-stone-200 bg-white p-4">
                <p class="text-xs text-stone-500">Prévu</p>
                <p class="mt-1 text-xl font-semibold tabular-nums">{{ Format::fcfa($suivi['prevu']) }}</p>
            </div>
            <div class="rounded-xl border border-stone-200 bg-white p-4">
                <p class="text-xs text-stone-500">Réel (sorti)</p>
                <p @class(['mt-1 text-xl font-semibold tabular-nums', 'text-red-700' => $suivi['prevu'] > 0 && $suivi['reel'] > $suivi['prevu']])>{{ Format::fcfa($suivi['reel']) }}</p>
            </div>
            <div class="rounded-xl border border-stone-200 bg-white p-4">
                <p class="text-xs text-stone-500">En attente de validation</p>
                <p class="mt-1 text-xl font-semibold tabular-nums">{{ Format::fcfa($suivi['en_attente']) }}</p>
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white shadow-sm">
            <table class="min-w-full text-sm">
                <thead class="bg-stone-50 text-left text-stone-600">
                    <tr>
                        <th class="px-4 py-3 font-medium">Poste</th>
                        <th class="px-4 py-3 text-right font-medium">Prévu</th>
                        <th class="px-4 py-3 text-right font-medium">Réel</th>
                        <th class="px-4 py-3 text-right font-medium">Consommé</th>
                        <th class="px-4 py-3 text-right font-medium">Reste / dépassement</th>
                        <th class="px-4 py-3 text-right font-medium">En attente</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @foreach ($suivi['lignes'] as $l)
                        <tr wire:key="poste-{{ $l['cle'] }}">
                            <td class="px-4 py-3">
                                {{ $l['libelle'] }}
                                @if ($l['ligne']?->note)
                                    <span class="block text-xs text-stone-500">{{ $l['ligne']->note }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">
                                @if ($l['prevu'] === null)
                                    <span class="text-amber-700">Non prévu</span>
                                @else
                                    {{ Format::fcfa($l['prevu']) }}
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right font-medium tabular-nums">{{ Format::fcfa($l['reel']) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">
                                @if ($l['consomme_pour_mille'] === null)
                                    —
                                @else
                                    <span @class(['text-red-700 font-medium' => $l['consomme_pour_mille'] > 1000])>{{ intdiv($l['consomme_pour_mille'], 10) }},{{ $l['consomme_pour_mille'] % 10 }} %</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">
                                @if ($l['ecart'] === null)
                                    —
                                @elseif ($l['ecart'] < 0)
                                    <span class="font-medium text-red-700">Dépassé de {{ Format::fcfa(-$l['ecart']) }}</span>
                                @else
                                    {{ Format::fcfa($l['ecart']) }}
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-stone-600">{{ $l['en_attente'] > 0 ? Format::fcfa($l['en_attente']) : '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                @if ($modifiable)
                                    <button type="button" wire:click="ouvrir('{{ $l['cle'] }}')" class="rounded-md px-2 py-1 text-xs text-emerald-800 hover:bg-emerald-50">{{ $l['prevu'] === null ? 'Prévoir' : 'Modifier' }}</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="mt-4 text-xs text-stone-500">
            Réel = argent sorti : dépenses payées rattachées à cette campagne (une dépense sans campagne n'y figure pas),
            argent payé sur les achats validés (la part retenue sur un prêt n'est pas comptée deux fois),
            argent décaissé sur les prêts (les intrants remis à crédit sont déjà comptés à leur achat).
            @if ($campagne->statut === \App\Enums\StatutCampagne::Cloturee)
                Campagne clôturée : budget figé.
            @endif
        </p>
    @endif
</div>
