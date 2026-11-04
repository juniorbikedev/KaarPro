<?php

// Paramètres métier de KaarPro
return [

    // Adresse de l'interface React (liens des notifications, retour après paiement)
    'frontend_url' => rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/'),

    // Commission de la plateforme, en pourcentage du montant de la location ou du prix de vente (hors caution)
    'commission' => [
        'location' => (float) env('COMMISSION_LOCATION', 10),
        'vente' => (float) env('COMMISSION_VENTE', 3),
    ],

    // Passerelle de paiement : « simulateur » en développement, « paytech » avec un compte marchand PayTech
    'paiement' => [
        'passerelle' => env('PAIEMENT_PASSERELLE', 'simulateur'),
        'operateurs' => ['wave' => 'Wave', 'orange_money' => 'Orange Money'],
    ],

    // Agrégateur PayTech (paytech.sn) : un seul compte pour Wave et Orange Money.
    // En mode « test », PayTech débite réellement un montant aléatoire de 100 à 150 FCFA, quel que soit le prix.
    'paytech' => [
        'url' => rtrim(env('PAYTECH_URL', 'https://paytech.sn/api'), '/'),
        'cle_api' => env('PAYTECH_API_KEY'),
        'cle_secrete' => env('PAYTECH_API_SECRET'),
        'environnement' => env('PAYTECH_ENV', 'test'), // test ou prod
    ],
];
