@php use App\Support\Format; @endphp

<div class="space-y-6">
    <div>
        <h1 class="text-xl font-semibold">Mon investissement</h1>
        <p class="mt-1 text-sm text-stone-600">Vos apports et votre part de chaque campagne, en lecture seule.</p>
    </div>

    @if ($lignes->isEmpty())
        <div class="rounded-xl border border-dashed border-stone-300 bg-white p-8 text-center">
            <p class="font-medium">Aucun apport enregistré à votre nom pour l'instant.</p>
            <p class="mt-1 text-sm text-stone-600">Contactez la direction si vous pensez que c'est une erreur.</p>
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
