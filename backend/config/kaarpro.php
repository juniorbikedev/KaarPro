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

    // Passerelle de paiement : « simulateur » en développement ; Wave et Orange Money exigent un compte marchand
    'paiement' => [
        'passerelle' => env('PAIEMENT_PASSERELLE', 'simulateur'),
        'operateurs' => ['wave' => 'Wave', 'orange_money' => 'Orange Money'],
    ],
];
