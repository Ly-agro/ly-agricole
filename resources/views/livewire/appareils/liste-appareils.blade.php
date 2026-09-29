<div class="space-y-6">
    <div>
        <h1 class="text-xl font-semibold">Appareils</h1>
        <p class="mt-1 max-w-2xl text-sm text-stone-600">
            Les téléphones connectés à l'appli terrain. Un téléphone perdu ou volé se coupe ici : il ne peut plus rien envoyer
            et doit se reconnecter avec le mot de passe. Les connexions n'expirent pas toutes seules.
        </p>
    </div>

    @if ($statut !== '')
        <p class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-900">{{ $statut }}</p>
    @endif

    @if ($aCouper !== null || $aCouperTous !== null)
        <form wire:submit="couper" class="rounded-xl border border-red-200 bg-red-50 p-5">
            <label for="motif" class="block text-sm font-medium text-red-950">
                {{ $aCouperTous !== null ? 'Couper TOUS les appareils de cet utilisateur' : 'Couper cet appareil' }} — motif
            </label>
            <input wire:model="motif" id="motif" type="text" placeholder="Ex. téléphone perdu" class="mt-1 block w-full rounded-md border border-red-300 px-3 py-2 focus:outline-none">
            @error('motif') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            <div class="mt-3 flex gap-2">
                <button type="submit" class="rounded-md bg-red-700 px-4 py-2 text-sm font-medium text-white hover:bg-red-800">Couper</button>
                <button type="button" wire:click="annuler" class="rounded-md px-3 py-2 text-sm text-stone-700 underline">Annuler</button>
            </div>
        </form>
    @endif

    @if ($appareils->isEmpty())
        <div class="rounded-xl border border-dashed border-stone-300 bg-white p-8 text-center">
            <p class="font-medium">Aucun appareil connecté.</p>
            <p class="mt-1 text-sm text-stone-600">Un téléphone apparaît ici après sa première connexion à l'appli terrain.</p>
        </div>
    @else
        <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white">
            <table class="w-full text-sm">
                <thead class="bg-stone-50 text-left text-xs text-stone-500">
                    <tr>
                        <th class="px-4 py-2">Utilisateur</th>
                        <th class="px-4 py-2">Appareil</th>
                        <th class="px-4 py-2">Connecté le</th>
                        <th class="px-4 py-2">Dernière activité</th>
                        <th class="px-4 py-2">Dernière synchronisation</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @foreach ($appareils as $a)
                        <tr wire:key="jeton-{{ $a['jeton']->id }}">
                            <td class="px-4 py-2">{{ $a['utilisateur']?->nom ?? '—' }}
                                @if ($a['utilisateur'] && ! $a['utilisateur']->actif)<span class="ml-1 rounded-full bg-red-100 px-2 py-0.5 text-xs text-red-800">compte désactivé</span>@endif
                            </td>
                            <td class="px-4 py-2 text-stone-600">{{ $a['jeton']->name }}</td>
                            <td class="px-4 py-2 tabular-nums">{{ $a['cree_le']?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td class="px-4 py-2 tabular-nums">{{ $a['dernier_usage']?->format('d/m/Y H:i') ?? 'jamais' }}</td>
                            <td class="px-4 py-2 tabular-nums">
                                @if ($a['derniere_synchro'])
                                    {{ $a['derniere_synchro']->recu_at->format('d/m/Y H:i') }}
                                    <span class="block text-xs text-stone-500">{{ $a['derniere_synchro']->nb_operations }} opération(s)@if ($a['derniere_synchro']->nb_rejetees > 0), <span class="text-red-700">{{ $a['derniere_synchro']->nb_rejetees }} rejetée(s)</span>@endif</span>
                                @else
                                    <span class="text-stone-400">aucune</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-2 text-right text-xs">
                                <button type="button" wire:click="demanderCoupure({{ $a['jeton']->id }})" class="rounded-md border border-red-300 px-2 py-1 text-red-800 hover:bg-red-50">Couper</button>
                                @if ($a['utilisateur'])
                                    <button type="button" wire:click="demanderCoupureTous({{ $a['utilisateur']->id }})" class="ml-1 text-stone-500 underline">Tous ses appareils</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
