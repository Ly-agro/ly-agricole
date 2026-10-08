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

function detacher(select) {
    const ts = select.tomselect;
    ts.wrapper?.remove();
    ts.dropdown?.remove();
    delete select.tomselect;
    select.classList.remove(...CACHE);
    select.removeAttribute('tabindex');
    select.removeAttribute('hidden');
}

function retirerOrphelins() {
    const vivants = new Set();
    document.querySelectorAll('select').forEach((s) => {
        if (s.tomselect) {
            vivants.add(s.tomselect.wrapper);
            vivants.add(s.tomselect.dropdown);
        }
    });
    document.querySelectorAll('.ts-wrapper, .ts-dropdown').forEach((el) => {
        if (!vivants.has(el)) el.remove();
    });
}

export function equiperTout() {
    document.querySelectorAll('select').forEach((select) => {
        const ts = select.tomselect;
        if (ts && (!ts.wrapper?.isConnected || !select.classList.contains('tomselected'))) {
            detacher(select);
        }
        equiper(select);
    });
    retirerOrphelins();
}

document.addEventListener('DOMContentLoaded', () => equiperTout());

document.addEventListener('livewire:init', () => {
    window.Livewire.hook('commit', ({ succeed }) => succeed(() => queueMicrotask(() => equiperTout())));
});

document.addEventListener('livewire:navigated', () => equiperTout());
