@php use App\Support\Format; @endphp

<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold">Rapport de campagne</h1>
            <p class="mt-1 text-sm text-stone-600">Point d'étape adressé aux investisseurs (contrat, art. 18.1 : au plus tard le 30 avril 2027).</p>
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

    @if ($campagne === null)
        <div class="rounded-xl border border-dashed border-stone-300 bg-white p-8 text-center">
            <p class="font-medium">Aucune campagne.</p>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-stone-200 bg-white p-4">
                <p class="text-xs text-stone-500">Fonds de la campagne</p>
                <p class="mt-1 text-lg font-semibold tabular-nums">{{ Format::fcfa($rapport['fonds']['total']) }}</p>
                <p class="mt-1 text-xs text-stone-500">{{ $rapport['fonds']['nb_investisseurs'] }} investisseur(s) : {{ Format::fcfa($rapport['fonds']['investisseurs']) }} · LY : {{ Format::fcfa($rapport['fonds']['ly']) }}</p>
            </div>
            <div class="rounded-xl border border-stone-200 bg-white p-4">
                <p class="text-xs text-stone-500">Acheté / vendu</p>
                <p class="mt-1 text-lg font-semibold tabular-nums">{{ Format::kg($rapport['volumes']['achete_g']) }} / {{ Format::kg($rapport['volumes']['vendu_g']) }}</p>
                <p class="mt-1 text-xs text-stone-500">En stock : {{ Format::kg($rapport['volumes']['stock_g']) }}</p>
            </div>
            <div class="rounded-xl border border-stone-200 bg-white p-4">
                <p class="text-xs text-stone-500">Achats et charges</p>
                <p class="mt-1 text-lg font-semibold tabular-nums">{{ Format::fcfa($rapport['engage']['achats'] + $rapport['engage']['total_depenses']) }}</p>
                <p class="mt-1 text-xs text-stone-500">Avances versées : {{ Format::fcfa($rapport['engage']['avances_decaissees']) }}</p>
            </div>
            <div class="rounded-xl border border-stone-200 bg-white p-4">
                <p class="text-xs text-stone-500">Trésorerie disponible</p>
                <p class="mt-1 text-lg font-semibold tabular-nums">{{ Format::fcfa($rapport['tresorerie']['total']) }}</p>
                <p class="mt-1 text-xs text-stone-500">Reste à encaisser : {{ Format::fcfa($rapport['ventes']['reste']) }}</p>
            </div>
        </div>

        <form method="POST" action="{{ route('rapport-campagne.point-etape') }}" target="_blank" class="rounded-xl border border-stone-200 bg-white p-5">
            @csrf
            <input type="hidden" name="campagne_id" value="{{ $campagne->id }}">
            <label for="evenements" class="block text-sm font-medium text-stone-700">Principaux événements (écrits par la direction)</label>
            <textarea wire:model.blur="evenements" name="evenements" id="evenements" rows="6" maxlength="{{ $maxEvenements }}"
                class="mt-1 w-full rounded-md border border-stone-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none"
                placeholder="Ex. démarrage des achats, changement du prix officiel, retard de livraison…"></textarea>
            <p class="mt-1 text-xs text-stone-500">Jamais rempli automatiquement. Laissé vide, le PDF indique « Aucun événement particulier signalé ».</p>

            <div class="mt-4 flex flex-wrap items-center gap-3">
                <button type="submit" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">Télécharger le PDF</button>
                <p class="text-xs text-stone-500">Les chiffres sont relus dans les registres à l'instant du téléchargement. Le PDF ne contient ni résultat, ni quote-part.</p>
            </div>
        </form>
    @endif
</div>
