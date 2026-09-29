@php use App\Services\PartageResultat;use App\Support\Format; @endphp

<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold">Résultat de campagne</h1>
            <p class="mt-1 text-sm text-stone-600">Résultat net et partage entre les investisseurs et LY (contrat, art. 10 à 14).</p>
        </div>
        <div class="text-sm">
            <label for="campagneId" class="mb-1 block text-stone-600">Campagne</label>
            <select wire:model.live="campagneId" id="campagneId" class="rounded-md border border-stone-300 px-3 py-1.5 focus:border-emerald-600 focus:outline-none">
                @foreach ($campagnes as $c)
                    <option value="{{ $c->id }}">{{ $c->produit->nom }} · {{ $c->code }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <p class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        <strong class="font-semibold">Résultat provisoire.</strong> Ces chiffres se recalculent à chaque opération et ne sont
        pas le rapport final de l'article 18. Rien n'est enregistré ni communiqué aux investisseurs depuis cet écran.
    </p>

    @if ($campagne === null)
        <div class="rounded-xl border border-dashed border-stone-300 bg-white p-8 text-center">
            <p class="font-medium">Aucune campagne.</p>
        </div>
    @else
        <div class="grid gap-6 lg:grid-cols-2">
            <div class="rounded-xl border border-stone-200 bg-white p-5">
                <h2 class="mb-3 font-semibold">Du résultat net (art. 10 et 11)</h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-stone-600">Recettes encaissées</dt><dd class="font-medium tabular-nums">{{ Format::fcfa($etat['recettes']) }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-stone-600">Valeur du stock invendu</dt><dd class="font-medium tabular-nums">{{ Format::fcfa($etat['valeur_stock_invendu']) }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-stone-600">Coût d'achat</dt><dd class="tabular-nums">− {{ Format::fcfa($etat['charges']['achats']) }}</dd></div>
                    @foreach ($etat['charges']['depenses'] as $d)
                        <div class="flex justify-between gap-4 pl-4"><dt class="text-stone-600">{{ $d['categorie'] }}</dt><dd class="tabular-nums">− {{ Format::fcfa($d['montant']) }}</dd></div>
                    @endforeach
                    <div class="flex justify-between gap-4 border-t border-stone-200 pt-2 text-base">
                        <dt class="font-semibold">Résultat net</dt>
                        <dd class="font-semibold tabular-nums {{ $etat['resultat_net'] < 0 ? 'text-red-700' : 'text-emerald-800' }}">{{ Format::fcfa($etat['resultat_net']) }}</dd>
                    </div>
                </dl>

                <div class="mt-5 border-t border-stone-100 pt-4 text-sm">
                    <label for="valeurStock" class="block font-medium text-stone-700">Valeur du stock invendu (art. 11.3), en FCFA</label>
                    <input wire:model.live.debounce.400ms="valeurStock" id="valeurStock" type="text" inputmode="numeric" placeholder="0"
                        class="mt-1 w-full rounded-md border border-stone-300 px-3 py-2 tabular-nums focus:border-emerald-600 focus:outline-none">
                    <p class="mt-1 text-xs text-stone-500">
                        Non calculée : elle dépend de la décision de LY (vente ou reprise) et de deux offres de prix écrites.
                        Le stock en magasin est de <span class="tabular-nums">{{ Format::kg($etat['info']['stock_invendu_g']) }}</span>.
                    </p>
                    @if ($erreurStock)<p class="mt-1 text-sm text-red-700">{{ $erreurStock }}</p>@endif
                </div>

                <div class="mt-4 rounded-md bg-stone-50 px-3 py-2 text-xs text-stone-600">
                    <strong class="font-medium">Hors résultat, pour information :</strong> avances aux producteurs pas encore remboursées,
                    <span class="font-medium tabular-nums">{{ Format::fcfa($etat['info']['avances_non_remboursees']) }}</span>
                    (des créances, que le contrat ne range pas parmi les charges).
                </div>
            </div>

            <div class="rounded-xl border border-stone-200 bg-white p-5">
                <h2 class="mb-3 font-semibold">Partage (art. 12 et 13)</h2>

                @if ($refus)
                    <p class="rounded-md bg-stone-50 px-3 py-3 text-sm text-stone-600">{{ $refus }} Enregistrez d'abord les apports de la campagne.</p>
                @else
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between gap-4"><dt class="text-stone-600">Collecté auprès des investisseurs</dt><dd class="tabular-nums">{{ Format::fcfa($partage['collecte']) }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-stone-600">Apport propre de LY</dt><dd class="tabular-nums">{{ Format::fcfa($partage['apport_ly']) }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-stone-600">Fonds de la campagne</dt><dd class="tabular-nums">{{ Format::fcfa($partage['fonds']) }}</dd></div>
                        <div class="flex justify-between gap-4 border-t border-stone-200 pt-2">
                            <dt class="font-medium">{{ $partage['sens'] === PartageResultat::PERTE ? 'Part de perte des investisseurs' : 'Enveloppe investisseurs (40 %)' }}</dt>
                            <dd class="font-medium tabular-nums">{{ Format::fcfa($partage['part_investisseurs']) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="font-medium">{{ $partage['sens'] === PartageResultat::PERTE ? 'Part de perte de LY' : 'Part de LY (60 %)' }}</dt>
                            <dd class="font-medium tabular-nums">{{ Format::fcfa($partage['part_ly']) }}</dd>
                        </div>
                    </dl>

                    @if ($partage['sens'] === PartageResultat::PERTE)
                        <label class="mt-4 flex items-start gap-2 text-sm">
                            <input type="checkbox" wire:model.live="fauteLy" class="mt-1 h-4 w-4 rounded border-stone-300">
                            <span>Perte due à une faute de gestion, une fraude ou une utilisation non conforme des fonds (art. 13.4) : supportée par LY seule.
                                <span class="block text-xs text-stone-500">Décision de la direction, jamais déduite par le logiciel.</span></span>
                        </label>
                    @endif
                    @if ($partage['non_impute'] > 0)
                        <p class="mt-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-900">
                            La perte dépasse les fonds investis : <span class="font-medium tabular-nums">{{ Format::fcfa($partage['non_impute']) }}</span>
                            ne peuvent pas être imputés aux investisseurs (aucun ne doit d'argent). Le contrat ne dit pas qui les supporte.
                        </p>
                    @endif
                @endif
            </div>
        </div>

        @if (! $refus)
            <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white">
                <table class="w-full text-sm">
                    <thead class="bg-stone-50 text-left text-xs text-stone-500">
                        <tr>
                            <th class="px-4 py-2">Investisseur</th>
                            <th class="px-4 py-2 text-right">Investi</th>
                            <th class="px-4 py-2 text-right">{{ $partage['sens'] === PartageResultat::PERTE ? 'Part de perte' : 'Quote-part de bénéfice' }}</th>
                            <th class="px-4 py-2 text-right">Somme due (art. 12.3 / 13.3)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($partage['lignes'] as $id => $l)
                            <tr>
                                <td class="px-4 py-2">{{ $noms[$id] ?? 'Investisseur #'.$id }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ Format::fcfa($l['investi']) }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ $partage['sens'] === PartageResultat::PERTE ? '− ' : '' }}{{ Format::fcfa($l['part']) }}</td>
                                <td class="px-4 py-2 text-right font-medium tabular-nums">{{ Format::fcfa($l['somme_due']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif
</div>
