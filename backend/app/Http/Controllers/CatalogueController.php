<?php

namespace App\Http\Controllers;

use App\Enums\CategorieVehicule;
use App\Enums\TypeActivite;
use App\Http\Resources\VehiculeResource;
use App\Models\PhotoVehicule;
use App\Models\Vehicule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Cas « Rechercher un véhicule », « Filtrer par critères » et « Consulter la fiche d'un véhicule ». */
class CatalogueController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtres = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'categorie' => ['nullable', Rule::enum(CategorieVehicule::class)],
            'offre' => ['nullable', Rule::in(['location', 'vente'])],
            'ville' => ['nullable', 'string', 'max:60'],
            'prix_max' => ['nullable', 'integer', 'min:0'],
            'date_debut' => ['nullable', 'date_format:Y-m-d', 'required_with:date_fin'],
            'date_fin' => ['nullable', 'date_format:Y-m-d', 'required_with:date_debut', 'after_or_equal:date_debut'],
            'tri' => ['nullable', Rule::in(['recent', 'prix_croissant', 'prix_decroissant'])],
        ]);

        $offre = $filtres['offre'] ?? null;
        $colonnePrix = $offre === 'vente' ? 'prix_vente' : 'prix_jour';
        $modes = match ($offre) {
            'location' => [TypeActivite::Location, TypeActivite::VenteEtLocation],
            'vente' => [TypeActivite::Vente, TypeActivite::VenteEtLocation],
            default => null,
        };

        $vehicules = Vehicule::visibles()
            ->with('photos', 'partenaire')
            ->when($filtres['q'] ?? null, function ($q, $texte) {
                $motif = '%'.mb_strtolower($texte).'%';
                $q->where(fn ($q) => $q->whereRaw('LOWER(marque) LIKE ?', [$motif])->orWhereRaw('LOWER(modele) LIKE ?', [$motif]));
            })
            ->when($filtres['categorie'] ?? null, fn ($q, $c) => $q->where('categorie', $c))
            ->when($modes, fn ($q, $m) => $q->whereIn('mode_offre', $m))
            ->when($filtres['ville'] ?? null, fn ($q, $v) => $q->whereRaw('LOWER(ville) = ?', [mb_strtolower($v)]))
            ->when($filtres['prix_max'] ?? null, fn ($q, $p) => $q->where($colonnePrix, '<=', $p))
            // Des dates n'ont de sens que pour une location : on écarte les véhicules bloqués sur la période
            ->when($filtres['date_debut'] ?? null, fn ($q) => $q
                ->whereIn('mode_offre', [TypeActivite::Location, TypeActivite::VenteEtLocation])
                ->libresEntre($filtres['date_debut'], $filtres['date_fin']))
            ->when(($filtres['tri'] ?? 'recent') === 'recent', fn ($q) => $q->latest())
            ->when(($filtres['tri'] ?? null) === 'prix_croissant', fn ($q) => $q->orderBy($colonnePrix))
            ->when(($filtres['tri'] ?? null) === 'prix_decroissant', fn ($q) => $q->orderByDesc($colonnePrix))
            ->paginate(12)
            ->withQueryString();

        return VehiculeResource::collection($vehicules);
    }

    public function show(Vehicule $vehicule): VehiculeResource
    {
        $visible = Vehicule::visibles()->whereKey($vehicule->id)->exists();
        abort_unless($visible, 404, "Ce véhicule n'est pas disponible.");

        return new VehiculeResource($vehicule->load([
            'photos',
            'partenaire',
            'indisponibilites' => fn ($q) => $q->where('date_fin', '>=', now()->toDateString()),
        ]));
    }

    /** Photos servies par l'API : aucune configuration de lien symbolique n'est nécessaire. */
    public function photo(PhotoVehicule $photo): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($photo->chemin), 404);

        return Storage::disk('local')->response($photo->chemin, null, ['Cache-Control' => 'public, max-age=86400']);
    }
}
