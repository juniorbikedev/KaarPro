<?php

namespace Tests\Feature;

use App\Enums\EtatVehicule;
use App\Enums\Role;
use App\Models\EtatDesLieux;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreeDesTransactions;
use Tests\TestCase;

class EtatDesLieuxTest extends TestCase
{
    use CreeDesTransactions, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function constat(int $kilometrage, array $extra = []): array
    {
        return [
            'kilometrage' => $kilometrage,
            'niveau_carburant' => 'plein',
            'observations' => 'Carrosserie propre.',
            'photos' => [UploadedFile::fake()->image('avant.jpg'), UploadedFile::fake()->image('arriere.jpg')],
            ...$extra,
        ];
    }

    public function test_le_paiement_cree_les_missions_et_previent_les_agents(): void
    {
        $agent = User::factory()->role(Role::Agent)->create();
        $demande = $this->demandePayee();

        $this->assertSame(['depart', 'retour'], $demande->etatsDesLieux()->orderBy('id')->get()->pluck('type')->map->value->all());
        $this->assertSame('Nouvelle mission', $agent->notifications()->first()->data['titre']);

        $this->actingAs($agent)->getJson('/api/agent/missions')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.statut', 'a_attribuer');
    }

    public function test_un_agent_realise_le_depart_puis_le_retour(): void
    {
        $agent = User::factory()->role(Role::Agent)->create();
        $demande = $this->demandePayee(['montant_caution' => 100_000]);
        $vehicule = $demande->vehicule;
        [$depart, $retour] = $demande->etatsDesLieux()->orderBy('id')->get();

        $this->actingAs($agent)->postJson("/api/agent/missions/{$depart->id}/prendre")->assertOk()->assertJsonPath('data.statut', 'a_faire');
        $this->postJson("/api/agent/missions/{$retour->id}/prendre")->assertOk();

        // Le retour attend le départ
        $this->post("/api/agent/missions/{$retour->id}/realiser", $this->constat($vehicule->kilometrage + 500), ['Accept' => 'application/json'])
            ->assertJsonValidationErrors('type');

        // Le kilométrage ne peut pas baisser, et les photos sont obligatoires
        $this->post("/api/agent/missions/{$depart->id}/realiser", ['kilometrage' => $vehicule->kilometrage - 1, 'niveau_carburant' => 'plein'], ['Accept' => 'application/json'])
            ->assertJsonValidationErrors(['kilometrage', 'photos']);

        $this->post("/api/agent/missions/{$depart->id}/realiser", $this->constat($vehicule->kilometrage + 10), ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.statut', 'realise')
            ->assertJsonCount(2, 'data.photos');
        $this->assertSame(EtatVehicule::EnLocation, $vehicule->refresh()->etat);

        // Le retour présente le constat de départ, pour comparer
        $this->getJson("/api/etats-des-lieux/{$retour->id}")
            ->assertJsonPath('data.depart.kilometrage', $vehicule->kilometrage)
            ->assertJsonCount(2, 'data.depart.photos')
            ->assertJsonPath('data.caution.statut', 'bloquee');

        // L'agent doit statuer sur la caution, et motiver une retenue
        $this->post("/api/agent/missions/{$retour->id}/realiser", $this->constat($vehicule->kilometrage + 640), ['Accept' => 'application/json'])
            ->assertJsonValidationErrors('decision_caution');
        $this->post("/api/agent/missions/{$retour->id}/realiser", $this->constat($vehicule->kilometrage + 640, ['decision_caution' => 'retenir']), ['Accept' => 'application/json'])
            ->assertJsonValidationErrors(['dommages', 'montant_dommages']);
        $this->post("/api/agent/missions/{$retour->id}/realiser", $this->constat($vehicule->kilometrage - 5), ['Accept' => 'application/json'])
            ->assertJsonValidationErrors('kilometrage');

        $this->post("/api/agent/missions/{$retour->id}/realiser", $this->constat($vehicule->kilometrage + 640, [
            'dommages' => 'Rayure sur la portière arrière gauche.',
            'decision_caution' => 'retenir',
            'montant_dommages' => 30_000,
        ]), ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.montant_dommages', 30_000)
            ->assertJsonPath('data.caution.statut', 'restituee')
            ->assertJsonPath('data.caution.retenue', 30_000);

        $vehicule->refresh();
        $this->assertSame(EtatVehicule::Disponible, $vehicule->etat);
        $this->assertSame($retour->refresh()->kilometrage, $vehicule->kilometrage);
        $titres = $demande->client->notifications->pluck('data.titre');
        $this->assertTrue($titres->contains('État des lieux de retour'));
        $this->assertTrue($titres->contains('Caution restituée'));
    }

    public function test_sans_dommage_l_agent_restitue_toute_la_caution(): void
    {
        $agent = User::factory()->role(Role::Agent)->create();
        $demande = $this->demandePayee(['montant_caution' => 100_000]);
        [$depart, $retour] = $demande->etatsDesLieux()->orderBy('id')->get();
        $depart->update(['agent_id' => $agent->id, 'kilometrage' => 50_000, 'niveau_carburant' => 'plein', 'realise_le' => now()]);
        $retour->update(['agent_id' => $agent->id]);

        $this->actingAs($agent)->post("/api/agent/missions/{$retour->id}/realiser", $this->constat(50_300, ['decision_caution' => 'restituer']), ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.caution.retenue', 0)
            ->assertJsonPath('data.montant_dommages', 0);

        $this->assertSame('restituee', $demande->paiementReussi->statut_caution);
    }

    public function test_une_mission_prise_par_un_agent_n_est_pas_modifiable_par_un_autre(): void
    {
        $agent = User::factory()->role(Role::Agent)->create();
        $autre = User::factory()->role(Role::Agent)->create();
        $mission = $this->demandePayee()->etatsDesLieux()->first();

        $this->actingAs($agent)->postJson("/api/agent/missions/{$mission->id}/prendre")->assertOk();
        $this->actingAs($autre)->postJson("/api/agent/missions/{$mission->id}/prendre")->assertStatus(422);
        $this->post("/api/agent/missions/{$mission->id}/realiser", $this->constat(999_999), ['Accept' => 'application/json'])->assertNotFound();

        $client = User::factory()->role(Role::Client)->create();
        $this->actingAs($client)->getJson('/api/agent/missions')->assertForbidden();
    }

    public function test_le_constat_et_ses_photos_sont_visibles_par_les_parties_seulement(): void
    {
        $agent = User::factory()->role(Role::Agent)->create();
        $demande = $this->demandePayee();
        $depart = $demande->etatsDesLieux()->orderBy('id')->first();
        $this->actingAs($agent)->postJson("/api/agent/missions/{$depart->id}/prendre");
        $this->post("/api/agent/missions/{$depart->id}/realiser", $this->constat($demande->vehicule->kilometrage), ['Accept' => 'application/json'])->assertOk();

        $url = $this->actingAs($demande->client)->getJson("/api/etats-des-lieux/{$depart->id}")
            ->assertOk()
            ->assertJsonPath('data.niveau_carburant', 'plein')
            ->json('data.photos.0.url');
        $this->actingAs($demande->partenaire->user)->getJson("/api/etats-des-lieux/{$depart->id}")->assertOk();
        $this->actingAs(User::factory()->role(Role::Client)->create())->getJson("/api/etats-des-lieux/{$depart->id}")->assertNotFound();

        // Le lien signé fonctionne sans jeton ; modifié, il est refusé
        $this->get($url)->assertOk();
        $this->get(str_replace('signature=', 'signature=x', $url))->assertForbidden();
        $this->assertSame(2, EtatDesLieux::find($depart->id)->photos()->count());
    }
}
