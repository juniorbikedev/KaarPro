<?php

// Messages de validation en français pour les règles utilisées par l'API KaarPro.
return [
    'boolean' => 'Le champ :attribute doit être vrai ou faux.',
    'confirmed' => 'La confirmation du champ :attribute ne correspond pas.',
    'email' => 'Le champ :attribute doit être une adresse e-mail valide.',
    'enum' => 'La valeur choisie pour :attribute est invalide.',
    'file' => 'Le champ :attribute doit être un fichier.',
    'max' => [
        'file' => 'Le fichier :attribute ne doit pas dépasser :max kilo-octets.',
        'string' => 'Le champ :attribute ne doit pas dépasser :max caractères.',
    ],
    'mimes' => 'Le fichier :attribute doit être de type : :values.',
    'min' => [
        'string' => 'Le champ :attribute doit contenir au moins :min caractères.',
    ],
    'password' => [
        'min' => 'Le mot de passe doit contenir au moins :min caractères.',
    ],
    'required' => 'Le champ :attribute est obligatoire.',
    'string' => 'Le champ :attribute doit être une chaîne de caractères.',
    'unique' => 'Cette valeur de :attribute est déjà utilisée.',

    'attributes' => [
        'nom' => 'nom',
        'email' => 'e-mail',
        'telephone' => 'téléphone',
        'password' => 'mot de passe',
        'nom_commercial' => 'nom commercial',
        'type_activite' => "type d'activité",
        'piece_identite' => "pièce d'identité",
        'zone' => 'zone',
        'motif' => 'motif',
        'est_actif' => 'statut du compte',
    ],
];
