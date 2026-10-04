@php use App\Support\Format; @endphp

<div class="space-y-6">
    <div>
        <h1 class="text-xl font-semibold">Vitrine : prix et actualités</h1>
        <p class="mt-1 max-w-2xl text-sm text-stone-600">
            Ce que le public voit sur la page d'accueil. <a href="{{ route('accueil') }}#prix" target="_blank" rel="noopener" class="text-emerald-800 underline">Voir la vitrine</a>
        </p>
    </div>

    <div class="flex gap-1 border-b border-stone-200 text-sm" role="tablist">
        <button type="button" role="tab" wire:click="$set('onglet', 'prix')" aria-selected="{{ $onglet === 'prix' ? 'true' : 'false' }}"
            class="-mb-px border-b-2 px-4 py-2 {{ $onglet === 'prix' ? 'border-emerald-700 font-medium text-emerald-900' : 'border-transparent text-stone-600 hover:text-stone-900' }}">Prix bord-champ</button>
        <button type="button" role="tab" wire:click="$set('onglet', 'actualites')" aria-selected="{{ $onglet === 'actualites' ? 'true' : 'false' }}"
            class="-mb-px border-b-2 px-4 py-2 {{ $onglet === 'actualites' ? 'border-emerald-700 font-medium text-emerald-900' : 'border-transparent text-stone-600 hover:text-stone-900' }}">Actualités</button>
        <button type="button" role="tab" wire:click="$set('onglet', 'sources')" aria-selected="{{ $onglet === 'sources' ? 'true' : 'false' }}"
            class="-mb-px border-b-2 px-4 py-2 {{ $onglet === 'sources' ? 'border-emerald-700 font-medium text-emerald-900' : 'border-transparent text-stone-600 hover:text-stone-900' }}">Sources d'actualités</button>
    </div>

    @if ($statut !== '')
        <p class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-900">{{ $statut }}</p>
    @endif

    @if ($onglet === 'prix')
        <p class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <strong class="font-semibold">Information, pas le prix officiel.</strong> Un prix publié ici s'affiche sur la vitrine avec sa source et sa date.
            Il ne change rien aux achats : le plancher d'achat reste le prix officiel de la campagne (Référentiels › Campagnes).
            Un prix ne se corrige pas : on en publie un nouveau, l'ancien reste dans l'historique.
        </p>

        <form wire:submit="publierPrix" class="rounded-xl border border-stone-200 bg-white p-5">
            <h2 class="mb-3 font-semibold">Publier un prix</h2>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label for="produitId" class="mb-1 block text-sm text-stone-700">Produit</label>
                    <select wire:model.live="produitId" id="produitId" class="w-full rounded-md border border-stone-300 px-3 py-2">
                        <option value="">— Choisir —</option>
                        @foreach ($produits as $p)
                            <option value="{{ $p->id }}">{{ $p->nom }}</option>
                        @endforeach
                    </select>
                    @error('produitId') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    @if ($produits->isEmpty())
                        <p class="mt-1 text-xs text-stone-500">Aucun produit. Ajoutez le café, le cacao… dans Référentiels › Produits.</p>
                    @endif
                </div>
                <div>
                    <label for="prixKg" class="mb-1 block text-sm text-stone-700">Prix (FCFA / kg)</label>
                    <input wire:model="prixKg" id="prixKg" type="text" inputmode="numeric" class="w-full rounded-md border border-stone-300 px-3 py-2 tabular-nums">
                    @error('prixKg') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="dateEffet" class="mb-1 block text-sm text-stone-700">Date d'effet</label>
                    <input wire:model="dateEffet" id="dateEffet" type="date" class="w-full rounded-md border border-stone-300 px-3 py-2">
                    @error('dateEffet') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="source" class="mb-1 block text-sm text-stone-700">Source (obligatoire)</label>
                    <input wire:model="source" id="source" type="text" placeholder="Ex. communiqué du 1er octobre" class="w-full rounded-md border border-stone-300 px-3 py-2">
                    @error('source') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label for="sourceUrl" class="mb-1 block text-sm text-stone-700">Lien de la source (facultatif)</label>
                    <input wire:model="sourceUrl" id="sourceUrl" type="url" placeholder="https://…" class="w-full rounded-md border border-stone-300 px-3 py-2">
                    @error('sourceUrl') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label for="note" class="mb-1 block text-sm text-stone-700">Note (facultatif)</label>
                    <input wire:model="note" id="note" type="text" class="w-full rounded-md border border-stone-300 px-3 py-2">
                </div>
            </div>
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <button type="submit" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">Publier ce prix</button>
                @if ($produitChoisi)
                    <button type="button" wire:click="reprendrePrixCampagne" class="text-sm text-emerald-800 underline">Reprendre le prix officiel de la campagne ouverte</button>
                @endif
            </div>
        </form>

        <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white">
            <table class="w-full text-sm">
                <thead class="bg-stone-50 text-left text-xs text-stone-500">
                    <tr><th class="px-4 py-2">Produit</th><th class="px-4 py-2 text-right">Prix en vigueur</th><th class="px-4 py-2 text-right">Écart</th><th class="px-4 py-2">Date d'effet</th><th class="px-4 py-2">Source</th></tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse ($courants as $c)
                        <tr>
                            <td class="px-4 py-2 font-medium">{{ $c['produit']->nom }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ Format::fcfa($c['prix']->prix_kg_fcfa) }} / kg</td>
                            <td class="px-4 py-2 text-right tabular-nums {{ ($c['ecart'] ?? 0) > 0 ? 'text-emerald-700' : (($c['ecart'] ?? 0) < 0 ? 'text-red-700' : 'text-stone-400') }}">
                                {{ $c['ecart'] === null ? '—' : (($c['ecart'] > 0 ? '+' : '').Format::entier($c['ecart'])) }}
                            </td>
                            <td class="px-4 py-2 tabular-nums">{{ $c['prix']->date_effet->format('d/m/Y') }}</td>
                            <td class="px-4 py-2 text-stone-600">{{ $c['prix']->source }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-stone-500">Aucun prix publié : la vitrine affiche « Aucun prix publié pour le moment ».</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($produitChoisi && $historique->isNotEmpty())
            <div class="rounded-xl border border-stone-200 bg-white p-5">
                <h2 class="mb-2 font-semibold">Historique — {{ $produitChoisi->nom }}</h2>
                <ul class="divide-y divide-stone-100 text-sm">
                    @foreach ($historique as $h)
                        <li class="flex flex-wrap items-baseline justify-between gap-2 py-2">
                            <span class="tabular-nums">{{ $h->date_effet->format('d/m/Y') }} — <strong class="font-medium">{{ Format::fcfa($h->prix_kg_fcfa) }} / kg</strong></span>
                            <span class="text-xs text-stone-500">{{ $h->source }} · saisi par {{ $h->auteur->nom }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    @else
        <div class="flex items-center justify-between">
            <p class="text-sm text-stone-600">Un brouillon ne s'affiche pas sur la vitrine. Le texte est simple : pas de mise en forme.</p>
            @if (! $formulaireActualite)
                <button type="button" wire:click="nouvelleActualite" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">Nouvelle actualité</button>
            @endif
        </div>

        @if ($formulaireActualite)
            <form wire:submit="enregistrerActualite" class="space-y-3 rounded-xl border border-stone-200 bg-white p-5">
                <div>
                    <label for="titre" class="mb-1 block text-sm text-stone-700">Titre</label>
                    <input wire:model="titre" id="titre" type="text" maxlength="200" class="w-full rounded-md border border-stone-300 px-3 py-2">
                    @error('titre') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="contenu" class="mb-1 block text-sm text-stone-700">Texte</label>
                    <textarea wire:model="contenu" id="contenu" rows="8" class="w-full rounded-md border border-stone-300 px-3 py-2"></textarea>
                    @error('contenu') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="publie" class="h-4 w-4 rounded border-stone-300"> Publier sur la vitrine</label>
                <div class="flex gap-2">
                    <button type="submit" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">Enregistrer</button>
                    <button type="button" wire:click="annulerActualite" class="rounded-md px-3 py-2 text-sm text-stone-600 underline">Annuler</button>
                </div>
            </form>
        @endif

        <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white">
            <table class="w-full text-sm">
                <thead class="bg-stone-50 text-left text-xs text-stone-500">
                    <tr><th class="px-4 py-2">Titre</th><th class="px-4 py-2">État</th><th class="px-4 py-2">Date</th><th class="px-4 py-2"></th></tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse ($actualites as $a)
                        <tr wire:key="actualite-{{ $a->id }}">
                            <td class="px-4 py-2">{{ $a->titre }}
                                @if ($a->origine === 'externe')<span class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800">À relire · {{ $a->source_nom }}</span>@endif
                            </td>
                            <td class="px-4 py-2 text-xs">
                                @if ($a->publie)<span class="rounded-full bg-emerald-100 px-2 py-0.5 text-emerald-800">Publiée</span>@else<span class="rounded-full bg-stone-100 px-2 py-0.5 text-stone-700">Brouillon</span>@endif
                            </td>
                            <td class="px-4 py-2 tabular-nums">{{ $a->publie_le?->format('d/m/Y') ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-right text-xs">
                                <button type="button" wire:click="modifierActualite({{ $a->id }})" class="text-emerald-800 underline">Modifier</button>
                                <button type="button" wire:click="basculerPublication({{ $a->id }})" class="ml-2 text-stone-600 underline">{{ $a->publie ? 'Retirer' : 'Publier' }}</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-6 text-center text-stone-500">Aucune actualité.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    @if ($onglet === 'sources')
        <div class="space-y-4">
            <p class="text-sm text-stone-600">Flux RSS ou Atom de sites d'actualités agricoles. Ce qui est récupéré (titre, court extrait, lien vers l'article) arrive en <strong>brouillon « à relire »</strong> dans l'onglet Actualités : rien n'est public sans votre publication. Les prix ne sont pas récupérés ici : ils se saisissent avec leur source.</p>

            <form wire:submit="ajouterSource" class="grid gap-3 rounded-xl border border-stone-200 bg-white p-5 sm:grid-cols-[1fr_2fr_auto] sm:items-end">
                <div>
                    <label for="source-nom" class="mb-1 block text-sm text-stone-700">Nom de la source</label>
                    <input wire:model="fluxNom" id="source-nom" type="text" maxlength="150" class="w-full rounded-md border border-stone-300 px-3 py-2">
                    @error('fluxNom') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="source-url" class="mb-1 block text-sm text-stone-700">Adresse du flux (https://…)</label>
                    <input wire:model="fluxUrl" id="source-url" type="url" maxlength="500" class="w-full rounded-md border border-stone-300 px-3 py-2">
                    @error('fluxUrl') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">Ajouter</button>
            </form>

            <div class="flex justify-end">
                <button type="button" wire:click="recupererTout" wire:loading.attr="disabled" class="rounded-md border border-emerald-700 px-4 py-2 text-sm font-medium text-emerald-800 hover:bg-emerald-50">Récupérer maintenant</button>
            </div>

            <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white">
                <table class="w-full text-sm">
                    <thead class="bg-stone-50 text-left text-xs text-stone-500">
                        <tr><th class="px-4 py-2">Source</th><th class="px-4 py-2">Dernière récupération</th><th class="px-4 py-2">État</th><th class="px-4 py-2"></th></tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @forelse ($sources as $s)
                            <tr wire:key="source-{{ $s->id }}">
                                <td class="px-4 py-2">{{ $s->nom }}<div class="break-all text-xs text-stone-500">{{ $s->url }}</div></td>
                                <td class="px-4 py-2 tabular-nums">{{ $s->derniere_recuperation_at?->format('d/m/Y H:i') ?? 'jamais' }}@if ($s->dernier_statut === 'ok') <span class="text-xs text-stone-500">· {{ $s->dernier_nb }} nouveau(x)</span>@endif</td>
                                <td class="px-4 py-2 text-xs">
                                    @if ($s->dernier_statut === 'erreur')<span class="rounded-full bg-red-100 px-2 py-0.5 text-red-800">{{ $s->dernier_message }}</span>
                                    @elseif (! $s->actif)<span class="rounded-full bg-stone-100 px-2 py-0.5 text-stone-700">Désactivée</span>
                                    @else<span class="rounded-full bg-emerald-100 px-2 py-0.5 text-emerald-800">Active</span>@endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-2 text-right text-xs">
                                    <button type="button" wire:click="recupererSource({{ $s->id }})" class="text-emerald-800 underline">Récupérer</button>
                                    <button type="button" wire:click="basculerSource({{ $s->id }})" class="ml-2 text-stone-600 underline">{{ $s->actif ? 'Désactiver' : 'Réactiver' }}</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-6 text-center text-stone-500">Aucune source. Ajoutez l'adresse d'un flux RSS.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
