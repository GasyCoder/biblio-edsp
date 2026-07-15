<?php

return [
    'superadmin' => [
        'name' => env('SEED_SUPERADMIN_NAME', 'Super Administrateur'),
        'email' => env('SEED_SUPERADMIN_EMAIL', 'superadmin@edsp.mg'),
        'password' => env('SEED_SUPERADMIN_PASSWORD'),
    ],
    'secretaire' => [
        'name' => env('SEED_SECRETAIRE_NAME', 'Secrétaire Bibliothèque'),
        'email' => env('SEED_SECRETAIRE_EMAIL', 'secretaire@edsp.mg'),
        'password' => env('SEED_SECRETAIRE_PASSWORD'),
    ],
    'etudiant' => [
        'name' => env('SEED_ETUDIANT_NAME', 'Étudiant Démonstration'),
        'email' => env('SEED_ETUDIANT_EMAIL', 'etudiant@edsp.mg'),
        'password' => env('SEED_ETUDIANT_PASSWORD'),
    ],
];
