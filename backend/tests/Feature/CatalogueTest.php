<?php

namespace Tests\Feature;

use App\Enums\CategorieVehicule;
use App\Enums\EtatVehicule;
use App\Models\Partenaire;
use App\Models\Vehicule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogueTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_catalogue_ne_montre_que_les_vehicules_visibles(): void
    {
        $visible = Vehicule::factory()->create();
        Vehicule::factory()->create(['statut_publication' => 'retire']);
        Vehicule::factory()->create(['etat' => EtatVehicule::Vendu]);
        Vehicule::factory()->create(['partenaire_id' => Partenaire::factory()]); // partenaire en attente

        $this->getJson('/api/vehicules')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visible->id);
    }

    public function test_filtrer_par_offre_categorie_et_prix(): void
    {
        $location = Vehicule::factory()->location()->create(['prix_jour' => 25000, 'categorie' => CategorieVehicule::Suv]);
        $vente = Vehicule::factory()->vente()->create(['prix_vente' => 7000000]);
        $lesDeux = Vehicule::factory()->create(['prix_jour' => 60000]);

        $ids = fn ($url) => collect($this->getJson($url)->assertOk()->json('data'))->pluck('id')->sort()->values()->all();

        $this->assertSame([$location->id, $lesDeux->id], $ids('/api/vehicules?offre=location'));
        $this->assertSame([$vente->id, $lesDeux->id], $ids('/api/vehicules?offre=vente'));
        $this->assertSame([$location->id], $ids('/api/vehicules?offre=location&prix_max=30000'));
        $this->assertSame([$location->id], $ids('/api/vehicules?categorie=suv'));
    }

    public function test_les_dates_ecartent_les_vehicules_indisponibles(): void
    {
        $libre = Vehicule::factory()->location()->create();
        $bloque = Vehicule::factory()->location()->create();
        $bloque->indisponibilites()->create(['date_debut' => '2030-01-10', 'date_fin' => '2030-01-15']);
        Vehicule::factory()->vente()->create();

        $reponse = $this->getJson('/api/vehicules?date_debut=2030-01-14&date_fin=2030-01-20')->assertOk();
        $this->assertSame([$libre->id], collect($reponse->json('data'))->pluck('id')->all());

        $reponse = $this->getJson('/api/vehicules?date_debut=2030-01-16&date_fin=2030-01-20')->assertOk();
        $this->assertCount(2, $reponse->json('data'));
    }

    public function test_la_recherche_texte_porte_sur_la_marque_et_le_modele(): void
    {
        Vehicule::factory()->create(['marque' => 'Toyota', 'modele' => 'Hilux']);
        Vehicule::factory()->create(['marque' => 'Kia', 'modele' => 'Picanto']);

        $this->getJson('/api/vehicules?q=hilux')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.modele', 'Hilux');
    }

    public function test_la_fiche_montre_les_indisponibilites_a_venir_et_le_partenaire(): void
    {
        $vehicule = Vehicule::factory()->create();
        $vehicule->indisponibilites()->create(['date_debut' => now()->subDays(10)->toDateString(), 'date_fin' => now()->subDays(5)->toDateString()]);
        $vehicule->indisponibilites()->create(['date_debut' => now()->addDays(3)->toDateString(), 'date_fin' => now()->addDays(4)->toDateString()]);

        $this->getJson("/api/vehicules/{$vehicule->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.indisponibilites')
            ->assertJsonPath('data.partenaire.nom_commercial', $vehicule->partenaire->nom_commercial)
            ->assertJsonMissingPath('data.documents');
    }
}
