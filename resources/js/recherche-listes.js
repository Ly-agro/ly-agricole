import TomSelect from 'tom-select';

const CACHE = ['tomselected', 'ts-hidden-accessible'];

function equiper(select) {
    if (select.tomselect || select.multiple || select.hasAttribute('data-sans-recherche')) return;
    const creer = select.hasAttribute('data-recherche-creer');
    new TomSelect(select, {
        allowEmptyOption: true,
        create: creer,
        createOnBlur: creer,
        persist: false,
        maxOptions: null,
        dropdownParent: 'body',
        render: {
            no_results: () => '<div class="no-results">Aucun résultat</div>',
            option_create: (data, echapper) => `<div class="create">Utiliser « <strong>${echapper(data.input)}</strong> »</div>`,
        },
    });
}

function synchroniser(select) {
    const ts = select.tomselect;
    if (!ts) return equiper(select);
    select.classList.add(...CACHE);

    ts.clearOptions();
    ts.sync();
}

export function equiperTout(racine = document) {
    racine.querySelectorAll('select').forEach((s) => (s.tomselect ? synchroniser(s) : equiper(s)));
}

document.addEventListener('DOMContentLoaded', () => equiperTout());

document.addEventListener('livewire:init', () => {
    window.Livewire.hook('morph.removing', ({ el, skip }) => {
        if (el.classList?.contains('ts-wrapper') || el.classList?.contains('ts-dropdown')) skip();
    });

    window.Livewire.hook('commit', ({ succeed }) => succeed(() => queueMicrotask(() => equiperTout())));
});

document.addEventListener('livewire:navigated', () => equiperTout());
