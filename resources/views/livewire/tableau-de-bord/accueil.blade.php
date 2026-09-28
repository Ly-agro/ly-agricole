@php
    use App\Support\Format;

    $precedente = $campagne
        ? $campagnes->first(fn ($c) => $c->produit_id === $campagne->produit_id && $c->debut < $campagne->debut && $c->prix_officiel_kg_fcfa !== null)
        : null;
    $variation = $campagne && $precedente && $campagne->prix_officiel_kg_fcfa !== null
        ? $campagne->prix_officiel_kg_fcfa - $precedente->prix_officiel_kg_fcfa
        : null;
    $prixHistorique = $campagne
        ? $campagnes->where('produit_id', $campagne->produit_id)->whereNotNull('prix_officiel_kg_fcfa')->sortBy('debut')->values()
            ->map(fn ($c) => ['label' => $c->code, 'valeur' => $c->prix_officiel_kg_fcfa])
        : collect();
    $genres = ['pret' => 'Prêt', 'versement' => 'Versement', 'depense' => 'Dépense', 'producteur' => 'Terrain'];
@endphp

<div class="space-y-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm text-stone-500">{{ now()->locale('fr')->isoFormat('dddd D MMMM YYYY') }}</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight">Bonjour, {{ auth()->user()->nom }}</h1>
        </div>

        @if ($campagnes->isNotEmpty())
            <div class="text-sm">
                <label for="choixCampagne" class="mb-1 block text-stone-600">Campagne affichée</label>
                <select wire:model.live="choixCampagne" id="choixCampagne"
                    class="rounded-lg border border-stone-300 bg-white px-3 py-2 focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700/20">
                    @foreach ($campagnes as $c)
                        <option value="{{ $c->id }}" @selected($campagne?->id === $c->id)>{{ $c->produit->nom }} · {{ $c->code }}</option>
                    @endforeach
                </select>
            </div>
        @endif
    </div>

    @if (auth()->user()->role === null)
        <p class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Aucun rôle ne vous est attribué : vous n'avez accès à aucun écran. Contactez l'administrateur.
        </p>
    @endif

    @if ($campagne === null)
        <div class="rounded-xl border border-dashed border-stone-300 bg-white p-8 text-center">
            <p class="font-medium">Aucune campagne n'est encore créée.</p>
            <p class="mt-1 text-sm text-stone-600">Le prix bord-champ, les prêts et les dépenses s'affichent dès qu'une campagne existe.</p>
            @can('gerer-campagnes')
                <a href="{{ route('referentiels.campagnes') }}" class="mt-4 inline-block rounded-lg bg-emerald-800 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-900">Créer une campagne</a>
            @endcan
        </div>
    @else
        {{-- Prix bord-champ + chiffres clés --}}
        <section class="grid gap-4 lg:grid-cols-3" aria-label="Prix et chiffres clés">
            <div class="rounded-xl bg-emerald-900 p-6 text-white">
                <p class="text-sm text-emerald-100">Prix officiel bord-champ · {{ $campagne->produit->nom }}</p>
                @if ($campagne->prix_officiel_kg_fcfa === null)
                    <p class="mt-3 text-2xl font-semibold">Pas encore annoncé</p>
                    <p class="mt-1 text-sm text-emerald-100">Campagne {{ $campagne->code }} · {{ $campagne->statut->libelle() }}</p>
                @else
                    <p class="mt-3 text-4xl font-semibold tabular-nums">{{ number_format($campagne->prix_officiel_kg_fcfa, 0, ',', "\u{202F}") }} <span class="text-lg font-normal text-emerald-100">FCFA / kg</span></p>
                    <p class="mt-2 text-sm text-emerald-100">
                        Campagne {{ $campagne->code }} · {{ $campagne->statut->libelle() }}
                        @if ($variation !== null)
                            <br>{{ $variation === 0 ? 'Identique' : ($variation > 0 ? '+' : '−').Format::fcfa(abs($variation)) }} par rapport à {{ $precedente->code }}
                        @endif
                    </p>
                @endif
                <p class="mt-4 border-t border-emerald-700 pt-3 text-xs text-emerald-100">
                    Du {{ $campagne->debut->locale('fr')->isoFormat('D MMM YYYY') }} au {{ $campagne->fin->locale('fr')->isoFormat('D MMM YYYY') }}
                </p>
            </div>

            <dl class="grid grid-cols-2 gap-4 lg:col-span-2">
                @if ($chiffres !== null)
                    @if ($voitPrets)
                        <div class="rounded-xl border border-stone-200 bg-white p-4">
                            <dt class="text-sm text-stone-600">Prêts accordés</dt>
                            <dd class="mt-1 text-xl font-semibold tabular-nums">{{ Format::fcfa($chiffres['accordes']) }}</dd>
                            <dd class="mt-0.5 text-xs text-stone-500">{{ $chiffres['nombre'] }} {{ $chiffres['nombre'] > 1 ? 'prêts' : 'prêt' }} · {{ $chiffres['producteurs'] }} {{ $chiffres['producteurs'] > 1 ? 'producteurs' : 'producteur' }}</dd>
                        </div>
                        <div class="rounded-xl border border-stone-200 bg-white p-4">
                            <dt class="text-sm text-stone-600">Déjà remis aux producteurs</dt>
                            <dd class="mt-1 text-xl font-semibold tabular-nums">{{ Format::fcfa($chiffres['remis']) }}</dd>
                            <dd class="mt-0.5 text-xs text-stone-500">Argent versé + valeur des intrants</dd>
                        </div>
                    @endif
                    @if ($voitDepenses)
                        <div class="rounded-xl border border-stone-200 bg-white p-4">
                            <dt class="text-sm text-stone-600">Dépenses payées</dt>
                            <dd class="mt-1 text-xl font-semibold tabular-nums">{{ Format::fcfa($chiffres['depenses']) }}</dd>
                            <dd class="mt-0.5 text-xs text-stone-500">Rattachées à cette campagne</dd>
                        </div>
                    @endif
                @endif
                @if ($tresorerie !== null)
                    <div class="rounded-xl border border-stone-200 bg-white p-4">
                        <dt class="text-sm text-stone-600">Trésorerie disponible</dt>
                        <dd class="mt-1 text-xl font-semibold tabular-nums">{{ Format::fcfa($tresorerie) }}</dd>
                        <dd class="mt-0.5 text-xs text-stone-500">Tous comptes actifs, à ce jour</dd>
                    </div>
                @endif
            </dl>
        </section>

        {{-- Argent : flux et revenus --}}
        @if ($voitArgent)
            <section class="grid gap-4 lg:grid-cols-3" aria-label="Flux d'argent">
                <div class="rounded-xl border border-stone-200 bg-white p-5 lg:col-span-2">
                    <div class="mb-4 flex flex-wrap items-baseline justify-between gap-2">
                        <h2 class="font-semibold">Entrées et sorties d'argent</h2>
                        <div class="flex items-center gap-4 text-xs text-stone-600">
                            <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-emerald-700"></span>Entrées</span>
                            <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-amber-500"></span>Sorties</span>
                        </div>
                    </div>
                    <x-graphiques.flux :serie="$flux" />
                    <p class="mt-2 text-xs text-stone-500">6 derniers mois, virements entre comptes exclus.</p>
                </div>

                <div class="rounded-xl border border-stone-200 bg-white p-5">
                    <h2 class="font-semibold">Revenus</h2>
                    <p class="mt-1 text-xs text-stone-500">Entrées d'argent sur 6 mois, par origine.</p>
                    <div class="mt-4">
                        <x-graphiques.barres :lignes="$entrees" format="fcfa" vide="Aucune entrée d'argent enregistrée." />
                    </div>
                    <p class="mt-4 border-t border-stone-100 pt-3 text-xs text-stone-500">
                        Les revenus de vente s'ajouteront ici avec le module des reventes.
                    </p>
                </div>
            </section>
        @endif

        {{-- Analyses de la campagne --}}
        <section class="grid gap-4 lg:grid-cols-3" aria-label="Analyses de la campagne">
            @if ($voitDepenses)
                <div class="rounded-xl border border-stone-200 bg-white p-5">
                    <h2 class="font-semibold">Où part l'argent</h2>
                    <p class="mt-1 mb-4 text-xs text-stone-500">Dépenses payées · {{ $campagne->code }}</p>
                    <x-graphiques.anneau :lignes="$depensesParCategorie" vide="Aucune dépense payée sur cette campagne." />
                </div>
            @endif

            @if ($voitPrets)
                <div class="rounded-xl border border-stone-200 bg-white p-5">
                    <h2 class="font-semibold">Avancement des prêts</h2>
                    <p class="mt-1 mb-4 text-xs text-stone-500">Nombre de prêts par étape · {{ $campagne->code }}</p>
                    <x-graphiques.barres :lignes="$pretsParStatut" vide="Aucun prêt sur cette campagne." />
                </div>
            @endif

            <div class="rounded-xl border border-stone-200 bg-white p-5">
                <h2 class="font-semibold">Évolution du prix bord-champ</h2>
                <p class="mt-1 mb-4 text-xs text-stone-500">{{ $campagne->produit->nom }}, par campagne</p>
                <x-graphiques.barres :lignes="$prixHistorique" format="fcfa" vide="Aucun prix annoncé pour ce produit." />
            </div>
        </section>

        {{-- À faire + actualité --}}
        <section class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-xl border border-stone-200 bg-white p-5">
                <h2 class="font-semibold">Actions à mener</h2>
                @if (count($aFaire) === 0)
                    <p class="mt-3 text-sm text-stone-600">Rien n'attend votre action pour le moment.</p>
                @else
                    <ul class="mt-3 divide-y divide-stone-100">
                        @foreach ($aFaire as $tache)
                            <li>
                                <a href="{{ $tache['lien'] }}" class="flex items-center justify-between gap-4 py-3 text-sm hover:text-emerald-800">
                                    <span class="flex items-center gap-3">
                                        <span class="flex h-7 min-w-7 items-center justify-center rounded-full bg-amber-100 px-2 text-xs font-semibold text-amber-900">{{ $tache['nombre'] }}</span>
                                        {{ $tache['texte'] }}
                                    </span>
                                    <span aria-hidden="true" class="text-stone-400">→</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="rounded-xl border border-stone-200 bg-white p-5">
                <h2 class="font-semibold">Dernières nouvelles</h2>
                @if ($actualite->isEmpty())
                    <p class="mt-3 text-sm text-stone-600">Rien d'enregistré pour l'instant.</p>
                @else
                    <ul class="mt-3 divide-y divide-stone-100">
                        @foreach ($actualite as $fait)
                            <li class="py-3 text-sm">
                                <a href="{{ $fait['lien'] }}" class="flex items-start justify-between gap-4 hover:text-emerald-800">
                                    <span class="min-w-0">
                                        <span class="block font-medium">{{ $fait['texte'] }}</span>
                                        <span class="block truncate text-stone-600">{{ $fait['detail'] }}</span>
                                    </span>
                                    <span class="shrink-0 text-right text-xs text-stone-500">
                                        {{ $genres[$fait['genre']] }}<br>{{ $fait['quand']->locale('fr')->diffForHumans() }}
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>

        {{-- Bilan par campagne --}}
        @if ($bilan->isNotEmpty())
            <section class="rounded-xl border border-stone-200 bg-white p-5" aria-label="Bilan par campagne">
                <h2 class="font-semibold">Ce qui a été fait, campagne par campagne</h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-[40rem] text-sm">
                        <thead>
                            <tr class="border-b border-stone-200 text-left text-stone-600">
                                <th class="pb-2 font-medium">Campagne</th>
                                <th class="pb-2 text-right font-medium">Producteurs</th>
                                <th class="pb-2 text-right font-medium">Prêts accordés</th>
                                <th class="pb-2 text-right font-medium">Remis</th>
                                <th class="pb-2 text-right font-medium">Dépenses</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            @foreach ($bilan as $ligne)
                                <tr @class(['bg-emerald-50/60' => $ligne['campagne']->id === $campagne->id])>
                                    <td class="py-3">
                                        <span class="font-medium">{{ $ligne['campagne']->produit->nom }} · {{ $ligne['campagne']->code }}</span>
                                        <span class="ml-2 text-xs text-stone-500">{{ $ligne['campagne']->statut->libelle() }}</span>
                                    </td>
                                    <td class="py-3 text-right tabular-nums">{{ $ligne['producteurs'] }}</td>
                                    <td class="py-3 text-right tabular-nums">{{ Format::fcfa($ligne['accordes']) }} <span class="text-xs text-stone-500">({{ $ligne['prets'] }})</span></td>
                                    <td class="py-3 text-right tabular-nums">{{ Format::fcfa($ligne['remis']) }}</td>
                                    <td class="py-3 text-right tabular-nums">{{ Format::fcfa($ligne['depenses']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        {{-- Ce qui viendra --}}
        <section aria-label="Modules à venir">
            <h2 class="mb-3 text-sm font-medium text-stone-600">Bientôt sur cette page</h2>
            <ul class="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    'Kilos achetés' => 'Achats bord-champ, par lot',
                    'Stock en magasin' => 'Poids et valeur, pertes',
                    'Remboursements' => 'En argent ou en kilos livrés',
                    'Marge par lot' => 'Après reventes et frais',
                ] as $titre => $aide)
                    <li class="rounded-xl border border-dashed border-stone-300 p-4">
                        <p class="font-medium text-stone-700">{{ $titre }}</p>
                        <p class="mt-0.5 text-xs text-stone-500">{{ $aide }}</p>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
