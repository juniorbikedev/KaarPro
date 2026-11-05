<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreeDesTransactions;
use Tests\TestCase;

class AttributionMissionTest extends TestCase
{
    use CreeDesTransactions, RefreshDatabase;

    public function test_l_administrateur_confie_une_mission_avec_une_consigne(): void
    {
        $agent = User::factory()->role(Role::Agent)->create(['zone' => 'Dakar Plateau']);
        $mission = $this->demandePayee()->etatsDesLieux()->orderBy('id')->first();
        $admin = User::factory()->role(Role::Admin)->create();

        $this->actingAs($admin)->getJson('/api/admin/missions?statut=a_attribuer')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('agents.0.zone', 'Dakar Plateau')
            ->assertJsonPath('agents.0.missions_en_cours', 0);

        $this->postJson("/api/admin/missions/{$mission->id}/attribuer", ['agent_id' => $agent->id, 'consigne' => 'Vérifier la roue de secours.'])
            ->assertOk()
            ->assertJsonPath('data.statut', 'a_faire')
            ->assertJsonPath('data.agent.id', $agent->id)
            ->assertJsonPath('data.consigne', 'Vérifier la roue de secours.')
            ->assertJsonPath('data.confiee_par_admin', true);

        $this->assertContains('Mission confiée', $agent->notifications->pluck('data.titre'));

        // L'agent la retrouve dans ses missions, avec la consigne
        $this->actingAs($agent)->getJson('/api/agent/missions?filtre=miennes')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.consigne', 'Vérifier la roue de secours.');
    }

    public function test_reattribuer_previent_l_ancien_et_le_nouvel_agent(): void
    {
        [$premier, $second] = User::factory()->role(Role::Agent)->count(2)->create();
        $mission = $this->demandePayee()->etatsDesLieux()->first();
        $admin = User::factory()->role(Role::Admin)->create();

        $this->actingAs($premier)->postJson("/api/agent/missions/{$mission->id}/prendre")->assertOk();
        $this->actingAs($admin)->postJson("/api/admin/missions/{$mission->id}/attribuer", ['agent_id' => $second->id])
            ->assertOk()
            ->assertJsonPath('data.agent.id', $second->id);

        $this->assertContains('Mission réattribuée', $premier->notifications->pluck('data.titre'));
        $this->actingAs($premier)->getJson('/api/agent/missions?filtre=miennes')->assertJsonCount(0, 'data');
    }

    public function test_seul_un_agent_actif_peut_recevoir_une_mission_non_realisee(): void
    {
        $client = User::factory()->role(Role::Client)->create();
        $inactif = User::factory()->role(Role::Agent)->inactif()->create();
        $mission = $this->demandePayee()->etatsDesLieux()->first();
        $admin = User::factory()->role(Role::Admin)->create();

        $this->actingAs($admin)->postJson("/api/admin/missions/{$mission->id}/attribuer", ['agent_id' => $client->id])
            ->assertJsonValidationErrors('agent_id');
        $this->postJson("/api/admin/missions/{$mission->id}/attribuer", ['agent_id' => $inactif->id])
            ->assertJsonValidationErrors('agent_id');

        $mission->update(['realise_le' => now()]);
        $agent = User::factory()->role(Role::Agent)->create();
        $this->postJson("/api/admin/missions/{$mission->id}/attribuer", ['agent_id' => $agent->id])->assertStatus(422);

        // Un agent ne s'attribue pas de missions par cette route
        $this->actingAs($agent)->getJson('/api/admin/missions')->assertForbidden();
    }
}
