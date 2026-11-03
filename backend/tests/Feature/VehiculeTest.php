<?php

namespace Tests\Feature;

use App\Enums\StatutValidation;
use App\Enums\TypeActivite;
use App\Models\Partenaire;
use App\Models\Vehicule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VehiculeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function annonce(array $extra = []): array
    {
        return [
            'immatriculation' => 'dk-1234-ab',
            'marque' => 'Toyota',
            'modele' => 'Corolla',
            'annee' => 2020,
            'categorie' => 'berline',
            'carburant' => 'essence',
            'boite' => 'automatique',
            'places' => 5,
            'kilometrage' => 45000,
            'ville' => 'Dakar',
            'mode_offre' => 'location',
            'prix_jour' => 30000,
            'montant_caution' => 150000,
            'photos' => [UploadedFile::fake()->image('avant.jpg'), UploadedFile::fake()->image('interieur.png')],
            'carte_grise' => UploadedFile::fake()->create('carte-grise.pdf', 50, 'application/pdf'),
            'assurance' => UploadedFile::fake()->create('assurance.pdf', 50, 'application/pdf'),
            ...$extra,
        ];
    }

    private function partenaire(TypeActivite $activite = TypeActivite::Location, StatutValidation $statut = StatutValidation::Valide): Partenaire
    {
        return Partenaire::factory()->create(['type_activite' => $activite, 'statut_validation' => $statut]);
    }

    public function test_un_loueur_valide_publie_un_vehicule_avec_photos_et_documents(): void
    {
        $partenaire = $this->partenaire();

        $this->actingAs($partenaire->user)->post('/api/partenaire/vehicules', $this->annonce(), ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.immatriculation', 'DK-1234-AB')
            ->assertJsonPath('data.a_louer', true)
            ->assertJsonPath('data.a_vendre', false)
            ->assertJsonCount(2, 'data.photos')
            ->assertJsonCount(2, 'data.documents');

        $vehicule = Vehicule::first();
        $this->assertSame(['carte_grise', 'assurance'], $vehicule->documents->pluck('type')->map->value->all());
        Storage::disk('local')->assertExists($vehicule->photos->first()->chemin);
    }

    public function test_un_partenaire_non_valide_ne_peut_pas_publier(): void
    {
        $partenaire = $this->partenaire(statut: StatutValidation::EnAttente);

        $this->actingAs($partenaire->user)->post('/api/partenaire/vehicules', $this->annonce(), ['Accept' => 'application/json'])
            ->assertForbidden();
    }

    public function test_le_mode_d_offre_doit_correspondre_a_l_activite_du_partenaire(): void
    {
        $loueur = $this->partenaire(TypeActivite::Location);

        $this->actingAs($loueur->user)->post('/api/partenaire/vehicules', $this->annonce(['mode_offre' => 'vente', 'prix_vente' => 8000000]), ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('mode_offre');
    }

    public function test_les_prix_exiges_dependent_du_mode_d_offre(): void
    {
        $partenaire = $this->partenaire(TypeActivite::VenteEtLocation);

        $this->actingAs($partenaire->user)->post('/api/partenaire/vehicules', $this->annonce([
            'mode_offre' => 'vente_et_location', 'prix_jour' => null, 'montant_caution' => null,
        ]), ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['prix_jour', 'montant_caution', 'prix_vente']);
    }

    public function test_les_photos_et_les_documents_sont_obligatoires(): void
    {
        $partenaire = $this->partenaire();

        $this->actingAs($partenaire->user)->post('/api/partenaire/vehicules', $this->annonce(['photos' => [], 'carte_grise' => null, 'assurance' => null]), ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['photos', 'carte_grise', 'assurance']);
    }

    public function test_un_partenaire_ne_modifie_pas_le_vehicule_d_un_autre(): void
    {
        $vehicule = Vehicule::factory()->create();
        $autre = $this->partenaire(TypeActivite::VenteEtLocation);

        $this->actingAs($autre->user)->putJson("/api/partenaire/vehicules/{$vehicule->id}", ['marque' => 'X'])->assertNotFound();
        $this->postJson("/api/partenaire/vehicules/{$vehicule->id}/retirer")->assertNotFound();
    }

    public function test_retirer_puis_republier_un_vehicule(): void
    {
        $vehicule = Vehicule::factory()->create();
        $this->actingAs($vehicule->partenaire->user);

        $this->postJson("/api/partenaire/vehicules/{$vehicule->id}/retirer")->assertOk()->assertJsonPath('data.statut_publication', 'retire');
        $this->getJson("/api/vehicules/{$vehicule->id}")->assertNotFound();

        $this->postJson("/api/partenaire/vehicules/{$vehicule->id}/republier")->assertOk()->assertJsonPath('data.statut_publication', 'publie');
        $this->getJson("/api/vehicules/{$vehicule->id}")->assertOk();
    }

    public function test_passer_en_location_seule_efface_le_prix_de_vente(): void
    {
        $vehicule = Vehicule::factory()->create();
        $champs = collect($this->annonce(['immatriculation' => $vehicule->immatriculation]))->except(['photos', 'carte_grise', 'assurance'])->all();

        $this->actingAs($vehicule->partenaire->user)->putJson("/api/partenaire/vehicules/{$vehicule->id}", [...$champs, 'prix_vente' => 9000000])
            ->assertOk()
            ->assertJsonPath('data.mode_offre', 'location')
            ->assertJsonPath('data.prix_vente', null);
    }

    public function test_une_indisponibilite_ne_peut_pas_chevaucher_une_autre(): void
    {
        $vehicule = Vehicule::factory()->create();
        $this->actingAs($vehicule->partenaire->user);
        $url = "/api/partenaire/vehicules/{$vehicule->id}/indisponibilites";
        $debut = now()->addDays(5)->toDateString();
        $fin = now()->addDays(8)->toDateString();

        $this->postJson($url, ['date_debut' => $debut, 'date_fin' => $fin, 'motif' => 'Entretien'])->assertCreated();
        $this->postJson($url, ['date_debut' => now()->addDays(7)->toDateString(), 'date_fin' => now()->addDays(10)->toDateString()])
            ->assertUnprocessable()->assertJsonValidationErrors('date_debut');
        $this->postJson($url, ['date_debut' => $fin, 'date_fin' => $debut])
            ->assertUnprocessable()->assertJsonValidationErrors('date_fin');
    }

    public function test_un_vehicule_a_vendre_seulement_n_a_pas_de_calendrier(): void
    {
        $vehicule = Vehicule::factory()->vente()->create();

        $this->actingAs($vehicule->partenaire->user)
            ->postJson("/api/partenaire/vehicules/{$vehicule->id}/indisponibilites", ['date_debut' => now()->addDay()->toDateString(), 'date_fin' => now()->addDays(2)->toDateString()])
            ->assertUnprocessable();
    }
}
