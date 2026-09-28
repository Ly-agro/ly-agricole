@php($champ = 'block w-full rounded-md border border-stone-300 px-3 py-2 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 focus:outline-none')
<div>
    <div class="mb-6">
        <a href="{{ route('prets') }}" class="text-sm text-emerald-800 hover:underline">← Prêts</a>
        <div class="mt-2 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">Prêt <span class="font-mono">{{ $pret->reference }}</span></h1>
                <p class="text-stone-600">
                    <a href="{{ route('producteurs.fiche', $pret->producteur) }}" class="text-emerald-800 hover:underline">{{ $pret->producteur->nomComplet() }}</a>
                    — {{ $pret->producteur->village->nom }} · campagne {{ $pret->campagne->produit->nom }} {{ $pret->campagne->code }}
                </p>
            </div>
            <p class="text-lg font-semibold" id="statut-pret">{{ $pret->statut->libelle() }}</p>
        </div>
    </div>

    @if ($statut !== '')
        <p class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-900">{{ $statut }}</p>
    @endif
    @error('action')
        <p class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-800">{{ $message }}</p>
    @enderror

    <dl class="grid gap-4 rounded-xl border border-stone-200 bg-white p-6 text-sm shadow-sm sm:grid-cols-2 lg:grid-cols-4">
        <div><dt class="text-stone-500">Montant</dt><dd class="text-base font-semibold tabular-nums">{{ \App\Support\Format::fcfa($pret->montant_fcfa) }}</dd></div>
        <div><dt class="text-stone-500">Décaissé</dt><dd class="text-base font-semibold tabular-nums" id="decaisse">{{ \App\Support\Format::fcfa($decaisse) }}</dd></div>
        <div><dt class="text-stone-500">Reste à décaisser</dt><dd class="text-base font-semibold tabular-nums">{{ \App\Support\Format::fcfa($pret->montant_fcfa - $decaisse) }}</dd></div>
        <div><dt class="text-stone-500">Forme</dt><dd>{{ $pret->forme->libelle() }}</dd></div>
        <div><dt class="text-stone-500">Échéance</dt><dd>{{ $pret->echeance->format('d/m/Y') }}</dd></div>
        <div>
            <dt class="text-stone-500">Kilos attendus (estimation)</dt>
            <dd>
                @if ($pret->grammes_attendus !== null)
                    {{ \App\Support\Format::kg($pret->grammes_attendus) }} <span class="text-stone-500">à {{ \App\Support\Format::fcfa($pret->prix_reference_kg_fcfa) }}/kg</span>
                @else — @endif
            </dd>
        </div>
        <div><dt class="text-stone-500">Surface financée</dt><dd>{{ $surface === null ? 'non relevée' : \App\Support\Format::hectares($surface) }}</dd></div>
        <div><dt class="text-stone-500">Demande saisie par</dt><dd>{{ $pret->auteur->nom }}, le {{ $pret->created_at->format('d/m/Y') }}</dd></div>
        @if ($pret->parcelles->isNotEmpty())
            <div class="sm:col-span-2 lg:col-span-4"><dt class="text-stone-500">Parcelles</dt><dd>{{ $pret->parcelles->pluck('nom')->join(', ') }}</dd></div>
        @endif
        @if ($pret->partie_liee)
            <div class="sm:col-span-2 lg:col-span-4 text-amber-800">
                Partie liée (art. 17.3) — <a href="{{ route('prets.accord', $pret) }}" target="_blank" class="underline">accord écrit</a>
            </div>
        @endif
        @if ($pret->motif_refus)
            <div class="sm:col-span-2 lg:col-span-4 text-red-800">Refusé : {{ $pret->motif_refus }}</div>
        @endif
    </dl>

    <section class="mt-6 rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold">Validations ({{ $pret->validations->count() }}/{{ $pret->validations_requises }})</h2>
            @if ($peutValider)
                <div class="flex gap-2">
                    <button type="button" wire:click="valider" wire:confirm="Valider le prêt {{ $pret->reference }} de {{ \App\Support\Format::fcfa($pret->montant_fcfa) }} ?"
                        class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">Valider</button>
                    <button type="button" wire:click="ouvrirRefus" class="rounded-md px-4 py-2 text-sm text-red-800 hover:bg-red-50">Refuser</button>
                </div>
            @elseif ($pret->statut->value === 'demande' && $pret->cree_par === auth()->id())
                <span class="text-sm text-stone-500">Votre demande : la validation revient à une autre personne.</span>
            @endif
        </div>
        <ul class="mt-3 space-y-1 text-sm">
            @forelse ($pret->validations as $v)
                <li>✓ {{ $v->user->nom }}, le {{ $v->created_at->format('d/m/Y à H:i') }}</li>
            @empty
                <li class="text-stone-500">Aucune validation pour l'instant.</li>
            @endforelse
        </ul>

        @if ($refusOuvert)
            <form wire:submit="refuser" class="mt-4 rounded-md border border-red-200 bg-red-50 p-4">
                <label for="motifRefus" class="block text-sm font-medium text-red-950">Motif du refus</label>
                <input wire:model="motifRefus" id="motifRefus" type="text" class="mt-1 block w-full rounded-md border border-red-300 px-3 py-2 focus:outline-none">
                @error('motifRefus') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                <button type="submit" class="mt-3 rounded-md bg-red-700 px-4 py-2 text-sm font-medium text-white hover:bg-red-800">Refuser la demande</button>
            </form>
        @endif
    </section>

    <section class="mt-6 rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold">Décaissements</h2>
            @if ($peutDecaisser)
                <button type="button" wire:click="ouvrirDecaissement" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">Verser une tranche</button>
            @endif
        </div>

        @if ($decaissementOuvert)
            <form wire:submit="decaisser" class="mt-4 rounded-md border border-stone-200 bg-stone-50 p-4">
                <p class="mb-3 text-sm text-stone-600">Versement en {{ $mode->libelle() }}.</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="compteId" class="mb-1 block text-sm font-medium text-stone-700">Depuis le compte</label>
                        <select wire:model="compteId" id="compteId" class="{{ $champ }}">
                            <option value="">— Choisir —</option>
                            @foreach ($comptes as $c)
                                <option value="{{ $c->id }}">{{ $c->nom }}</option>
                            @endforeach
                        </select>
                        @error('compteId') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="montant" class="mb-1 block text-sm font-medium text-stone-700">Montant (FCFA)</label>
                        <input wire:model="montant" id="montant" type="text" inputmode="numeric" class="{{ $champ }} text-right">
                        @error('montant') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="dateDecaissement" class="mb-1 block text-sm font-medium text-stone-700">Date</label>
                        <input wire:model="dateDecaissement" id="dateDecaissement" type="date" class="{{ $champ }}">
                        @error('dateDecaissement') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    @if ($mode->value === 'mobile_money')
                        <div>
                            <label for="reference" class="mb-1 block text-sm font-medium text-stone-700">Référence de la transaction</label>
                            <input wire:model="reference" id="reference" type="text" class="{{ $champ }}">
                            @error('reference') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                        </div>
                    @else
                        <div>
                            <label for="recu" class="mb-1 block text-sm font-medium text-stone-700">Reçu signé par le producteur</label>
                            <input wire:model="recu" id="recu" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" class="block text-sm">
                            @error('recu') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                        </div>
                    @endif
                </div>
                <button type="submit" wire:loading.attr="disabled" class="mt-4 rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:opacity-60">Enregistrer le versement</button>
            </form>
        @endif

        <table class="mt-4 min-w-full text-sm">
            <thead class="text-left text-stone-600">
                <tr>
                    <th class="py-2 pr-4 font-medium">Date</th>
                    <th class="py-2 pr-4 text-right font-medium">Montant</th>
                    <th class="py-2 pr-4 font-medium">Mode</th>
                    <th class="py-2 pr-4 font-medium">Compte</th>
                    <th class="py-2 pr-4 font-medium">Preuve</th>
                    <th class="py-2 pr-4 font-medium">Par</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($pret->decaissements as $d)
                    <tr @class(['text-stone-400 line-through' => $d->mouvement->contrePassation !== null])>
                        <td class="py-2 pr-4">{{ $d->date_decaissement->format('d/m/Y') }}</td>
                        <td class="py-2 pr-4 text-right tabular-nums">{{ \App\Support\Format::fcfa($d->montant_fcfa) }}</td>
                        <td class="py-2 pr-4">{{ $d->mode->libelle() }}</td>
                        <td class="py-2 pr-4">{{ $d->compte->nom }}</td>
                        <td class="py-2 pr-4">
                            @if ($d->reference_paiement) réf. {{ $d->reference_paiement }} @endif
                            @if ($d->justificatif) <a href="{{ route('prets.recu', $d) }}" target="_blank" class="text-emerald-800 underline">reçu</a> @endif
                        </td>
                        <td class="py-2 pr-4">{{ $d->auteur->nom }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-4 text-stone-500">Aucun versement.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
</div>
