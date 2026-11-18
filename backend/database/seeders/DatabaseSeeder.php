<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\StatutDemande;
use App\Enums\StatutValidation;
use App\Enums\TypeActivite;
use App\Enums\TypeDemande;
use App\Enums\TypeDocument;
use App\Models\Conversation;
use App\Models\Demande;
use App\Models\Partenaire;
use App\Models\User;
use App\Models\Vehicule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

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

        // Quelques véhicules récents pour que le catalogue ne soit pas vide. Leurs photos (licence libre,
        // Wikimedia Commons) sont dans database/seeders/photos, avec les crédits dans CREDITS.md.
        $vehicules = [
            [$locationAuto, 'DK-2201-AA', 'Mercedes-Benz', 'Classe C', 2023, 'berline', 'diesel', 'automatique', 5, 18000, 'Dakar', 'location', 65000, null, 400000, 'mercedes-classe-c'],
            [$locationAuto, 'DK-2202-AB', 'Hyundai', 'Tucson', 2023, 'suv', 'diesel', 'automatique', 5, 24000, 'Dakar', 'location', 45000, null, 250000, 'hyundai-tucson'],
            [$locationAuto, 'TH-3101-AC', 'Mercedes-Benz', 'Classe V', 2024, 'minibus', 'diesel', 'automatique', 8, 12000, 'Thiès', 'location', 90000, null, 500000, 'mercedes-classe-v'],
            [$prestige, 'DK-5501-BA', 'Land Rover', 'Range Rover Sport', 2023, 'suv', 'essence', 'automatique', 5, 15000, 'Dakar', 'vente', null, 75000000, null, 'range-rover-sport'],
            [$prestige, 'DK-5502-BB', 'Toyota', 'Land Cruiser 300', 2022, '4x4', 'diesel', 'automatique', 7, 35000, 'Dakar', 'vente_et_location', 120000, 62000000, 600000, 'toyota-land-cruiser-300'],
            [$prestige, 'MB-5503-BC', 'Peugeot', '408', 2023, 'berline', 'essence', 'automatique', 5, 21000, 'Mbour', 'vente_et_location', 45000, 22500000, 250000, 'peugeot-408'],
        ];

        // Parc de TerangaAuto : véhicules de l'entreprise, gérés par l'administrateur et sans commission
        $administrateur = User::where('role', Role::Admin)->oldest('id')->first();
        $parc = $administrateur ? Partenaire::interne($administrateur) : null;
        if ($parc) {
            array_push($vehicules,
                [$parc, 'DK-7001-TA', 'Toyota', 'RAV4 hybride rechargeable', 2024, 'suv', 'hybride', 'automatique', 5, 9000, 'Dakar', 'vente_et_location', 60000, 38000000, 300000, 'toyota-rav4'],
                [$parc, 'DK-7002-TA', 'Kia', 'Sportage', 2023, 'suv', 'essence', 'automatique', 5, 16000, 'Dakar', 'location', 50000, null, 250000, 'kia-sportage'],
                [$parc, 'DK-7003-TA', 'Hyundai', 'Santa Fe', 2024, 'suv', 'hybride', 'automatique', 7, 7000, 'Dakar', 'vente_et_location', 70000, 42000000, 350000, 'hyundai-santa-fe'],
                [$parc, 'DK-7004-TA', 'Toyota', 'Land Cruiser Prado 250', 2025, '4x4', 'diesel', 'automatique', 7, 3000, 'Dakar', 'vente', null, 58000000, null, 'toyota-land-cruiser-prado'],
                [$parc, 'TH-7005-TA', 'Toyota', 'Yaris Cross hybride', 2023, 'citadine', 'hybride', 'automatique', 5, 20000, 'Thiès', 'location', 35000, null, 150000, 'toyota-yaris-cross'],
                [$parc, 'DK-7006-TA', 'Toyota', 'Hilux GR Sport', 2024, 'utilitaire', 'diesel', 'automatique', 5, 11000, 'Dakar', 'vente_et_location', 65000, 32000000, 300000, 'toyota-hilux'],
            );
        }
        foreach ($vehicules as [$partenaire, $immat, $marque, $modele, $annee, $categorie, $carburant, $boite, $places, $km, $ville, $mode, $jour, $vente, $caution, $photos]) {
            $vehicule = $partenaire->vehicules()->updateOrCreate(['immatriculation' => $immat], [
                'marque' => $marque, 'modele' => $modele, 'annee' => $annee, 'categorie' => $categorie,
                'carburant' => $carburant, 'boite' => $boite, 'places' => $places, 'kilometrage' => $km,
                'ville' => $ville, 'mode_offre' => $mode, 'prix_jour' => $jour, 'prix_vente' => $vente,
                'montant_caution' => $caution,
                'description' => $partenaire->est_interne
                    ? "{$marque} {$modele} de {$annee}, du parc TerangaAuto : entretenu dans nos ateliers, assistance incluse. Véhicule de démonstration."
                    : "{$marque} {$modele} de {$annee}, entretenu régulièrement. Véhicule de démonstration.",
            ]);
            $this->photos($vehicule, $photos);
            $this->documents($vehicule);
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

    /**
     * Carte grise et assurance d'un véhicule de démonstration, comme pour une vraie publication. Ce sont des PDF
     * d'une page marqués « document de démonstration », vérifiés d'office puisque le partenaire est validé.
     */
    private function documents(Vehicule $vehicule): void
    {
        foreach ([TypeDocument::CarteGrise, TypeDocument::Assurance] as $type) {
            if ($vehicule->documents()->where('type', $type)->exists()) {
                continue;
            }
            $libelle = $type === TypeDocument::CarteGrise ? 'Carte grise' : "Attestation d'assurance";
            $nom = str_replace('_', '-', $type->value).'.pdf';
            $chemin = "documents/vehicules/{$vehicule->id}/{$nom}";
            Storage::disk('local')->put($chemin, $this->pdf("{$libelle} - {$vehicule->marque} {$vehicule->modele} - {$vehicule->immatriculation}"));
            $vehicule->documents()->create(['type' => $type, 'chemin' => $chemin, 'nom_original' => $nom, 'est_verifie' => true]);
        }
    }

    /** PDF minimal d'une page portant un titre et la mention « document de démonstration ». */
    private function pdf(string $titre): string
    {
        $texte = fn (string $t) => str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], mb_convert_encoding($t, 'Windows-1252', 'UTF-8'));
        $contenu = "BT /F1 16 Tf 60 760 Td ({$texte($titre)}) Tj ET\nBT /F1 11 Tf 60 735 Td ({$texte('Document de démonstration KaarPro, sans valeur légale.')}) Tj ET";
        $objets = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($contenu)." >>\nstream\n{$contenu}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        ];
        $pdf = "%PDF-1.4\n";
        $positions = [];
        foreach ($objets as $i => $objet) {
            $positions[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$objet}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref'."\n0 ".(count($objets) + 1)."\n0000000000 65535 f \n";
        foreach ($positions as $position) {
            $pdf .= sprintf("%010d 00000 n \n", $position);
        }

        return $pdf.'trailer << /Size '.(count($objets) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }

    /** Remplace les photos d'un véhicule de démonstration par celles du dossier seeders/photos. */
    private function photos(Vehicule $vehicule, string $prefixe): void
    {
        $fichiers = glob(__DIR__."/photos/{$prefixe}-*.jpg") ?: [];
        sort($fichiers);
        if ($fichiers === []) {
            return;
        }

        foreach ($vehicule->photos as $ancienne) {
            Storage::disk('local')->delete($ancienne->chemin);
            $ancienne->delete();
        }
        foreach ($fichiers as $ordre => $fichier) {
            $chemin = "vehicules/{$vehicule->id}/".basename($fichier);
            Storage::disk('local')->put($chemin, file_get_contents($fichier));
            $vehicule->photos()->create(['chemin' => $chemin, 'ordre' => $ordre + 1]);
        }
    }
}
