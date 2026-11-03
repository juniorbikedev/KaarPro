<?php

namespace App\Http\Controllers\Partenaire;

use App\Enums\StatutDemande;
use App\Enums\TypeDemande;
use App\Http\Controllers\Controller;
use App\Http\Resources\DemandeResource;
use App\Models\Contrat;
use App\Models\Demande;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Cas « Traiter les demandes reçues » : accepter (génère le contrat) ou refuser. */
class DemandeController extends Controller
{
    private const RELATIONS = ['vehicule.photos', 'client', 'partenaire', 'contrat'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['statut' => ['nullable', Rule::enum(StatutDemande::class)]]);

        $demandes = $request->user()->partenaire->demandes()
            ->when($request->query('statut'), fn ($q, $statut) => $q->where('statut', $statut))
            ->with(self::RELATIONS)
            ->latest()
            ->get();

        return DemandeResource::collection($demandes);
    }

    public function accepter(Request $request, Demande $demande): DemandeResource
    {
        $this->verifierPartenaire($request, $demande);
        abort_unless($demande->estEnAttente(), 422, 'Cette demande a déjà été traitée.');

        DB::transaction(function () use ($demande) {
            $vehicule = $demande->vehicule()->lockForUpdate()->first();

            if ($demande->type === TypeDemande::Location) {
                $debut = $demande->date_debut->toDateString();
                $fin = $demande->date_fin->toDateString();

                if (! $vehicule->estLibreEntre($debut, $fin)) {
                    throw ValidationException::withMessages(['demande' => "Le véhicule n'est plus disponible sur cette période."]);
                }

                // La période est réservée dans le calendrier du véhicule
                $vehicule->indisponibilites()->create([
                    'demande_id' => $demande->id,
                    'date_debut' => $debut,
                    'date_fin' => $fin,
                    'motif' => "Réservé (demande n°{$demande->id})",
                ]);

                // Les autres demandes en attente sur une période qui chevauche ne peuvent plus aboutir
                $vehicule->demandes()
                    ->whereKeyNot($demande->id)
                    ->where('type', TypeDemande::Location)
                    ->where('statut', StatutDemande::EnAttente)
                    ->where('date_debut', '<=', $fin)
                    ->where('date_fin', '>=', $debut)
                    ->update([
                        'statut' => StatutDemande::Refusee,
                        'motif_refus' => 'Le véhicule a été réservé sur cette période.',
                        'repondue_le' => now(),
                    ]);
            } else {
                $dejaVendu = $vehicule->demandes()
                    ->where('type', TypeDemande::Achat)
                    ->where('statut', StatutDemande::Acceptee)
                    ->exists();
                if ($dejaVendu) {
                    throw ValidationException::withMessages(['demande' => "Une demande d'achat a déjà été acceptée pour ce véhicule."]);
                }
            }

            $demande->update(['statut' => StatutDemande::Acceptee, 'repondue_le' => now()]);
            $this->genererContrat($demande);
        });

        return new DemandeResource($demande->refresh()->load(self::RELATIONS));
    }

    public function refuser(Request $request, Demande $demande): DemandeResource
    {
        $this->verifierPartenaire($request, $demande);
        abort_unless($demande->estEnAttente(), 422, 'Cette demande a déjà été traitée.');

        $donnees = $request->validate(['motif' => ['required', 'string', 'max:255']]);

        $demande->update([
            'statut' => StatutDemande::Refusee,
            'motif_refus' => $donnees['motif'],
            'repondue_le' => now(),
        ]);

        return new DemandeResource($demande->load(self::RELATIONS));
    }

    /**
     * Le contrat fige les informations du moment : une modification ultérieure du véhicule
     * ou du prix ne change pas un contrat déjà généré. Le partenaire le signe en acceptant.
     */
    private function genererContrat(Demande $demande): Contrat
    {
        $demande->load(['client', 'partenaire.user', 'vehicule']);
        $location = $demande->type === TypeDemande::Location;
        $vehicule = $demande->vehicule;

        return $demande->contrat()->create([
            'numero' => sprintf('KP-%s-%s%05d', now()->year, $location ? 'L' : 'A', $demande->id),
            'signe_partenaire_le' => now(),
            'donnees' => [
                'type' => $demande->type->value,
                'client' => [
                    'nom' => $demande->client->nom,
                    'email' => $demande->client->email,
                    'telephone' => $demande->client->telephone,
                ],
                'partenaire' => [
                    'nom_commercial' => $demande->partenaire->nom_commercial,
                    'representant' => $demande->partenaire->user->nom,
                    'telephone' => $demande->partenaire->user->telephone,
                ],
                'vehicule' => [
                    'marque' => $vehicule->marque,
                    'modele' => $vehicule->modele,
                    'annee' => $vehicule->annee,
                    'immatriculation' => $vehicule->immatriculation,
                    'kilometrage' => $vehicule->kilometrage,
                ],
                'date_debut' => $demande->date_debut?->toDateString(),
                'date_fin' => $demande->date_fin?->toDateString(),
                'nb_jours' => $demande->nb_jours,
                'prix_jour' => $location ? $vehicule->prix_jour : null,
                'montant' => $demande->montant,
                'montant_caution' => $demande->montant_caution,
                'clauses' => $location ? self::CLAUSES_LOCATION : self::CLAUSES_VENTE,
            ],
        ]);
    }

    private const CLAUSES_LOCATION = [
        'Le loyer et la caution sont payés par Wave ou Orange Money sur la plateforme KaarPro avant la remise du véhicule.',
        "Un agent de TerangaAuto réalise l'état des lieux de départ et de retour ; ses constats, photos à l'appui, font foi entre les parties.",
        "La caution est restituée au locataire après l'état des lieux de retour, déduction faite des dommages constatés.",
        'Le locataire utilise le véhicule en bon père de famille, le restitue à la date prévue et ne le sous-loue pas.',
        "Toute prolongation fait l'objet d'une nouvelle demande sur la plateforme.",
        'KaarPro agit comme intermédiaire et prélève une commission sur le montant de la location.',
    ];

    private const CLAUSES_VENTE = [
        "Le prix est payé par Wave ou Orange Money sur la plateforme KaarPro ; la vente est conclue à la réception du paiement intégral.",
        "Un agent de TerangaAuto contrôle le véhicule avant la remise et constate son état, photos à l'appui.",
        "Le vendeur remet à l'acheteur la carte grise et les documents du véhicule, et l'assiste pour la mutation.",
        "Le véhicule est vendu dans l'état constaté lors du contrôle.",
        'KaarPro agit comme intermédiaire et prélève une commission sur le prix de vente.',
    ];

    private function verifierPartenaire(Request $request, Demande $demande): void
    {
        abort_unless($demande->partenaire_id === $request->user()->partenaire->id, 404);
    }
}
