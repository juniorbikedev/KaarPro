<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Agent\MissionController;
use App\Http\Resources\EtatDesLieuxResource;
use App\Models\Document;
use App\Models\EtatDesLieux;
use App\Models\PhotoEtatDesLieux;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Consultation d'un constat par le client, le partenaire, un agent ou l'administrateur. */
class EtatDesLieuxController extends Controller
{
    public function show(Request $request, EtatDesLieux $etatDesLieux): EtatDesLieuxResource
    {
        $etatDesLieux->load(MissionController::RELATIONS);
        abort_unless($etatDesLieux->estVisiblePar($request->user()), 404);

        return new EtatDesLieuxResource($etatDesLieux);
    }

    /** Photo servie par un lien signé et temporaire (voir EtatDesLieuxResource). */
    public function photo(PhotoEtatDesLieux $photo): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($photo->chemin), 404);

        return Storage::disk('local')->response($photo->chemin, null, ['Cache-Control' => 'private, max-age=3600']);
    }

    /** Carte grise ou assurance consultée par l'agent pendant l'inspection, par lien signé et temporaire. */
    public function document(Document $document): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($document->chemin), 404);

        return Storage::disk('local')->response($document->chemin, $document->nom_original, ['Cache-Control' => 'private, max-age=3600']);
    }
}
