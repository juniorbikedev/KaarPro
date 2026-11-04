<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\StatutDemande;
use App\Enums\StatutValidation;
use App\Enums\TypeActivite;
use App\Enums\TypeDemande;
use App\Models\Conversation;
use App\Models\Demande;
use App\Models\User;
use App\Models\Vehicule;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Comptes de démonstration, un par rôle. Les e-mails et mots de passe viennent du fichier .env
     * (voir .env.example) : ce sont des valeurs de test, à ne jamais utiliser en production.
     */
    public function run(): void
    {
        $demo = env('DEMO_PASSWORD', 'kaarpro-demo-2026');

        $this->call(AdministrateurSeeder::class);

        User::updateOrCreate(['email' => 'agent@kaarpro.test'], [
            'nom' => 'Agent Dakar Plateau',
            'telephone' => '77 000 00 01',
            'password' => $demo,
            'role' => Role::Agent,
            'zone' => 'Dakar Plateau',
        ]);

        $client = User::updateOrCreate(['email' => 'client@kaarpro.test'], [
            'nom' => 'Client de démonstration',
            'telephone' => '77 000 00 02',
            'password' => $demo,
            'role' => Role::Client,
        ]);

        $loueur = User::updateOrCreate(['email' => 'loueur@kaarpro.test'], [
            'nom' => 'Loueur de démonstration',
            'telephone' => '77 000 00 03',
            'password' => $demo,
            'role' => Role::Vendeur,
        ]);
        $locationAuto = $loueur->partenaire()->updateOrCreate([], [
            'nom_commercial' => 'Dakar Location Auto',
            'type_activite' => TypeActivite::Location,
            'statut_validation' => StatutValidation::Valide,
            'valide_le' => now(),
        ]);

        $vendeur = User::updateOrCreate(['email' => 'vendeur@kaarpro.test'], [
            'nom' => 'Vendeur de démonstration',
            'telephone' => '77 000 00 04',
            'password' => $demo,
            'role' => Role::Vendeur,
        ]);
        $prestige = $vendeur->partenaire()->updateOrCreate([], [
            'nom_commercial' => 'Auto Prestige Dakar',
            'type_activite' => TypeActivite::VenteEtLocation,
            'statut_validation' => StatutValidation::Valide,
            'valide_le' => now(),
        ]);

        // Quelques véhicules pour que le catalogue ne soit pas vide (sans photos : l'interface affiche une illustration)
        $vehicules = [
            [$locationAuto, 'DK-2201-AA', 'Toyota', 'Corolla', 2021, 'berline', 'essence', 'automatique', 5, 42000, 'Dakar', 'location', 30000, null, 150000],
            [$locationAuto, 'DK-2202-AB', 'Hyundai', 'Tucson', 2022, 'suv', 'diesel', 'automatique', 5, 31000, 'Dakar', 'location', 45000, null, 250000],
            [$locationAuto, 'TH-3101-AC', 'Toyota', 'Hiace', 2019, 'minibus', 'diesel', 'manuelle', 15, 98000, 'Thiès', 'location', 70000, null, 300000],
            [$prestige, 'DK-5501-BA', 'Kia', 'Picanto', 2020, 'citadine', 'essence', 'manuelle', 4, 56000, 'Dakar', 'vente', null, 5900000, null],
            [$prestige, 'DK-5502-BB', 'Toyota', 'Land Cruiser', 2018, '4x4', 'diesel', 'automatique', 7, 120000, 'Dakar', 'vente_et_location', 90000, 28500000, 400000],
            [$prestige, 'MB-5503-BC', 'Peugeot', '3008', 2021, 'suv', 'essence', 'automatique', 5, 38000, 'Mbour', 'vente_et_location', 40000, 14900000, 200000],
        ];
        foreach ($vehicules as [$partenaire, $immat, $marque, $modele, $annee, $categorie, $carburant, $boite, $places, $km, $ville, $mode, $jour, $vente, $caution]) {
            $partenaire->vehicules()->updateOrCreate(['immatriculation' => $immat], [
                'marque' => $marque, 'modele' => $modele, 'annee' => $annee, 'categorie' => $categorie,
                'carburant' => $carburant, 'boite' => $boite, 'places' => $places, 'kilometrage' => $km,
                'ville' => $ville, 'mode_offre' => $mode, 'prix_jour' => $jour, 'prix_vente' => $vente,
                'montant_caution' => $caution,
                'description' => "{$marque} {$modele} de {$annee}, entretenu régulièrement. Véhicule de démonstration.",
            ]);
        }

        // Une demande de location en attente et une conversation, pour essayer le traitement côté loueur
        $tucson = Vehicule::where('immatriculation', 'DK-2202-AB')->first();
        $debut = now()->addWeek()->startOfDay();
        Demande::firstOrCreate(
            ['client_id' => $client->id, 'vehicule_id' => $tucson->id, 'type' => TypeDemande::Location, 'statut' => StatutDemande::EnAttente],
            [
                'partenaire_id' => $tucson->partenaire_id,
                'date_debut' => $debut->toDateString(),
                'date_fin' => $debut->copy()->addDays(2)->toDateString(),
                'nb_jours' => 3,
                'montant' => 3 * $tucson->prix_jour,
                'montant_caution' => $tucson->montant_caution,
                'message' => 'Bonjour, je souhaite louer le Tucson pour un déplacement à Saint-Louis.',
            ],
        );
        $conversation = Conversation::firstOrCreate(
            ['client_id' => $client->id, 'vehicule_id' => $tucson->id],
            ['partenaire_id' => $tucson->partenaire_id],
        );
        if ($conversation->wasRecentlyCreated) {
            $conversation->messages()->create([
                'auteur_id' => $client->id,
                'contenu' => 'Bonjour, le véhicule peut-il être livré à Dakar Plateau ?',
            ]);
        }
    }
}
