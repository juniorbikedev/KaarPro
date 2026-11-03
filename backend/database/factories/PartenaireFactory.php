<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Enums\StatutValidation;
use App\Enums\TypeActivite;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Partenaire>
 */
class PartenaireFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->role(Role::Vendeur),
            'nom_commercial' => fake()->company(),
            'type_activite' => TypeActivite::VenteEtLocation,
            'statut_validation' => StatutValidation::EnAttente,
        ];
    }

    public function valide(): static
    {
        return $this->state(fn (array $attributes) => [
            'statut_validation' => StatutValidation::Valide,
            'valide_le' => now(),
        ]);
    }
}
