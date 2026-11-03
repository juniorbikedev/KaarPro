<?php

namespace Database\Factories;

use App\Enums\CategorieVehicule;
use App\Enums\EtatVehicule;
use App\Enums\TypeActivite;
use App\Models\Partenaire;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Vehicule>
 */
class VehiculeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'partenaire_id' => Partenaire::factory()->valide(),
            'immatriculation' => strtoupper(fake()->unique()->bothify('DK-####-??')),
            'marque' => fake()->randomElement(['Toyota', 'Hyundai', 'Kia', 'Peugeot', 'Renault']),
            'modele' => fake()->randomElement(['Corolla', 'Tucson', 'Sportage', '308', 'Duster']),
            'annee' => fake()->numberBetween(2015, 2025),
            'categorie' => CategorieVehicule::Berline,
            'carburant' => 'essence',
            'boite' => 'automatique',
            'places' => 5,
            'kilometrage' => fake()->numberBetween(10_000, 150_000),
            'ville' => 'Dakar',
            'mode_offre' => TypeActivite::VenteEtLocation,
            'prix_jour' => 30_000,
            'prix_vente' => 9_500_000,
            'montant_caution' => 100_000,
            'etat' => EtatVehicule::Disponible,
            'statut_publication' => 'publie',
        ];
    }

    public function location(): static
    {
        return $this->state(fn () => ['mode_offre' => TypeActivite::Location, 'prix_vente' => null]);
    }

    public function vente(): static
    {
        return $this->state(fn () => ['mode_offre' => TypeActivite::Vente, 'prix_jour' => null, 'montant_caution' => null]);
    }
}
