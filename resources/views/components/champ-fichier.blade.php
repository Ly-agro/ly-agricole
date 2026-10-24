@props(['modele', 'label', 'accept' => null, 'formats' => null, 'fichier' => null, 'facultatif' => false])
@php
    $erreur = $errors->first($modele);
    $present = $fichier instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile && $erreur === '';
    $image = $present && $fichier->isPreviewable();
    $taille = $present ? $fichier->getSize() : 0;
    $tailleLisible = $taille >= 1048576 ? number_format($taille / 1048576, 1, ',', ' ').' Mo' : max(1, (int) round($taille / 1024)).' Ko';
@endphp
<div
    x-data="{ survol: false, envoi: false, progres: 0 }"
    x-on:livewire-upload-start="envoi = true; progres = 0"
    x-on:livewire-upload-progress="progres = $event.detail.progress"
    x-on:livewire-upload-finish="envoi = false"
    x-on:livewire-upload-error="envoi = false"
    x-on:livewire-upload-cancel="envoi = false"
>
    <label for="{{ $modele }}" class="mb-1 block text-sm font-medium text-stone-700">
        {{ $label }}
        @if ($facultatif)
            <span class="font-normal text-stone-500">(facultatif)</span>
        @endif
    </label>

    <div class="relative">
        @if ($present)
            <div class="flex items-center gap-4 rounded-xl border border-stone-200 bg-white p-3 shadow-sm">
                @if ($image)
                    <img src="{{ $fichier->temporaryUrl() }}" alt="Aperçu" class="h-16 w-16 shrink-0 rounded-lg object-cover ring-1 ring-stone-200">
                @else
                    <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="h-7 w-7" aria-hidden="true"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5Z" /><path d="M14 3v5h5M9 13h6M9 17h4" /></svg>
                    </span>
                @endif
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-stone-800">{{ $fichier->getClientOriginalName() }}</p>
                    <p class="text-xs text-stone-500">{{ $tailleLisible }} · prêt à être enregistré</p>
                </div>
                <div class="flex shrink-0 items-center gap-1">
                    <label for="{{ $modele }}" class="cursor-pointer rounded-md px-2.5 py-1.5 text-sm text-emerald-800 hover:bg-emerald-50">Remplacer</label>
                    <button type="button" wire:click="$set('{{ $modele }}', null)" class="rounded-md px-2.5 py-1.5 text-sm text-stone-600 hover:bg-stone-100">Retirer</button>
                </div>
            </div>
        @else
            <div
                class="flex flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed px-6 py-7 text-center transition"
                :class="survol ? 'border-emerald-500 bg-emerald-50' : '{{ $erreur !== '' ? 'border-red-300 bg-red-50/40' : 'border-stone-300 bg-stone-50' }}'"
            >
                <span class="flex h-11 w-11 items-center justify-center rounded-full bg-white text-emerald-700 shadow-sm ring-1 ring-stone-200">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true"><path d="M12 16V4M7 9l5-5 5 5" /><path d="M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2" /></svg>
                </span>
                <p class="text-sm text-stone-700"><span class="font-medium text-emerald-800">Choisir un fichier</span> ou le glisser ici</p>
                @if ($formats)
                    <p class="text-xs text-stone-500">{{ $formats }}</p>
                @endif
            </div>
        @endif

        <input
            wire:model="{{ $modele }}"
            id="{{ $modele }}"
            type="file"
            @if ($accept) accept="{{ $accept }}" @endif
            x-on:dragenter="survol = true"
            x-on:dragleave="survol = false"
            x-on:drop="survol = false"
            class="{{ $present ? 'sr-only' : 'absolute inset-0 h-full w-full cursor-pointer opacity-0' }}"
        >

        <div x-show="envoi" x-cloak class="absolute inset-0 flex flex-col items-center justify-center gap-2 rounded-xl bg-white/90 px-6">
            <p class="text-sm font-medium text-stone-700">Envoi en cours… <span x-text="progres + ' %'"></span></p>
            <div class="h-1.5 w-full max-w-xs overflow-hidden rounded-full bg-stone-200">
                <div class="h-full rounded-full bg-emerald-600 transition-all" :style="'width: ' + progres + '%'"></div>
            </div>
        </div>
    </div>

    @if ($erreur !== '')
        <p class="mt-1 text-sm text-red-700">{{ $erreur }}</p>
    @endif
</div>
