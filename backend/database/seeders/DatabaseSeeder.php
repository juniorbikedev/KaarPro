<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\StatutValidation;
use App\Enums\TypeActivite;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Comptes de démonstration, un par rôle. Les e-mails et mots de passe viennent du fichier .env
     * (voir .env.example) : ce sont des valeurs de test, à ne jamais utiliser en production.
     */
    public function run(): void
    {
        $demo = env('DEMO_PASSWORD', 'kaarpro-demo-2026');

        User::updateOrCreate(['email' => env('ADMIN_EMAIL', 'admin@kaarpro.test')], [
            'nom' => 'Administrateur KaarPro',
            'password' => env('ADMIN_PASSWORD', $demo),
            'role' => Role::Admin,
        ]);

        User::updateOrCreate(['email' => 'agent@kaarpro.test'], [
            'nom' => 'Agent Dakar Plateau',
            'telephone' => '77 000 00 01',
            'password' => $demo,
            'role' => Role::Agent,
            'zone' => 'Dakar Plateau',
        ]);

        User::updateOrCreate(['email' => 'client@kaarpro.test'], [
            'nom' => 'Client de démonstration',
            'telephone' => '77 000 00 02',
            'password' => $demo,
            'role' => Role::Client,
        ]);

        $loueur = User::updateOrCreate(['email' => 'loueur@kaarpro.test'], [
            'nom' => 'Loueur de démonstration',
            'telephone' => '77 000 00 03',
            'password' => $demo,
            'role' => Role::Vendeur,
        ]);
        $loueur->partenaire()->updateOrCreate([], [
            'nom_commercial' => 'Dakar Location Auto',
            'type_activite' => TypeActivite::Location,
            'statut_validation' => StatutValidation::Valide,
            'valide_le' => now(),
        ]);
    }
}
