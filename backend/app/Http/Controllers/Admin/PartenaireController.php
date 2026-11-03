<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatutValidation;
use App\Http\Controllers\Controller;
use App\Http\Resources\PartenaireResource;
use App\Models\Document;
use App\Models\Partenaire;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Cas « Valider les partenaires » et « Consulter les documents fournis ». */
class PartenaireController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['statut' => ['nullable', Rule::enum(StatutValidation::class)]]);

        $partenaires = Partenaire::with('user')
            ->when($request->statut, fn ($q, $statut) => $q->where('statut_validation', $statut))
            ->latest()
            ->paginate(20);

        return PartenaireResource::collection($partenaires);
    }

    public function show(Partenaire $partenaire): PartenaireResource
    {
        return new PartenaireResource($partenaire->load('user', 'documents'));
    }

    public function valider(Partenaire $partenaire): PartenaireResource
    {
        $partenaire->update([
            'statut_validation' => StatutValidation::Valide,
            'motif_refus' => null,
            'valide_le' => now(),
        ]);
        $partenaire->documents()->update(['est_verifie' => true]);

        return $this->show($partenaire);
    }

    public function refuser(Request $request, Partenaire $partenaire): PartenaireResource
    {
        $donnees = $request->validate(['motif' => ['required', 'string', 'max:500']]);

        $partenaire->update([
            'statut_validation' => StatutValidation::Refuse,
            'motif_refus' => $donnees['motif'],
            'valide_le' => null,
        ]);

        return $this->show($partenaire);
    }

    /** Téléchargement d'un document : les fichiers ne sont jamais publics. */
    public function document(Document $document): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($document->chemin), 404, 'Fichier introuvable.');

        return Storage::disk('local')->download($document->chemin, $document->nom_original);
    }
}
