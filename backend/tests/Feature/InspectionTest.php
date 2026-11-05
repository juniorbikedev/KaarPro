<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TypeEtatDesLieux;
use App\Models\Document;
use App\Models\EtatDesLieux;
use App\Models\Signalement;
use App\Models\User;
use App\Models\Vehicule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InspectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function vehiculeAvecDocuments(): Vehicule
    {
        $vehicule = Vehicule::factory()->create();
        foreach (['carte_grise', 'assurance'] as $type) {
            Storage::disk('local')->put("documents/vehicules/{$vehicule->id}/{$type}.pdf", '%PDF');
            $vehicule->documents()->create(['type' => $type, 'chemin' => "documents/vehicules/{$vehicule->id}/{$type}.pdf", 'nom_original' => "{$type}.pdf"]);
        }

        return $vehicule;
    }

    private function inspection(Vehicule $vehicule, User $agent): EtatDesLieux
    {
        return $vehicule->etatsDesLieux()->create(['type' => TypeEtatDesLieux::Inspection, 'agent_id' => $agent->id]);
    }

    private function constat(array $points, array $extra = []): array
    {
        return [
            'kilometrage' => 61_000,
            'points_controle' => $points,
            'observations' => 'Véhicule présenté par le gérant.',
            'photos' => [UploadedFile::fake()->image('plaque.jpg'), UploadedFile::fake()->image('compteur.jpg')],
            ...$extra,
        ];
    }

    private const TOUT_CONFORME = ['immatriculation' => '1', 'carte_grise' => '1', 'assurance' => '1', 'annonce' => '1'];

    public function test_publier_un_vehicule_cree_une_inspection_et_previent_les_agents(): void
    {
        $agent = User::factory()->role(Role::Agent)->create();
        $vehicule = Vehicule::factory()->location()->create();
        $partenaire = $vehicule->partenaire;

        $this->actingAs($partenaire->user)->post('/api/partenaire/vehicules', [
            'immatriculation' => 'dk-7777-zz', 'marque' => 'Toyota', 'modele' => 'Yaris', 'annee' => 2022, 'categorie' => 'citadine',
            'carburant' => 'essence', 'boite' => 'manuelle', 'places' => 5, 'kilometrage' => 20000, 'ville' => 'Dakar',
            'mode_offre' => 'vente_et_location', 'prix_jour' => 20000, 'prix_vente' => 7_000_000, 'montant_caution' => 100000,
            'photos' => [UploadedFile::fake()->image('a.jpg')],
            'carte_grise' => UploadedFile::fake()->create('cg.pdf', 20, 'application/pdf'),
            'assurance' => UploadedFile::fake()->create('as.pdf', 20, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $nouveau = Vehicule::where('immatriculation', 'DK-7777-ZZ')->sole();
        $this->assertSame(1, $nouveau->etatsDesLieux()->where('type', 'inspection')->count());
        $this->assertContains('Nouvelle inspection', $agent->notifications->pluck('data.titre'));

        $this->actingAs($agent)->getJson('/api/agent/missions')
            ->assertJsonPath('data.0.type', 'inspection')
            ->assertJsonPath('data.0.vehicule.immatriculation', 'DK-7777-ZZ')
            ->assertJsonPath('data.0.demande', null);
    }

    public function test_une_inspection_conforme_donne_le_badge_verifie(): void
    {
        $agent = User::factory()->role(Role::Agent)->create();
        $vehicule = $this->vehiculeAvecDocuments();
        $inspection = $this->inspection($vehicule, $agent);

        // L'agent consulte les documents à comparer, par lien signé
        $url = $this->actingAs($agent)->getJson("/api/etats-des-lieux/{$inspection->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data.documents')
            ->assertJsonCount(4, 'data.points_controle')
            ->json('data.documents.0.url');
        $this->get($url)->assertOk();

        $this->post("/api/agent/missions/{$inspection->id}/realiser", $this->constat(self::TOUT_CONFORME), ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.conforme', true)
            ->assertJsonPath('data.points_controle.0.valeur', true);

        $this->assertNotNull($vehicule->refresh()->verifie_le);
        $this->getJson("/api/vehicules/{$vehicule->id}")->assertJsonPath('data.verifie_le', $vehicule->verifie_le->toIso8601String());
        $this->assertContains('Véhicule vérifié', $vehicule->partenaire->user->notifications->pluck('data.titre'));
        $this->assertSame(0, Signalement::count());
    }

    public function test_une_non_conformite_cree_un_signalement_pour_l_administrateur(): void
    {
        $agent = User::factory()->role(Role::Agent)->create();
        $admin = User::factory()->role(Role::Admin)->create();
        $vehicule = $this->vehiculeAvecDocuments();
        $inspection = $this->inspection($vehicule, $agent);
        $points = [...self::TOUT_CONFORME, 'assurance' => '0'];

        // La non-conformité doit être décrite
        $this->actingAs($agent)->post("/api/agent/missions/{$inspection->id}/realiser", $this->constat($points), ['Accept' => 'application/json'])
            ->assertJsonValidationErrors('dommages');

        $this->post("/api/agent/missions/{$inspection->id}/realiser", $this->constat($points, ['dommages' => 'Attestation d’assurance expirée depuis août.']), ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.conforme', false);

        $signalement = Signalement::sole();
        $this->assertSame($vehicule->id, $signalement->vehicule_id);
        $this->assertSame($agent->id, $signalement->auteur_id);
        $this->assertStringContainsString('assurance expirée', $signalement->description);
        $this->assertContains('Non-conformité relevée', $admin->notifications->pluck('data.titre'));
        $this->assertContains('Non-conformité relevée', $vehicule->partenaire->user->notifications->pluck('data.titre'));
        $this->assertNull($vehicule->refresh()->verifie_le);

        // Le partenaire consulte le constat, mais pas les documents par lien
        $this->actingAs($vehicule->partenaire->user)->getJson("/api/etats-des-lieux/{$inspection->id}")
            ->assertOk()
            ->assertJsonPath('data.documents', null);
    }

    public function test_l_administrateur_demande_une_inspection(): void
    {
        $agent = User::factory()->role(Role::Agent)->create();
        $admin = User::factory()->role(Role::Admin)->create();
        $vehicule = Vehicule::factory()->create();

        $this->actingAs($admin)->postJson("/api/admin/vehicules/{$vehicule->id}/inspection", ['agent_id' => $agent->id, 'consigne' => 'Vérifier le numéro de châssis.'])
            ->assertCreated()
            ->assertJsonPath('data.type', 'inspection')
            ->assertJsonPath('data.agent.id', $agent->id)
            ->assertJsonPath('data.consigne', 'Vérifier le numéro de châssis.');
        $this->assertContains('Mission confiée', $agent->notifications->pluck('data.titre'));

        $this->postJson("/api/admin/vehicules/{$vehicule->id}/inspection")->assertStatus(422);
        $this->actingAs($agent)->postJson("/api/admin/vehicules/{$vehicule->id}/inspection")->assertForbidden();
    }

    public function test_un_document_n_est_pas_accessible_sans_signature(): void
    {
        $vehicule = $this->vehiculeAvecDocuments();
        $this->get('/api/documents/'.Document::first()->id.'/fichier-signe')->assertForbidden();
    }
}
