<?php

namespace App\Http\Controllers;

use App\Enums\StatutDemande;
use App\Enums\TypeDemande;
use App\Http\Resources\DemandeResource;
use App\Models\Demande;
use App\Models\Vehicule;
use App\Notifications\Alerte;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Cas « Envoyer une demande de location / d'achat » et « Suivre ses demandes » (client). */
class DemandeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $demandes = Demande::where('client_id', $request->user()->id)
            ->with(['vehicule.photos', 'partenaire', 'contrat', 'paiementReussi'])
            ->latest()
            ->get();

        return DemandeResource::collection($demandes);
    }

    public function store(Request $request, Vehicule $vehicule): JsonResponse
    {
        abort_unless(Vehicule::visibles()->whereKey($vehicule->id)->exists(), 404, "Ce véhicule n'est pas disponible.");

        $donnees = $request->validate([
            'type' => ['required', Rule::enum(TypeDemande::class)],
            'date_debut' => ['required_if:type,location', 'nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'date_fin' => ['required_if:type,location', 'nullable', 'date_format:Y-m-d', 'after_or_equal:date_debut'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);
        $type = TypeDemande::from($donnees['type']);

        if ($type === TypeDemande::Location && ! $vehicule->mode_offre->permetLaLocation()) {
            throw ValidationException::withMessages(['type' => "Ce véhicule n'est pas proposé à la location."]);
        }
        if ($type === TypeDemande::Achat && ! $vehicule->mode_offre->permetLaVente()) {
            throw ValidationException::withMessages(['type' => "Ce véhicule n'est pas proposé à la vente."]);
        }

        $dejaEnvoyee = Demande::where('client_id', $request->user()->id)
            ->where('vehicule_id', $vehicule->id)
            ->where('type', $type)
            ->where('statut', StatutDemande::EnAttente)
            ->exists();
        if ($dejaEnvoyee) {
            throw ValidationException::withMessages(['type' => 'Vous avez déjà une demande en attente pour ce véhicule.']);
        }

        $attributs = [
            'client_id' => $request->user()->id,
            'partenaire_id' => $vehicule->partenaire_id,
            'type' => $type,
            'message' => $donnees['message'] ?? null,
        ];

        if ($type === TypeDemande::Location) {
            if (! $vehicule->estLibreEntre($donnees['date_debut'], $donnees['date_fin'])) {
                throw ValidationException::withMessages(['date_debut' => "Le véhicule n'est pas disponible sur cette période."]);
            }
            // Les jours sont comptés de façon inclusive : du 10 au 12 = 3 jours
            $nbJours = CarbonImmutable::parse($donnees['date_debut'])->diffInDays(CarbonImmutable::parse($donnees['date_fin'])) + 1;
            $attributs += [
                'date_debut' => $donnees['date_debut'],
                'date_fin' => $donnees['date_fin'],
                'nb_jours' => $nbJours,
                'montant' => $nbJours * $vehicule->prix_jour,
                'montant_caution' => $vehicule->montant_caution,
            ];
        } else {
            $attributs['montant'] = $vehicule->prix_vente;
        }

        $demande = $vehicule->demandes()->create($attributs);

        $vehicule->partenaire->user->notify(new Alerte('demande',
            $type === TypeDemande::Location ? 'Nouvelle demande de location' : "Nouvelle demande d'achat",
            "{$request->user()->nom} s'intéresse à votre {$vehicule->marque} {$vehicule->modele}.",
            '/partenaire/demandes'));

        return (new DemandeResource($demande->refresh()->load(['vehicule.photos', 'partenaire', 'contrat'])))
            ->response()
            ->setStatusCode(201);
    }

    public function annuler(Request $request, Demande $demande): DemandeResource
    {
        abort_unless($demande->client_id === $request->user()->id, 404);
        abort_unless($demande->estEnAttente(), 422, 'Seule une demande en attente peut être annulée.');

        $demande->update(['statut' => StatutDemande::Annulee]);

        return new DemandeResource($demande->load(['vehicule.photos', 'partenaire', 'contrat']));
    }
}
