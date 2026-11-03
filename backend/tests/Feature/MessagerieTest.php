<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use App\Models\Vehicule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessagerieTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_client_et_un_partenaire_echangent_des_messages(): void
    {
        $vehicule = Vehicule::factory()->create();
        $partenaire = $vehicule->partenaire->user;
        $client = User::factory()->role(Role::Client)->create();

        $id = $this->actingAs($client)->postJson("/api/vehicules/{$vehicule->id}/conversation")
            ->assertCreated()
            ->json('data.id');

        // Ouvrir de nouveau la conversation retrouve la même
        $this->postJson("/api/vehicules/{$vehicule->id}/conversation")->assertOk()->assertJsonPath('data.id', $id);

        $this->postJson("/api/conversations/{$id}/messages", ['contenu' => 'Le véhicule est-il climatisé ?'])
            ->assertOk()
            ->assertJsonPath('data.messages.0.de_moi', true);

        $this->actingAs($partenaire)->getJson('/api/conversations')
            ->assertOk()
            ->assertJsonPath('data.0.non_lus', 1)
            ->assertJsonPath('data.0.interlocuteur', $client->nom);

        // La lecture marque le message comme lu
        $this->getJson("/api/conversations/{$id}")->assertOk()->assertJsonPath('data.messages.0.de_moi', false);
        $this->getJson('/api/conversations')->assertJsonPath('data.0.non_lus', 0);

        $this->postJson("/api/conversations/{$id}/messages", ['contenu' => 'Oui, climatisation complète.'])
            ->assertOk()
            ->assertJsonCount(2, 'data.messages');
    }

    public function test_un_tiers_ne_voit_pas_la_conversation(): void
    {
        $vehicule = Vehicule::factory()->create();
        $client = User::factory()->role(Role::Client)->create();
        $id = $this->actingAs($client)->postJson("/api/vehicules/{$vehicule->id}/conversation")->json('data.id');

        $intrus = User::factory()->role(Role::Client)->create();
        $this->actingAs($intrus)->getJson("/api/conversations/{$id}")->assertNotFound();
        $this->postJson("/api/conversations/{$id}/messages", ['contenu' => 'Bonjour'])->assertNotFound();
        $this->getJson('/api/conversations')->assertJsonCount(0, 'data');
    }
}
