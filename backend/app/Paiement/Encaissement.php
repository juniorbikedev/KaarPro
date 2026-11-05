<?php

namespace App\Paiement;

use App\Enums\EtatVehicule;
use App\Enums\Role;
use App\Enums\StatutDemande;
use App\Enums\StatutPaiement;
use App\Enums\TypeDemande;
use App\Enums\TypeEtatDesLieux;
use App\Models\Demande;
use App\Models\Paiement;
use App\Models\User;
use App\Notifications\Alerte;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/** Règles d'argent de la plateforme : calcul de la commission, ouverture et confirmation d'un paiement. */
class Encaissement
{
    public function __construct(private PasserellePaiement $passerelle) {}

    /** Prépare la tentative de paiement d'une demande acceptée et renvoie l'adresse de la page de l'opérateur. */
    public function ouvrir(Demande $demande, string $operateur, string $telephone): array
    {
        $location = $demande->type === TypeDemande::Location;
        $taux = (float) config('kaarpro.commission.'.($location ? 'location' : 'vente'));
        $commission = (int) round($demande->montant * $taux / 100);
        $caution = $location ? (int) $demande->montant_caution : 0;

        $paiement = $demande->paiements()->create([
            'client_id' => $demande->client_id,
            'reference' => 'KP-PAY-'.strtoupper(Str::random(10)),
            'operateur' => $operateur,
            'telephone' => $telephone,
            'montant' => $demande->montant,
            'montant_caution' => $caution,
            'montant_total' => $demande->montant + $caution,
            'taux_commission' => $taux,
            'commission' => $commission,
            'montant_partenaire' => $demande->montant - $commission,
            'statut' => StatutPaiement::EnAttente,
        ]);

        try {
            return [$paiement, $this->passerelle->initier($paiement)];
        } catch (\Throwable $erreur) {
            // La tentative n'a pas pu être ouverte chez l'opérateur : le client pourra réessayer
            $paiement->update(['statut' => StatutPaiement::Echoue]);
            throw $erreur;
        }
    }

    /** Résultat transmis par l'opérateur (ou par le simulateur). */
    public function confirmer(Paiement $paiement, bool $reussi, ?string $referenceOperateur = null): Paiement
    {
        if ($paiement->statut !== StatutPaiement::EnAttente) {
            return $paiement; // une confirmation reçue deux fois n'a pas d'effet
        }

        if (! $reussi) {
            $paiement->update(['statut' => StatutPaiement::Echoue, 'reference_operateur' => $referenceOperateur]);

            return $paiement;
        }

        DB::transaction(function () use ($paiement, $referenceOperateur) {
            $paiement->update([
                'statut' => StatutPaiement::Reussi,
                'reference_operateur' => $referenceOperateur,
                'paye_le' => now(),
                'statut_caution' => $paiement->montant_caution > 0 ? Paiement::CAUTION_BLOQUEE : null,
            ]);

            $demande = $paiement->demande;
            $demande->update(['statut' => StatutDemande::Payee]);

            // Missions confiées aux agents : départ et retour pour une location, contrôle avant remise pour une vente
            $types = $demande->type === TypeDemande::Location
                ? [TypeEtatDesLieux::Depart, TypeEtatDesLieux::Retour]
                : [TypeEtatDesLieux::Remise];
            foreach ($types as $type) {
                $demande->etatsDesLieux()->create(['type' => $type, 'vehicule_id' => $demande->vehicule_id]);
            }

            // Une vente payée retire le véhicule du catalogue et clôt les autres demandes
            if ($demande->type === TypeDemande::Achat) {
                $demande->vehicule->update(['etat' => EtatVehicule::Vendu]);
                $autres = $demande->vehicule->demandes()
                    ->whereKeyNot($demande->id)
                    ->whereIn('statut', [StatutDemande::EnAttente, StatutDemande::Acceptee])
                    ->with('client')
                    ->get();
                foreach ($autres as $autre) {
                    $autre->update(['statut' => StatutDemande::Refusee, 'motif_refus' => 'Le véhicule a été vendu.', 'repondue_le' => now()]);
                    $autre->client->notify(new Alerte('demande', 'Demande close', "Le véhicule {$this->nomVehicule($autre)} a été vendu.", '/demandes'));
                }
            }
        });

        $demande = $paiement->demande->load(['client', 'partenaire.user', 'vehicule']);
        $vehicule = $this->nomVehicule($demande);
        $montant = number_format($paiement->montant_total, 0, ',', ' ');
        $net = number_format($paiement->montant_partenaire, 0, ',', ' ');

        $demande->client->notify(new Alerte('paiement', 'Paiement confirmé',
            "Votre paiement de {$montant} FCFA pour le {$vehicule} a été reçu.", '/demandes'));
        $demande->partenaire->user->notify(new Alerte('paiement', 'Paiement reçu',
            "{$demande->client->nom} a payé pour le {$vehicule}. Montant qui vous revient : {$net} FCFA.", '/partenaire/paiements'));

        $agents = User::where('role', Role::Agent)->where('est_actif', true)->get();
        Notification::send($agents, new Alerte('mission', 'Nouvelle mission',
            "État des lieux à réaliser pour le {$vehicule} ({$demande->vehicule->ville}).", '/agent/missions'));

        return $paiement->refresh();
    }

    private function nomVehicule(Demande $demande): string
    {
        return "{$demande->vehicule->marque} {$demande->vehicule->modele}";
    }
}
