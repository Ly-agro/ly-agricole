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

// Valeur de la propriété Livewire liée par wire:model, ou undefined.
function valeurLivewire(select) {
    const modele = [...select.attributes].find((a) => a.name.startsWith('wire:model'));
    const racine = select.closest('[wire\\:id]');
    if (!modele || !racine || !window.Livewire) return undefined;
    const valeur = window.Livewire.find(racine.getAttribute('wire:id'))?.get(modele.value);
    return valeur === null || valeur === undefined ? undefined : String(valeur);
}

// Le morph de Livewire remet les <option> du serveur, sans `selected` : le select
// retombe sur sa première option et Tom Select, rééquipé, l'affiche vide alors que
// le serveur garde la valeur (vu le 2026-10-08 : village vidé après l'envoi d'une
// photo, puis `required` bloquait l'enregistrement). On recale sans événement.
function recaler(select) {
    const ts = select.tomselect;
    const attendu = valeurLivewire(select);
    if (!ts || attendu === undefined || ts.getValue() === attendu) return;
    ts.setValue(attendu, true);
}

export function equiperTout() {
    document.querySelectorAll('select').forEach((select) => {
        const ts = select.tomselect;
        if (ts && (!ts.wrapper?.isConnected || !select.classList.contains('tomselected'))) {
            detacher(select);
        }
        equiper(select);
        recaler(select);
    });
    retirerOrphelins();
}

document.addEventListener('DOMContentLoaded', () => equiperTout());

function plusTard() {
    setTimeout(equiperTout, 0);
    setTimeout(equiperTout, 150);
}

function brancher() {
    window.Livewire.hook('morphed', () => plusTard());
    window.Livewire.hook('commit', ({ succeed }) => succeed(() => plusTard()));
}

if (window.Livewire) brancher();
else document.addEventListener('livewire:init', brancher);

document.addEventListener('livewire:navigated', () => equiperTout());
