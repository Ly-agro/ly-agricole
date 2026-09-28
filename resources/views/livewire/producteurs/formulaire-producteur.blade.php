@php($champ = 'block w-full rounded-md border border-stone-300 px-3 py-2 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 focus:outline-none')
<div>
    <div class="mb-6">
        <a href="{{ $producteurId ? route('producteurs.fiche', $producteurId) : route('producteurs') }}" class="text-sm text-emerald-800 hover:underline">← Retour</a>
        <h1 class="mt-2 text-xl font-semibold">{{ $producteurId ? 'Modifier la fiche' : 'Nouveau producteur' }}</h1>
    </div>

    <form wire:submit="enregistrer" class="space-y-6">
        <section class="rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 font-semibold">Identité</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="nom" class="mb-1 block text-sm font-medium text-stone-700">Nom</label>
                    <input wire:model="nom" id="nom" type="text" required class="{{ $champ }}">
                    @error('nom') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="prenoms" class="mb-1 block text-sm font-medium text-stone-700">Prénoms</label>
                    <input wire:model="prenoms" id="prenoms" type="text" required class="{{ $champ }}">
                    @error('prenoms') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="sexe" class="mb-1 block text-sm font-medium text-stone-700">Sexe</label>
                    <select wire:model="sexe" id="sexe" class="{{ $champ }}">
                        <option value="">— Non renseigné —</option>
                        @foreach ($sexes as $s)
                            <option value="{{ $s->value }}">{{ $s->libelle() }}</option>
                        @endforeach
                    </select>
                    @error('sexe') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="anneeNaissance" class="mb-1 block text-sm font-medium text-stone-700">Année de naissance <span class="font-normal text-stone-500">(facultatif)</span></label>
                    <input wire:model="anneeNaissance" id="anneeNaissance" type="number" step="1" class="{{ $champ }}">
                    @error('anneeNaissance') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="pieceType" class="mb-1 block text-sm font-medium text-stone-700">Pièce d'identité</label>
                    <select wire:model.live="pieceType" id="pieceType" class="{{ $champ }}">
                        <option value="">— Aucune —</option>
                        @foreach ($typesPiece as $t)
                            <option value="{{ $t->value }}">{{ $t->libelle() }}</option>
                        @endforeach
                    </select>
                    @error('pieceType') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="pieceNumero" class="mb-1 block text-sm font-medium text-stone-700">Numéro de la pièce</label>
                    <input wire:model.blur="pieceNumero" id="pieceNumero" type="text" class="{{ $champ }}">
                    @error('pieceNumero') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label for="photo" class="mb-1 block text-sm font-medium text-stone-700">Photo <span class="font-normal text-stone-500">(facultatif, pour la carte)</span></label>
                    <input wire:model="photo" id="photo" type="file" accept="image/jpeg,image/png,image/webp" class="block text-sm">
                    <div wire:loading wire:target="photo" class="mt-1 text-sm text-stone-500">Envoi de la photo…</div>
                    @if ($photo && ! $errors->has('photo') && $photo->isPreviewable())
                        <img src="{{ $photo->temporaryUrl() }}" alt="Aperçu" class="mt-2 h-24 w-24 rounded-md object-cover">
                    @endif
                    @error('photo') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 font-semibold">Contact et paiement</h2>
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label for="telephone" class="mb-1 block text-sm font-medium text-stone-700">Téléphone</label>
                    <input wire:model.blur="telephone" id="telephone" type="tel" placeholder="07 01 02 03 04" class="{{ $champ }}">
                    @error('telephone') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="numeroMobileMoney" class="mb-1 block text-sm font-medium text-stone-700">Numéro Mobile Money</label>
                    <input wire:model.blur="numeroMobileMoney" id="numeroMobileMoney" type="tel" class="{{ $champ }}">
                    @error('numeroMobileMoney') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="operateurMm" class="mb-1 block text-sm font-medium text-stone-700">Opérateur</label>
                    <select wire:model="operateurMm" id="operateurMm" class="{{ $champ }}">
                        <option value="">— Aucun —</option>
                        @foreach ($operateurs as $o)
                            <option value="{{ $o->value }}">{{ $o->libelle() }}</option>
                        @endforeach
                    </select>
                    @error('operateurMm') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 font-semibold">Lieu</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="villageId" class="mb-1 block text-sm font-medium text-stone-700">Village</label>
                    <select wire:model.live="villageId" id="villageId" required class="{{ $champ }}">
                        <option value="">— Choisir —</option>
                        @foreach ($villages as $v)
                            <option value="{{ $v->id }}">{{ $v->nom }} ({{ $v->zone->nom }})</option>
                        @endforeach
                    </select>
                    @error('villageId') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="groupeId" class="mb-1 block text-sm font-medium text-stone-700">Groupe <span class="font-normal text-stone-500">(facultatif)</span></label>
                    <select wire:model="groupeId" id="groupeId" class="{{ $champ }}" @disabled($groupes->isEmpty())>
                        <option value="">{{ $groupes->isEmpty() ? '— Aucun groupe dans ce village —' : '— Aucun —' }}</option>
                        @foreach ($groupes as $g)
                            <option value="{{ $g->id }}">{{ $g->nom }}</option>
                        @endforeach
                    </select>
                    @error('groupeId') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        @if (! $producteurId)
            <section class="rounded-xl border border-amber-200 bg-amber-50 p-6">
                <h2 class="mb-2 font-semibold text-amber-900">Accord du producteur</h2>
                <p class="mb-3 text-sm text-amber-900">
                    Expliquer au producteur, dans sa langue, que LY AGRICOLE enregistre son identité, son téléphone et ses
                    parcelles pour suivre ses prêts et ses livraisons, et qu'il peut demander à voir ou corriger ces informations.
                </p>
                <label class="flex items-start gap-2 text-sm font-medium text-amber-950">
                    <input wire:model="consentement" type="checkbox" class="mt-0.5 rounded border-amber-400 text-emerald-700">
                    Le producteur a donné son accord.
                </label>
                @error('consentement') <p class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
            </section>
        @endif

        @if ($alertesDoublons !== [])
            <section class="rounded-xl border border-red-200 bg-red-50 p-6">
                <h2 class="mb-2 font-semibold text-red-900">Doublon possible</h2>
                <ul class="mb-3 list-disc space-y-1 pl-5 text-sm text-red-900">
                    @foreach ($alertesDoublons as $alerte)
                        <li>{{ $alerte }}</li>
                    @endforeach
                </ul>
                <label class="flex items-start gap-2 text-sm font-medium text-red-950">
                    <input wire:model="doublonsConfirmes" type="checkbox" class="mt-0.5 rounded border-red-400 text-red-700">
                    J'ai vérifié : il s'agit bien d'une autre personne (cette confirmation est inscrite au journal).
                </label>
            </section>
        @endif

        <div class="flex gap-3">
            <button type="submit" wire:loading.attr="disabled"
                class="rounded-md bg-emerald-700 px-4 py-2 font-medium text-white hover:bg-emerald-800 disabled:opacity-60">
                Enregistrer
            </button>
            <a href="{{ $producteurId ? route('producteurs.fiche', $producteurId) : route('producteurs') }}"
                class="rounded-md px-4 py-2 text-stone-700 hover:bg-stone-100">Annuler</a>
        </div>
    </form>
</div>
