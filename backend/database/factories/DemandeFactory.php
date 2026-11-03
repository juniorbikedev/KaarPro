<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Enums\StatutDemande;
use App\Enums\TypeDemande;
use App\Models\User;
use App\Models\Vehicule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Demande>
 */
class DemandeFactory extends Factory
{
    public function definition(): array
    {
        $debut = now()->addDays(5)->startOfDay();

        return [
            'client_id' => User::factory()->role(Role::Client),
            'vehicule_id' => Vehicule::factory(),
            'partenaire_id' => fn (array $attributs) => Vehicule::find($attributs['vehicule_id'])->partenaire_id,
            'type' => TypeDemande::Location,
            'date_debut' => $debut->toDateString(),
            'date_fin' => $debut->copy()->addDays(2)->toDateString(),
            'nb_jours' => 3,
            'montant' => 90_000,
            'montant_caution' => 100_000,
            'statut' => StatutDemande::EnAttente,
        ];
    }

    public function achat(): static
    {
        return $this->state(fn () => [
            'type' => TypeDemande::Achat,
            'date_debut' => null,
            'date_fin' => null,
            'nb_jours' => null,
            'montant' => 9_500_000,
            'montant_caution' => null,
        ]);
    }
}
