<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\StatutDemande;
use App\Enums\TypeActivite;
use App\Models\Demande;
use App\Models\User;
use App\Models\Vehicule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemandeTest extends TestCase
{
    use RefreshDatabase;

    private function client(): User
    {
        return User::factory()->role(Role::Client)->create();
    }

    private function jour(int $decalage): string
    {
        return now()->addDays($decalage)->toDateString();
    }

    public function test_un_client_envoie_une_demande_de_location_et_le_montant_est_calcule(): void
    {
        $vehicule = Vehicule::factory()->location()->create(['prix_jour' => 25_000, 'montant_caution' => 100_000]);

        $this->actingAs($this->client())->postJson("/api/vehicules/{$vehicule->id}/demandes", [
            'type' => 'location',
            'date_debut' => $this->jour(3),
            'date_fin' => $this->jour(5),
        ])
            ->assertCreated()
            ->assertJsonPath('data.statut', 'en_attente')
            ->assertJsonPath('data.nb_jours', 3)
            ->assertJsonPath('data.montant', 75_000)
            ->assertJsonPath('data.montant_caution', 100_000);
    }

    public function test_la_demande_doit_correspondre_a_l_offre_et_a_une_periode_libre(): void
    {
        $client = $this->client();
        $aVendre = Vehicule::factory()->vente()->create();
        $aLouer = Vehicule::factory()->location()->create();
        $aLouer->indisponibilites()->create(['date_debut' => $this->jour(4), 'date_fin' => $this->jour(6)]);

        $this->actingAs($client)->postJson("/api/vehicules/{$aVendre->id}/demandes", [
            'type' => 'location', 'date_debut' => $this->jour(1), 'date_fin' => $this->jour(2),
        ])->assertJsonValidationErrors('type');

        $this->postJson("/api/vehicules/{$aLouer->id}/demandes", [
            'type' => 'location', 'date_debut' => $this->jour(5), 'date_fin' => $this->jour(8),
        ])->assertJsonValidationErrors('date_debut');

        $this->postJson("/api/vehicules/{$aLouer->id}/demandes", ['type' => 'location'])
            ->assertJsonValidationErrors(['date_debut', 'date_fin']);
    }

    public function test_seul_un_client_peut_envoyer_une_demande(): void
    {
        $vehicule = Vehicule::factory()->create();
        $autrePartenaire = Vehicule::factory()->create()->partenaire->user;

        $this->actingAs($autrePartenaire)->postJson("/api/vehicules/{$vehicule->id}/demandes", ['type' => 'achat'])
            ->assertForbidden();
    }

    public function test_accepter_une_location_bloque_le_calendrier_genere_le_contrat_et_refuse_les_demandes_concurrentes(): void
    {
        $vehicule = Vehicule::factory()->location()->create();
        $demande = Demande::factory()->for($vehicule)->create([
            'partenaire_id' => $vehicule->partenaire_id,
            'date_debut' => $this->jour(5), 'date_fin' => $this->jour(7),
        ]);
        $concurrente = Demande::factory()->for($vehicule)->create([
            'partenaire_id' => $vehicule->partenaire_id,
            'date_debut' => $this->jour(6), 'date_fin' => $this->jour(9),
        ]);

        $this->actingAs($vehicule->partenaire->user)->postJson("/api/partenaire/demandes/{$demande->id}/accepter")
            ->assertOk()
            ->assertJsonPath('data.statut', 'acceptee')
            ->assertJsonPath('data.client.email', $demande->client->email);

        $indisponibilite = $vehicule->indisponibilites()->sole();
        $this->assertSame($demande->id, $indisponibilite->demande_id);
        $this->assertSame(StatutDemande::Refusee, $concurrente->refresh()->statut);

        $contrat = $demande->refresh()->contrat;
        $this->assertStringStartsWith('KP-', $contrat->numero);
        $this->assertNotNull($contrat->signe_partenaire_le);
        $this->assertSame($vehicule->immatriculation, $contrat->donnees['vehicule']['immatriculation']);

        // La période réservée ne peut pas être libérée depuis le calendrier
        $this->deleteJson("/api/partenaire/vehicules/{$vehicule->id}/indisponibilites/{$indisponibilite->id}")
            ->assertStatus(422);
    }

    public function test_le_partenaire_refuse_avec_un_motif_et_ne_traite_que_ses_demandes(): void
    {
        $demande = Demande::factory()->create();
        $partenaire = $demande->partenaire->user;
        $autre = Vehicule::factory()->create()->partenaire->user;

        $this->actingAs($autre)->postJson("/api/partenaire/demandes/{$demande->id}/refuser", ['motif' => 'Non'])
            ->assertNotFound();

        $this->actingAs($partenaire)->postJson("/api/partenaire/demandes/{$demande->id}/refuser")
            ->assertJsonValidationErrors('motif');

        $this->postJson("/api/partenaire/demandes/{$demande->id}/refuser", ['motif' => 'Véhicule en révision'])
            ->assertOk()
            ->assertJsonPath('data.statut', 'refusee')
            ->assertJsonPath('data.motif_refus', 'Véhicule en révision');

        $this->postJson("/api/partenaire/demandes/{$demande->id}/accepter")->assertStatus(422);
    }

    public function test_le_client_annule_une_demande_en_attente_et_suit_ses_demandes(): void
    {
        $demande = Demande::factory()->create();
        $client = $demande->client;
        Demande::factory()->create(); // demande d'un autre client

        $this->actingAs($client)->getJson('/api/demandes')->assertOk()->assertJsonCount(1, 'data');

        $this->postJson("/api/demandes/{$demande->id}/annuler")
            ->assertOk()
            ->assertJsonPath('data.statut', 'annulee');

        $this->postJson("/api/demandes/{$demande->id}/annuler")->assertStatus(422);
    }

    public function test_le_client_signe_le_contrat_d_un_achat_accepte(): void
    {
        $vehicule = Vehicule::factory()->create(['mode_offre' => TypeActivite::VenteEtLocation]);
        $client = $this->client();

        $this->actingAs($client)->postJson("/api/vehicules/{$vehicule->id}/demandes", ['type' => 'achat'])
            ->assertCreated()
            ->assertJsonPath('data.montant', $vehicule->prix_vente);
        $demande = Demande::sole();

        $this->actingAs($vehicule->partenaire->user)->postJson("/api/partenaire/demandes/{$demande->id}/accepter")->assertOk();
        $contrat = $demande->refresh()->contrat;

        $this->actingAs($client)->getJson("/api/contrats/{$contrat->id}")
            ->assertOk()
            ->assertJsonPath('data.statut', 'en_attente_signature')
            ->assertJsonPath('data.type', 'achat');

        $this->postJson("/api/contrats/{$contrat->id}/signer")->assertJsonValidationErrors('accepte');
        $this->postJson("/api/contrats/{$contrat->id}/signer", ['accepte' => true])
            ->assertOk()
            ->assertJsonPath('data.statut', 'signe');

        $this->actingAs($this->client())->getJson("/api/contrats/{$contrat->id}")->assertNotFound();
    }
}
