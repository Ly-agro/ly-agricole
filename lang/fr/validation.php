<?php

// Complété au fil des règles utilisées ; une clé absente retombe sur l'anglais
// (APP_FALLBACK_LOCALE=en).

return [

    'after_or_equal' => 'Le champ :attribute doit être une date égale ou postérieure à :date.',
    'between' => [
        'numeric' => 'Le champ :attribute doit être compris entre :min et :max.',
    ],
    'boolean' => 'Le champ :attribute doit être vrai ou faux.',
    'date' => 'Le champ :attribute n\'est pas une date valide.',
    'email' => 'Le champ :attribute doit être une adresse e-mail valide.',
    'enum' => 'La valeur choisie pour :attribute n\'est pas valide.',
    'exists' => 'La valeur choisie pour :attribute n\'existe pas.',
    'integer' => 'Le champ :attribute doit être un nombre entier.',
    'max' => [
        'numeric' => 'Le champ :attribute ne doit pas dépasser :max.',
        'string' => 'Le champ :attribute ne doit pas dépasser :max caractères.',
    ],
    'min' => [
        'numeric' => 'Le champ :attribute doit être au moins :min.',
        'string' => 'Le champ :attribute doit contenir au moins :min caractères.',
    ],
    'numeric' => 'Le champ :attribute doit être un nombre.',
    'regex' => 'Le format du champ :attribute n\'est pas valide.',
    'required' => 'Le champ :attribute est obligatoire.',
    'string' => 'Le champ :attribute doit être du texte.',
    'unique' => 'Cette valeur de :attribute est déjà utilisée.',

    'attributes' => [
        'donnees.debut' => 'début',
    ],

];
