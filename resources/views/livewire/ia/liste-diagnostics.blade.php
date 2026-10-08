@php
    use App\Enums\StatutDiagnostic;
    $couleurs = [
        'en_attente' => 'bg-stone-100 text-stone-700', 'propose' => 'bg-amber-100 text-amber-900',
        'incertain' => 'bg-sky-100 text-sky-900', 'confirme' => 'bg-emerald-100 text-emerald-900',
        'corrige' => 'bg-emerald-100 text-emerald-900', 'erreur' => 'bg-red-100 text-red-900',
    ];
@endphp

<div class="mx-auto max-w-5xl">
    <div class="mb-5 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold">Diagnostics IA</h1>
            <p class="mt-1 max-w-2xl text-sm text-stone-600">Avis sur les photos des visites. Tout reste « proposé » ou « incertain » tant qu'un agronome ne l'a pas confirmé ; les brouillons de conseil sont internes.</p>
        </div>
        <div class="text-sm">
            <label for="filtre" class="mb-1 block text-stone-600">Statut</label>
            <select wire:model.live="filtre" id="filtre" class="rounded-md border border-stone-300 px-3 py-1.5">
                <option value="">Tous</option>
                @foreach ($statuts as $s)<option value="{{ $s->value }}">{{ $s->libelle() }}</option>@endforeach
            </select>
        </div>
    </div>

    @if (! $serviceConfigure)
        <p class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-900">Le serveur IA n'est pas encore installé (IA_URL) : les demandes restent en attente ou en erreur, et se redemandent depuis la visite une fois le serveur branché. Voir docs/INSTALLATION_IA.md.</p>
    @endif
    @if (! $peutValider)
        <p class="mb-4 rounded-lg border border-stone-200 bg-white px-4 py-2 text-sm text-stone-700">Seul un agronome confirme un diagnostic ou demande un conseil. Sans agronome, rien n'est validé ni conseillé.</p>
    @endif

    <div class="space-y-4">
        @forelse ($diagnostics as $d)
            <article wire:key="diag-{{ $d->id }}" class="flex flex-wrap gap-4 rounded-xl border border-stone-200 bg-white p-4 shadow-sm">
                <a href="{{ route('photos-terrain', $d->photo) }}" target="_blank" rel="noopener" class="shrink-0">
                    <img src="{{ route('photos-terrain', $d->photo) }}" alt="Photo de la visite" loading="lazy" class="h-28 w-28 rounded-lg object-cover">
                </a>
                <div class="min-w-0 flex-1 space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <span @class(['rounded-full px-2.5 py-0.5 text-xs font-medium', $couleurs[$d->statut->value]])>{{ $d->statut->libelle() }}</span>
                        <span class="text-sm text-stone-700">{{ $d->visite->parcelle->producteur->nomComplet() }} · {{ $d->visite->parcelle->nom }} · {{ $d->visite->parcelle->produit->nom ?? 'culture ?' }}</span>
                        <span class="text-xs text-stone-500">demandé le {{ $d->created_at->format('d/m/Y') }} par {{ $d->demandeur->nom }}</span>
                    </div>

                    @if ($d->classe_proposee)
                        <p class="text-sm">Proposition du modèle : <strong>{{ $d->classe_proposee }}</strong> ({{ intdiv((int) $d->confiance_pour_mille, 10) }} %)</p>
                    @elseif ($d->motif)
                        <p class="text-sm text-stone-600">{{ $d->motif }}</p>
                    @endif
                    @if ($d->annotation_classe && ! $d->statut->valide())
                        <p class="text-sm text-stone-700">
                            <span class="rounded-full bg-violet-100 px-2 py-0.5 text-xs font-medium text-violet-900">Annotation provisoire ({{ $d->annotation_source }})</span>
                            {{ $d->annotation_classe }}@if ($d->annotation_note) — {{ $d->annotation_note }}@endif
                            <span class="block text-xs text-stone-500">Pas une validation : un agronome doit confirmer ou corriger.</span>
                        </p>
                    @endif
                    @if ($d->statut->valide())
                        <p class="text-sm">Retenu par l'agronome : <strong>{{ $d->classe_retenue }}</strong> — {{ $d->validateur?->nom }}, le {{ $d->valide_at?->format('d/m/Y') }}@if ($d->note_agronome) · {{ $d->note_agronome }}@endif</p>
                    @endif

                    @if ($d->conseil_statut === 'brouillon')
                        <div class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm">
                            <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-amber-900">Brouillon interne — à relire, jamais envoyé tel quel au producteur</p>
                            <p class="whitespace-pre-line text-stone-800">{{ \App\Services\Ia\Diagnostics::rendreConseil($d) }}</p>
                        </div>
                    @elseif ($d->conseil_statut === 'rejete')
                        <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-900">
                            <p class="font-semibold">Brouillon rejeté par le contrôle :</p>
                            <ul class="ml-4 list-disc">@foreach ($d->conseil_motifs ?? [] as $m)<li>{{ $m }}</li>@endforeach</ul>
                        </div>
                    @elseif ($d->conseil_statut === 'en_attente')
                        <p class="text-sm text-stone-600">Brouillon de conseil en cours de rédaction…</p>
                    @elseif ($d->conseil_statut === 'erreur')
                        <p class="text-sm text-red-800">Brouillon impossible : {{ implode(' ', $d->conseil_motifs ?? []) }}</p>
                    @endif

                    @if ($peutValider)
                        @if ($aValider === $d->id)
                            <form wire:submit="valider" class="flex flex-wrap items-end gap-2 rounded-lg bg-stone-50 p-3">
                                <div class="min-w-48 flex-1">
                                    <label for="classe-{{ $d->id }}" class="mb-1 block text-xs text-stone-600">Maladie, ravageur ou « sain »</label>
                                    <input wire:model="classe" id="classe-{{ $d->id }}" type="text" class="w-full rounded-md border border-stone-300 px-2 py-1.5 text-sm">
                                    @error('classe') <p class="mt-1 text-xs text-red-700">{{ $message }}</p> @enderror
                                </div>
                                <div class="min-w-48 flex-1">
                                    <label for="note-{{ $d->id }}" class="mb-1 block text-xs text-stone-600">Note (facultatif)</label>
                                    <input wire:model="note" id="note-{{ $d->id }}" type="text" class="w-full rounded-md border border-stone-300 px-2 py-1.5 text-sm">
                                </div>
                                <button type="submit" class="rounded-md bg-emerald-700 px-3 py-1.5 text-sm font-medium text-white">Enregistrer</button>
                            </form>
                        @elseif ($pourConseil === $d->id)
                            <form wire:submit="demanderConseil" class="space-y-2 rounded-lg bg-stone-50 p-3">
                                <label for="obs-{{ $d->id }}" class="block text-xs text-stone-600">Ce qui a été observé sur la parcelle (sans nom ni téléphone du producteur)</label>
                                <textarea wire:model="observation" id="obs-{{ $d->id }}" rows="2" class="w-full rounded-md border border-stone-300 px-2 py-1.5 text-sm"></textarea>
                                @error('observation') <p class="text-xs text-red-700">{{ $message }}</p> @enderror
                                <button type="submit" class="rounded-md bg-emerald-700 px-3 py-1.5 text-sm font-medium text-white">Demander le brouillon</button>
                            </form>
                        @else
                            <div class="flex flex-wrap gap-2">
                                @if (in_array($d->statut, [StatutDiagnostic::Propose, StatutDiagnostic::Incertain, StatutDiagnostic::Confirme, StatutDiagnostic::Corrige], true))
                                    <button type="button" wire:click="preparerValidation({{ $d->id }})" class="rounded-md border border-stone-300 px-3 py-1.5 text-sm hover:bg-stone-50">{{ $d->statut->valide() ? 'Modifier le diagnostic' : 'Confirmer ou corriger' }}</button>
                                @endif
                                @if ($d->statut->valide() && $d->conseil_statut !== 'en_attente')
                                    <button type="button" wire:click="preparerConseil({{ $d->id }})" class="rounded-md border border-stone-300 px-3 py-1.5 text-sm hover:bg-stone-50">Brouillon de conseil</button>
                                @endif
                            </div>
                        @endif
                    @endif
                </div>
            </article>
        @empty
            <div class="rounded-xl border border-dashed border-stone-300 bg-white/60 px-6 py-10 text-center">
                <p class="font-medium text-stone-800">Aucun diagnostic</p>
                <p class="mx-auto mt-1 max-w-xl text-sm text-stone-500">Depuis « Visites », « Demander un avis IA » sur une visite avec photos. Chaque photo confirmée par un agronome enrichit le futur jeu d'entraînement.</p>
            </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $diagnostics->links() }}</div>
</div>
