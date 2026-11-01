@php use App\Support\Format; @endphp

<div class="space-y-6">
    <div>
        <h1 class="text-xl font-semibold">Mon investissement</h1>
        <p class="mt-1 text-sm text-stone-600">Vos apports confirmés et vos déclarations de virement, en attente de confirmation du bureau.</p>
    </div>

    @if ($statutDeclaration !== '')
        <p class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-900">{{ $statutDeclaration }}</p>
    @endif

    @if ($campagnesOuvertes->isNotEmpty())
        <div>
            @if (! $formulaireDeclaration)
                <button type="button" wire:click="ouvrirDeclaration" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">Déclarer un virement</button>
            @else
                <form wire:submit="declarerApport" class="max-w-2xl space-y-4 rounded-md border border-stone-200 bg-white p-5">
                    <h2 class="font-semibold">Déclarer un apport</h2>
                    <div>
                        <label for="campagneDeclarationId" class="mb-1 block text-sm font-medium text-stone-700">Campagne</label>
                        <select wire:model="campagneDeclarationId" id="campagneDeclarationId" class="block w-full rounded-md border border-stone-300 px-3 py-2">
                            @foreach ($campagnesOuvertes as $campagneOuverte)
                                <option value="{{ $campagneOuverte->id }}">{{ $campagneOuverte->produit->nom }} · {{ $campagneOuverte->code }}</option>
                            @endforeach
                        </select>
                        @error('campagneDeclarationId') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="montantDeclaration" class="mb-1 block text-sm font-medium text-stone-700">Montant du virement (FCFA)</label>
                            <input wire:model="montantDeclaration" id="montantDeclaration" type="text" inputmode="numeric" placeholder="3 000 000" class="block w-full rounded-md border border-stone-300 px-3 py-2">
                            @error('montantDeclaration') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="dateVirement" class="mb-1 block text-sm font-medium text-stone-700">Date du virement</label>
                            <input wire:model="dateVirement" id="dateVirement" type="date" class="block w-full rounded-md border border-stone-300 px-3 py-2">
                            @error('dateVirement') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div>
                        <label for="referenceVirement" class="mb-1 block text-sm font-medium text-stone-700">Référence du virement <span class="font-normal text-stone-500">(facultatif)</span></label>
                        <input wire:model="referenceVirement" id="referenceVirement" type="text" maxlength="150" class="block w-full rounded-md border border-stone-300 px-3 py-2">
                        @error('referenceVirement') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <p class="text-xs text-stone-500">La déclaration ne sera comptabilisée qu'après confirmation de la direction ou de la comptabilité.</p>
                    <div class="flex justify-end gap-3">
                        <button type="button" wire:click="$set('formulaireDeclaration', false)" class="px-3 py-2 text-sm text-stone-600 hover:underline">Annuler</button>
                        <button type="submit" wire:loading.attr="disabled" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:opacity-60">Envoyer la déclaration</button>
                    </div>
                </form>
            @endif
        </div>
    @endif

    @if ($declarations->isNotEmpty())
        <section class="overflow-x-auto rounded-md border border-stone-200 bg-white">
            <h2 class="px-4 pt-4 font-semibold">Mes déclarations de virement</h2>
            <table class="mt-3 min-w-full text-sm">
                <thead class="bg-stone-50 text-left text-stone-600">
                    <tr><th class="px-4 py-3 font-medium">Campagne</th><th class="px-4 py-3 font-medium">Date</th><th class="px-4 py-3 text-right font-medium">Montant</th><th class="px-4 py-3 font-medium">Référence</th><th class="px-4 py-3 font-medium">Statut</th></tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @foreach ($declarations as $declaration)
                        <tr wire:key="declaration-{{ $declaration->id }}">
                            <td class="px-4 py-3">{{ $declaration->campagne->produit->nom }} · {{ $declaration->campagne->code }}</td>
                            <td class="whitespace-nowrap px-4 py-3">{{ $declaration->date_virement->format('d/m/Y') }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right font-medium tabular-nums">{{ Format::fcfa($declaration->montant_fcfa) }}</td>
                            <td class="px-4 py-3">{{ $declaration->reference_virement ?: '—' }}</td>
                            <td class="px-4 py-3">
                                {{ $declaration->statut->libelle() }}
                                @if ($declaration->motif_refus) <span class="block text-xs text-stone-500">{{ $declaration->motif_refus }}</span> @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endif

    @if ($lignes->isEmpty())
        <div class="rounded-xl border border-dashed border-stone-300 bg-white p-8 text-center">
            <p class="font-medium">Aucun apport confirmé à votre nom pour l'instant.</p>
            @if (! $declarationsEnAttente)
                <p class="mt-1 text-sm text-stone-600">Contactez la direction si vous pensez que c'est une erreur.</p>
            @endif
        </div>
    @endif

    @foreach ($lignes as $ligne)
        <div class="rounded-xl border border-stone-200 bg-white p-5">
            <div class="mb-4 flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="font-semibold">{{ $ligne['campagne']->produit->nom }} · {{ $ligne['campagne']->code }}</h2>
                <span class="text-xs text-stone-500">{{ $ligne['campagne']->statut->libelle() }}</span>
            </div>

            <dl class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                <div>
                    <dt class="text-xs text-stone-500">Votre apport</dt>
                    <dd class="mt-1 text-lg font-semibold tabular-nums">{{ Format::fcfa($ligne['monApport']) }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-stone-500">Votre part des apports d'investisseurs</dt>
                    <dd class="mt-1 text-lg font-semibold tabular-nums">
                        {{ intdiv($ligne['partPourMille'], 10) }},{{ str_pad((string) ($ligne['partPourMille'] % 10), 1, '0', STR_PAD_LEFT) }} %
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-stone-500">Total des apports d'investisseurs</dt>
                    <dd class="mt-1 text-lg font-semibold tabular-nums">{{ Format::fcfa($ligne['totalInvestisseurs']) }}</dd>
                </div>
            </dl>

            <p class="mt-4 rounded-md bg-stone-50 px-3 py-2 text-xs text-stone-500">
                Ceci est votre part de l'ensemble des apports versés par les investisseurs sur cette campagne — pas
                le calcul de votre quote-part du résultat, qui dépend du contrat de campagne (art. 10 à 14) et n'est
                pas encore disponible sur cette page.
            </p>

            <h3 class="mt-5 mb-2 text-sm font-medium text-stone-700">Détail de vos apports</h3>
            <ul class="divide-y divide-stone-100 text-sm">
                @foreach ($ligne['mesApports'] as $a)
                    <li class="flex items-center justify-between gap-4 py-2">
                        <span class="text-stone-600">{{ $a->date_apport->format('d/m/Y') }}</span>
                        <span class="font-medium tabular-nums {{ $a->montant_fcfa < 0 ? 'text-red-700' : '' }}">{{ Format::fcfa($a->montant_fcfa) }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach
</div>
