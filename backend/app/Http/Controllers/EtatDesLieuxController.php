<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Agent\MissionController;
use App\Http\Resources\EtatDesLieuxResource;
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
}
