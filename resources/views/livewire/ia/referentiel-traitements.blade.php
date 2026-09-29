<div class="mx-auto max-w-5xl">
    <div class="mb-5 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold">Référentiel des traitements</h1>
            <p class="mt-1 max-w-2xl text-sm text-stone-600">Seule source des produits et des doses : l'IA ne cite que ces fiches, par leur repère, et ne voit jamais un nom commercial ni une dose. Tenu et daté par l'agronome.</p>
        </div>
        <div class="flex flex-wrap items-end gap-3 text-sm">
            <div>
                <label for="produitId" class="mb-1 block text-stone-600">Culture</label>
                <select wire:model.live="produitId" id="produitId" class="rounded-md border border-stone-300 px-3 py-1.5 focus:border-emerald-600 focus:outline-none">
                    <option value="">Toutes</option>
                    @foreach ($produits as $p)
                        <option value="{{ $p->id }}">{{ $p->nom }}</option>
                    @endforeach
                </select>
            </div>
            @if ($peutModifier)
                <button type="button" wire:click="ouvrir" class="rounded-lg bg-emerald-700 px-4 py-2 font-medium text-white hover:bg-emerald-800">Nouvelle fiche</button>
            @endif
        </div>
    </div>

    @if ($statut !== '')
        <p class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-900">{{ $statut }}</p>
    @endif

    @if ($formulaire)
        <form wire:submit="enregistrer" class="mb-6 space-y-4 rounded-xl border border-stone-200 bg-white p-5 shadow-sm">
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label for="c-produit" class="mb-1 block text-sm font-medium text-stone-700">Culture</label>
                    <select wire:model="champs.produit_id" id="c-produit" class="block w-full rounded-md border border-stone-300 px-3 py-2">
                        <option value="">—</option>
                        @foreach ($produits as $p)<option value="{{ $p->id }}">{{ $p->nom }}</option>@endforeach
                    </select>
                    @error('champs.produit_id') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="c-type" class="mb-1 block text-sm font-medium text-stone-700">Type</label>
                    <select wire:model.live="champs.type" id="c-type" class="block w-full rounded-md border border-stone-300 px-3 py-2">
                        @foreach (\App\Models\FicheTraitement::TYPES as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label for="c-statut" class="mb-1 block text-sm font-medium text-stone-700">Statut</label>
                    <select wire:model="champs.statut" id="c-statut" class="block w-full rounded-md border border-stone-300 px-3 py-2">
                        @foreach (\App\Models\FicheTraitement::STATUTS as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
                    </select>
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="c-cible" class="mb-1 block text-sm font-medium text-stone-700">Cible (maladie, ravageur ou « général »)</label>
                    <input wire:model="champs.cible" id="c-cible" type="text" class="block w-full rounded-md border border-stone-300 px-3 py-2">
                    @error('champs.cible') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="c-titre" class="mb-1 block text-sm font-medium text-stone-700">Intitulé (sans nom commercial ni dose)</label>
                    <input wire:model="champs.titre" id="c-titre" type="text" class="block w-full rounded-md border border-stone-300 px-3 py-2">
                    @error('champs.titre') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label for="c-description" class="mb-1 block text-sm font-medium text-stone-700">Description (vue par l'IA : pas de nom commercial ni de dose)</label>
                <textarea wire:model="champs.description" id="c-description" rows="2" class="block w-full rounded-md border border-stone-300 px-3 py-2"></textarea>
            </div>

            @if (($champs['type'] ?? '') === 'chimique')
                <fieldset class="rounded-lg border border-amber-300 bg-amber-50/60 p-4">
                    <legend class="px-1 text-sm font-medium text-amber-900">Produit chimique : tout est obligatoire, sinon la fiche n'est pas proposable</legend>
                    <div class="mt-2 grid gap-3 sm:grid-cols-2">
                        @foreach (\App\Models\FicheTraitement::CHAMPS_CHIMIQUE as $cle => $libelle)
                            <div>
                                <label for="c-{{ $cle }}" class="mb-1 block text-sm text-stone-700">{{ ucfirst($libelle) }}</label>
                                <input wire:model="champs.{{ $cle }}" id="c-{{ $cle }}" type="text" class="block w-full rounded-md border border-stone-300 bg-white px-3 py-2">
                                @error('champs.'.$cle) <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                            </div>
                        @endforeach
                        <div>
                            <label for="c-homologation" class="mb-1 block text-sm text-stone-700">Référence d'homologation (Côte d'Ivoire)</label>
                            <input wire:model="champs.reference_homologation" id="c-homologation" type="text" class="block w-full rounded-md border border-stone-300 bg-white px-3 py-2">
                        </div>
                    </div>
                </fieldset>
            @endif

            <div class="flex justify-end gap-3">
                <button type="button" wire:click="$set('formulaire', false)" class="text-sm text-stone-600 hover:underline">Annuler</button>
                <button type="submit" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">Enregistrer et dater</button>
            </div>
        </form>
    @endif

    @if ($fiches->isEmpty())
        <div class="rounded-xl border border-dashed border-stone-300 bg-white/60 px-6 py-10 text-center">
            <p class="font-medium text-stone-800">Aucune fiche</p>
            <p class="mx-auto mt-1 max-w-xl text-sm text-stone-500">Le référentiel est rempli par l'agronome, à partir des homologations en vigueur en Côte d'Ivoire. Tant qu'il est vide, l'IA ne peut recommander aucun traitement : ses brouillons renvoient à l'avis de l'agronome.</p>
        </div>
    @else
        <ul class="overflow-hidden rounded-xl border border-stone-200 bg-white shadow-sm">
            @foreach ($fiches as $f)
                <li wire:key="fiche-{{ $f->id }}" class="flex flex-wrap items-start gap-3 border-t border-stone-100 px-4 py-3 first:border-t-0">
                    <span class="w-20 shrink-0 font-mono text-xs text-stone-500">FICHE-{{ $f->id }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="font-medium">{{ $f->titre }} <span class="font-normal text-stone-500">· {{ $f->produit->nom }} · {{ $f->cible }}</span></p>
                        <p class="mt-1 flex flex-wrap gap-2 text-xs">
                            <span @class(['rounded-full px-2 py-0.5 font-medium',
                                'bg-emerald-100 text-emerald-900' => $f->type === 'pratique',
                                'bg-lime-100 text-lime-900' => $f->type === 'biologique',
                                'bg-amber-100 text-amber-900' => $f->type === 'chimique'])>{{ \App\Models\FicheTraitement::TYPES[$f->type] }}</span>
                            @if ($f->statut !== 'autorisee')
                                <span class="rounded-full bg-stone-200 px-2 py-0.5 font-medium text-stone-700">{{ \App\Models\FicheTraitement::STATUTS[$f->statut] }}</span>
                            @elseif (! $f->proposable())
                                <span class="rounded-full bg-red-100 px-2 py-0.5 font-medium text-red-900">Non proposable : manque {{ implode(', ', $f->manquants()) }}</span>
                            @endif
                            <span class="text-stone-500">Validée le {{ $f->validee_le->format('d/m/Y') }} par {{ $f->validateur->nom }}</span>
                        </p>
                        @if ($f->type === 'chimique' && $f->nom_commercial)
                            <p class="mt-1 text-sm text-stone-700">{{ $f->nom_commercial }} ({{ $f->matiere_active }}) — {{ $f->dose }} ; délai avant récolte : {{ $f->delai_avant_recolte_jours ?? '?' }} j</p>
                        @endif
                    </div>
                    @if ($peutModifier)
                        <button type="button" wire:click="ouvrir({{ $f->id }})" class="text-sm text-emerald-800 hover:underline">Modifier</button>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
