<?php

namespace App\Http\Controllers\Partenaire;

use App\Enums\CategorieVehicule;
use App\Enums\EtatVehicule;
use App\Enums\TypeActivite;
use App\Enums\TypeDocument;
use App\Http\Controllers\Controller;
use App\Http\Resources\VehiculeResource;
use App\Missions\Inspections;
use App\Models\Partenaire;
use App\Models\PhotoVehicule;
use App\Models\Vehicule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** Cas « Gérer ses véhicules » : publier, modifier, retirer. */
class VehiculeController extends Controller
{
    private const MAX_PHOTOS = 8;

    public function index(Request $request): AnonymousResourceCollection
    {
        $vehicules = $this->partenaire($request)->vehicules()->with('photos')->latest()->get();

        return VehiculeResource::collection($vehicules);
    }

    public function show(Request $request, Vehicule $vehicule): VehiculeResource
    {
        $this->verifierProprietaire($request, $vehicule);

        return new VehiculeResource($vehicule->load('photos', 'indisponibilites', 'documents'));
    }

    /** « Publier un véhicule » : caractéristiques, prix, photos, carte grise et assurance. */
    public function store(Request $request, Inspections $inspections): JsonResponse
    {
        $partenaire = $this->partenaire($request);
        $donnees = $request->validate([
            ...$this->regles($partenaire),
            'photos' => ['required', 'array', 'min:1', 'max:'.self::MAX_PHOTOS],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'carte_grise' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'assurance' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $vehicule = DB::transaction(function () use ($partenaire, $donnees, $request) {
            $vehicule = $partenaire->vehicules()->create($this->normaliser($donnees));

            $this->enregistrerPhotos($vehicule, $request->file('photos'));
            foreach ([TypeDocument::CarteGrise->value => 'carte_grise', TypeDocument::Assurance->value => 'assurance'] as $type => $champ) {
                $fichier = $request->file($champ);
                $vehicule->documents()->create([
                    'type' => $type,
                    'chemin' => $fichier->store("documents/vehicules/{$vehicule->id}", 'local'),
                    'nom_original' => $fichier->getClientOriginalName(),
                ]);
            }

            return $vehicule;
        });

        // Un agent vérifiera que le véhicule correspond à ses documents
        $inspections->demander($vehicule);

        // refresh() récupère les valeurs par défaut posées par la base (état, statut de publication)
        return (new VehiculeResource($vehicule->refresh()->load('photos', 'documents')))->response()->setStatusCode(201);
    }

    /** « Modifier un véhicule » (les fichiers ont leurs propres routes). */
    public function update(Request $request, Vehicule $vehicule): VehiculeResource
    {
        $this->verifierProprietaire($request, $vehicule);
        $donnees = $request->validate([
            ...$this->regles($vehicule->partenaire, $vehicule),
            'etat' => ['sometimes', Rule::enum(EtatVehicule::class)],
        ]);

        $vehicule->update($this->normaliser($donnees));

        return $this->show($request, $vehicule);
    }

    /** « Retirer un véhicule » : il disparaît du catalogue mais reste dans l'historique. */
    public function retirer(Request $request, Vehicule $vehicule): VehiculeResource
    {
        $this->verifierProprietaire($request, $vehicule);
        $vehicule->update(['statut_publication' => Vehicule::RETIRE]);

        return $this->show($request, $vehicule);
    }

    public function republier(Request $request, Vehicule $vehicule): VehiculeResource
    {
        $this->verifierProprietaire($request, $vehicule);
        abort_unless(in_array($vehicule->mode_offre->value, $this->modesAutorises($vehicule->partenaire), true), 422,
            "Le mode d'offre de ce véhicule ne correspond plus à votre activité : modifiez-le avant de le republier.");
        $vehicule->update(['statut_publication' => Vehicule::PUBLIE]);

        return $this->show($request, $vehicule);
    }

    public function ajouterPhotos(Request $request, Vehicule $vehicule): VehiculeResource
    {
        $this->verifierProprietaire($request, $vehicule);
        $place = self::MAX_PHOTOS - $vehicule->photos()->count();
        $request->validate([
            'photos' => ['required', 'array', 'min:1', 'max:'.max($place, 0)],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], ['photos.max' => 'Un véhicule compte au plus '.self::MAX_PHOTOS.' photos.']);

        $this->enregistrerPhotos($vehicule, $request->file('photos'));

        return $this->show($request, $vehicule);
    }

    public function supprimerPhoto(Request $request, Vehicule $vehicule, PhotoVehicule $photo): VehiculeResource
    {
        $this->verifierProprietaire($request, $vehicule);
        abort_unless($photo->vehicule_id === $vehicule->id, 404);
        abort_if($vehicule->photos()->count() <= 1, 422, 'Un véhicule publié doit garder au moins une photo.');

        Storage::disk('local')->delete($photo->chemin);
        $photo->delete();

        return $this->show($request, $vehicule);
    }

    private function regles(Partenaire $partenaire, ?Vehicule $vehicule = null): array
    {
        return [
            'immatriculation' => ['required', 'string', 'max:20', Rule::unique('vehicules')->ignore($vehicule)],
            'marque' => ['required', 'string', 'max:60'],
            'modele' => ['required', 'string', 'max:80'],
            'annee' => ['required', 'integer', 'between:1980,'.(now()->year + 1)],
            'categorie' => ['required', Rule::enum(CategorieVehicule::class)],
            'carburant' => ['required', Rule::in(['essence', 'diesel', 'hybride', 'electrique'])],
            'boite' => ['required', Rule::in(['manuelle', 'automatique'])],
            'places' => ['required', 'integer', 'between:1,60'],
            'kilometrage' => ['required', 'integer', 'min:0'],
            'ville' => ['required', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:2000'],
            // Règle de gestion : le mode d'offre doit être compatible avec l'activité du partenaire
            'mode_offre' => ['required', Rule::in($this->modesAutorises($partenaire))],
            'prix_jour' => ['nullable', 'required_unless:mode_offre,vente', 'integer', 'min:1000'],
            'montant_caution' => ['nullable', 'required_unless:mode_offre,vente', 'integer', 'min:0'],
            'prix_vente' => ['nullable', 'required_unless:mode_offre,location', 'integer', 'min:10000'],
        ];
    }

    /** Un partenaire « location » ne publie qu'à louer, « vente » qu'à vendre, « les deux » au choix. */
    private function modesAutorises(Partenaire $partenaire): array
    {
        return match ($partenaire->type_activite) {
            TypeActivite::Vente => [TypeActivite::Vente->value],
            TypeActivite::Location => [TypeActivite::Location->value],
            TypeActivite::VenteEtLocation => array_column(TypeActivite::cases(), 'value'),
        };
    }

    /** Efface les prix qui ne concernent pas le mode d'offre choisi. */
    private function normaliser(array $donnees): array
    {
        $mode = TypeActivite::from($donnees['mode_offre']);
        if (! $mode->permetLaLocation()) {
            $donnees['prix_jour'] = null;
            $donnees['montant_caution'] = null;
        }
        if (! $mode->permetLaVente()) {
            $donnees['prix_vente'] = null;
        }
        $donnees['immatriculation'] = strtoupper(trim($donnees['immatriculation']));

        return collect($donnees)->except(['photos', 'carte_grise', 'assurance'])->all();
    }

    private function enregistrerPhotos(Vehicule $vehicule, array $fichiers): void
    {
        $ordre = (int) $vehicule->photos()->max('ordre');
        foreach ($fichiers as $fichier) {
            $vehicule->photos()->create([
                'chemin' => $fichier->store("vehicules/{$vehicule->id}", 'local'),
                'ordre' => ++$ordre,
            ]);
        }
    }

    private function partenaire(Request $request): Partenaire
    {
        return $request->user()->partenaire;
    }

    private function verifierProprietaire(Request $request, Vehicule $vehicule): void
    {
        abort_unless($vehicule->partenaire_id === $this->partenaire($request)->id, 404);
    }
}
