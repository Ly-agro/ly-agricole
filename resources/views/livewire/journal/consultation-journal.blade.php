<div>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold">Journal d'activité</h1>
            <p class="mt-1 text-sm text-stone-500">Qui a fait quoi et quand. Rien ne s'y efface.</p>
        </div>

        <div class="flex flex-wrap gap-3 text-sm">
            <div>
                <label for="filtreAction" class="mb-1 block text-stone-600">Action</label>
                <select wire:model.live="filtreAction" id="filtreAction"
                    class="rounded-md border border-stone-300 px-3 py-1.5 focus:border-emerald-600 focus:outline-none">
                    <option value="">Toutes</option>
                    @foreach ($actions as $a)
                        <option value="{{ $a->value }}">{{ $a->libelle() }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="filtreUtilisateur" class="mb-1 block text-stone-600">Utilisateur</label>
                <select wire:model.live="filtreUtilisateur" id="filtreUtilisateur"
                    class="rounded-md border border-stone-300 px-3 py-1.5 focus:border-emerald-600 focus:outline-none">
                    <option value="">Tous</option>
                    @foreach ($utilisateurs as $u)
                        <option value="{{ $u->id }}">{{ $u->nom }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-stone-50 text-left text-stone-600">
                <tr>
                    <th class="px-4 py-3 font-medium">Date</th>
                    <th class="px-4 py-3 font-medium">Utilisateur</th>
                    <th class="px-4 py-3 font-medium">Action</th>
                    <th class="px-4 py-3 font-medium">Objet</th>
                    <th class="px-4 py-3 font-medium">Détail</th>
                    <th class="px-4 py-3 font-medium">Adresse IP</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100 align-top">
                @forelse ($lignes as $ligne)
                    <tr wire:key="journal-{{ $ligne->id }}">
                        <td class="whitespace-nowrap px-4 py-3 text-stone-600">{{ $ligne->at->format('d/m/Y H:i:s') }}</td>
                        <td class="px-4 py-3">{{ $ligne->user?->nom ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $ligne->action->libelle() }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-stone-600">
                            @if ($ligne->objet_type)
                                {{ $ligne->objet_type }} n° {{ $ligne->objet_id }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @php($champs = array_unique(array_merge(array_keys($ligne->avant ?? []), array_keys($ligne->apres ?? []))))
                            @if ($champs === [])
                                <span class="text-stone-400">—</span>
                            @else
                                <dl class="space-y-0.5">
                                    @foreach ($champs as $champ)
                                        <div class="flex flex-wrap gap-x-2">
                                            <dt class="text-stone-500">{{ $champ }} :</dt>
                                            <dd>
                                                @if ($ligne->avant !== null && array_key_exists($champ, $ligne->avant))
                                                    <span class="text-stone-500 line-through">{{ \App\Models\JournalActivite::valeurLisible($ligne->avant[$champ]) }}</span>
                                                @endif
                                                @if ($ligne->apres !== null && array_key_exists($champ, $ligne->apres))
                                                    <span>{{ \App\Models\JournalActivite::valeurLisible($ligne->apres[$champ]) }}</span>
                                                @endif
                                            </dd>
                                        </div>
                                    @endforeach
                                </dl>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-stone-500">{{ $ligne->ip ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-stone-500">Aucune ligne.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $lignes->links() }}
    </div>
</div>
