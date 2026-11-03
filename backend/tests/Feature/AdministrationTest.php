<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TypeDocument;
use App\Models\Partenaire;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdministrationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->role(Role::Admin)->create();
    }

    public function test_seul_l_administrateur_accede_aux_routes_d_administration(): void
    {
        $this->getJson('/api/admin/utilisateurs')->assertUnauthorized();

        foreach ([Role::Client, Role::Vendeur, Role::Agent] as $role) {
            $this->actingAs(User::factory()->role($role)->create())
                ->getJson('/api/admin/utilisateurs')
                ->assertForbidden();
        }

        $this->actingAs($this->admin())->getJson('/api/admin/utilisateurs')->assertOk();
    }

    public function test_l_administrateur_cree_un_compte_agent(): void
    {
        $this->actingAs($this->admin())->postJson('/api/admin/agents', [
            'nom' => 'Moussa Fall',
            'email' => 'moussa@kaarpro.test',
            'zone' => 'Pikine',
            'password' => 'motdepasse-agent',
            'password_confirmation' => 'motdepasse-agent',
        ])->assertCreated()->assertJsonPath('data.role', 'agent')->assertJsonPath('data.zone', 'Pikine');
    }

    public function test_l_administrateur_valide_un_partenaire_et_ses_documents(): void
    {
        $partenaire = Partenaire::factory()->create();
        $partenaire->documents()->create(['type' => TypeDocument::PieceIdentite, 'chemin' => 'x.pdf', 'nom_original' => 'cni.pdf']);

        $this->actingAs($this->admin())->getJson('/api/admin/partenaires?statut=en_attente')
            ->assertOk()->assertJsonCount(1, 'data');

        $this->postJson("/api/admin/partenaires/{$partenaire->id}/valider")
            ->assertOk()
            ->assertJsonPath('data.statut_validation', 'valide')
            ->assertJsonPath('data.documents.0.est_verifie', true);
    }

    public function test_un_refus_exige_un_motif(): void
    {
        $partenaire = Partenaire::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin)->postJson("/api/admin/partenaires/{$partenaire->id}/refuser")
            ->assertUnprocessable()->assertJsonValidationErrors('motif');

        $this->postJson("/api/admin/partenaires/{$partenaire->id}/refuser", ['motif' => 'Pièce illisible'])
            ->assertOk()->assertJsonPath('data.statut_validation', 'refuse')->assertJsonPath('data.motif_refus', 'Pièce illisible');
    }

    public function test_l_administrateur_telecharge_un_document(): void
    {
        Storage::fake('local');
        $partenaire = Partenaire::factory()->create();
        $chemin = UploadedFile::fake()->create('cni.pdf', 10, 'application/pdf')->store('documents', 'local');
        $document = $partenaire->documents()->create(['type' => TypeDocument::PieceIdentite, 'chemin' => $chemin, 'nom_original' => 'cni.pdf']);

        $this->actingAs($this->admin())->get("/api/admin/documents/{$document->id}/fichier")->assertOk();
    }

    public function test_desactiver_un_compte_supprime_ses_jetons(): void
    {
        $client = User::factory()->create();
        $client->createToken('kaarpro');

        $this->actingAs($this->admin())->patchJson("/api/admin/utilisateurs/{$client->id}/activation", ['est_actif' => false])
            ->assertOk()->assertJsonPath('data.est_actif', false);

        $this->assertSame(0, $client->tokens()->count());
    }

    public function test_l_administrateur_ne_peut_pas_desactiver_son_propre_compte(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->patchJson("/api/admin/utilisateurs/{$admin->id}/activation", ['est_actif' => false])
            ->assertUnprocessable();
    }
}
