@php($champ = 'block w-full rounded-md border border-stone-300 px-3 py-2 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 focus:outline-none')
<div>
    <div class="mb-6">
        <a href="{{ route('producteurs.fiche', $producteur) }}" class="text-sm text-emerald-800 hover:underline">← {{ $producteur->nomComplet() }}</a>
        <h1 class="mt-2 text-xl font-semibold">{{ $parcelleId ? 'Modifier la parcelle' : 'Nouvelle parcelle' }}</h1>
    </div>

    <form wire:submit="enregistrer" class="space-y-6">
        <section class="rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 font-semibold">Contour et surface</h2>

            <div class="grid gap-6 md:grid-cols-[1fr_auto]">
                <div class="space-y-4">
                    <div>
                        <x-champ-fichier modele="fichierContour" label="Importer un fichier GeoJSON" :fichier="$fichierContour"
                            accept=".geojson,.json,application/geo+json,application/json" formats="Fichier .geojson ou .json du contour" />
                    </div>

                    <div>
                        <label for="texteContour" class="mb-1 block text-sm font-medium text-stone-700">ou coller le GeoJSON</label>
                        <textarea wire:model="texteContour" id="texteContour" rows="3" class="{{ $champ }} font-mono text-xs"
                            placeholder='{"type":"Polygon","coordinates":[[[-5.63,9.45], …]]}'></textarea>
                        <button type="button" wire:click="lireTexte" class="mt-2 rounded-md border border-stone-300 px-3 py-1.5 text-sm hover:bg-stone-50">Lire ce contour</button>
                        @error('texteContour') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>

                    <p class="text-xs text-stone-500">
                        La surface est calculée à partir du contour ; elle ne se saisit pas. Sans contour, elle reste « non relevée »
                        (relevé au GPS sur le terrain).
                    </p>
                </div>

                <div class="w-52 text-center">
                    @if ($contour)
                        <svg viewBox="0 0 200 200" class="h-52 w-52 rounded-md border border-stone-200 bg-stone-50" role="img" aria-label="Forme de la parcelle">
                            @foreach ($pointsSvg as $points)
                                <polygon points="{{ $points }}" class="fill-emerald-200 stroke-emerald-800" stroke-width="1.5" />
                            @endforeach
                        </svg>
                        <p class="mt-2 font-semibold" id="surface">{{ $surface }}</p>
                        <button type="button" wire:click="retirerContour" class="mt-1 text-xs text-stone-500 hover:text-red-700">Retirer le contour</button>
                    @else
                        <div class="flex h-52 w-52 items-center justify-center rounded-md border border-dashed border-stone-300 text-sm text-stone-500">
                            Surface non relevée
                        </div>
                    @endif
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 font-semibold">Description</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="nom" class="mb-1 block text-sm font-medium text-stone-700">Nom de la parcelle</label>
                    <input wire:model="nom" id="nom" type="text" required placeholder="ex. Champ derrière le marigot" class="{{ $champ }}">
                    @error('nom') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="produitId" class="mb-1 block text-sm font-medium text-stone-700">Culture</label>
                    <select wire:model="produitId" id="produitId" class="{{ $champ }}">
                        <option value="">— Non renseignée —</option>
                        @foreach ($produits as $p)
                            <option value="{{ $p->id }}">{{ $p->nom }}</option>
                        @endforeach
                    </select>
                    @error('produitId') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="anneePlantation" class="mb-1 block text-sm font-medium text-stone-700">Année de plantation</label>
                    <input wire:model="anneePlantation" id="anneePlantation" type="number" step="1" class="{{ $champ }}">
                    @error('anneePlantation') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="nbArbres" class="mb-1 block text-sm font-medium text-stone-700">Nombre d'arbres</label>
                    <input wire:model="nbArbres" id="nbArbres" type="number" step="1" min="0" class="{{ $champ }}">
                    @error('nbArbres') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="sol" class="mb-1 block text-sm font-medium text-stone-700">Type de sol</label>
                    <input wire:model="sol" id="sol" type="text" class="{{ $champ }}">
                    @error('sol') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="accesEau" class="mb-1 block text-sm font-medium text-stone-700">Accès à l'eau</label>
                    <select wire:model="accesEau" id="accesEau" class="{{ $champ }}">
                        <option value="">— Inconnu —</option>
                        <option value="1">Oui</option>
                        <option value="0">Non</option>
                    </select>
                </div>
            </div>
        </section>

        <div class="flex gap-3">
            <button type="submit" wire:loading.attr="disabled"
                class="rounded-md bg-emerald-700 px-4 py-2 font-medium text-white hover:bg-emerald-800 disabled:opacity-60">Enregistrer</button>
            <a href="{{ route('producteurs.fiche', $producteur) }}" class="rounded-md px-4 py-2 text-stone-700 hover:bg-stone-100">Annuler</a>
        </div>
    </form>
</div>
