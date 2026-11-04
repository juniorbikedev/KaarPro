<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Crée le compte administrateur à partir de ADMIN_EMAIL et ADMIN_PASSWORD.
 * C'est le seul compte créé en production ; il n'est jamais écrasé s'il existe déjà
 * (l'administrateur peut donc changer son mot de passe depuis son profil).
 */
class AdministrateurSeeder extends Seeder
{
    public function run(): void
    {
        $motDePasse = env('ADMIN_PASSWORD', app()->isProduction() ? null : env('DEMO_PASSWORD', 'kaarpro-demo-2026'));

        if (! $motDePasse) {
            $this->command?->warn('ADMIN_PASSWORD n’est pas défini : aucun administrateur créé.');

            return;
        }

        User::firstOrCreate(['email' => env('ADMIN_EMAIL', 'admin@kaarpro.test')], [
            'nom' => 'Administrateur KaarPro',
            'password' => $motDePasse,
            'role' => Role::Admin,
        ]);
    }
}
