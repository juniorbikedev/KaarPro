<?php

namespace App\Http\Controllers;

use App\Enums\MotifSignalement;
use App\Enums\Role;
use App\Models\Signalement;
use App\Models\User;
use App\Notifications\Alerte;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

/** Cas « Signaler une annonce ou un utilisateur » (tout utilisateur connecté). */
class SignalementController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            // Une annonce ou un utilisateur, pas les deux
            'vehicule_id' => ['required_without:utilisateur_id', 'prohibits:utilisateur_id', 'nullable', 'integer', 'exists:vehicules,id'],
            'utilisateur_id' => ['required_without:vehicule_id', 'nullable', 'integer', 'exists:users,id', Rule::notIn([$request->user()->id])],
            'motif' => ['required', Rule::enum(MotifSignalement::class)],
            'description' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $signalement = Signalement::create([...$donnees, 'auteur_id' => $request->user()->id]);

        $admins = User::where('role', Role::Admin)->where('est_actif', true)->get();
        Notification::send($admins, new Alerte('signalement', 'Nouveau signalement',
            "{$request->user()->nom} a signalé ".($signalement->vehicule_id ? 'une annonce' : 'un utilisateur').'.',
            '/admin/signalements'));

        return response()->json(['message' => 'Merci, votre signalement a été transmis à l’administrateur.'], 201);
    }
}
