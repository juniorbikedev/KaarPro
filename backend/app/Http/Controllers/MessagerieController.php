<?php

namespace App\Http\Controllers;

use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use App\Models\Vehicule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Cas « Échanger des messages » entre un client et un partenaire, à propos d'un véhicule. */
class MessagerieController extends Controller
{
    private const RELATIONS = ['vehicule', 'partenaire', 'client'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $conversations = Conversation::query()
            ->where(fn (Builder $q) => $q
                ->where('client_id', $user->id)
                ->orWhereHas('partenaire', fn ($p) => $p->where('user_id', $user->id)))
            ->whereHas('messages')
            ->with([...self::RELATIONS, 'dernierMessage'])
            ->withCount(['messages as non_lus' => fn ($q) => $q->whereNull('lu_le')->where('auteur_id', '!=', $user->id)])
            ->latest('updated_at')
            ->get();

        return ConversationResource::collection($conversations);
    }

    /** Ouvre (ou retrouve) la conversation du client connecté avec le partenaire du véhicule. */
    public function demarrer(Request $request, Vehicule $vehicule): JsonResponse
    {
        abort_unless(Vehicule::visibles()->whereKey($vehicule->id)->exists(), 404, "Ce véhicule n'est pas disponible.");

        $conversation = Conversation::firstOrCreate(
            ['client_id' => $request->user()->id, 'vehicule_id' => $vehicule->id],
            ['partenaire_id' => $vehicule->partenaire_id],
        );

        return response()->json(['data' => ['id' => $conversation->id]], $conversation->wasRecentlyCreated ? 201 : 200);
    }

    /** Affiche les messages et marque comme lus ceux reçus par l'utilisateur. */
    public function show(Request $request, Conversation $conversation): ConversationResource
    {
        abort_unless($conversation->concerne($request->user()), 404);

        $conversation->messages()
            ->whereNull('lu_le')
            ->where('auteur_id', '!=', $request->user()->id)
            ->update(['lu_le' => now()]);

        return new ConversationResource($conversation->load([...self::RELATIONS, 'messages']));
    }

    public function envoyer(Request $request, Conversation $conversation): ConversationResource
    {
        abort_unless($conversation->concerne($request->user()), 404);

        $donnees = $request->validate(['contenu' => ['required', 'string', 'max:2000']]);
        $conversation->messages()->create(['auteur_id' => $request->user()->id, 'contenu' => trim($donnees['contenu'])]);
        $conversation->touch();

        return new ConversationResource($conversation->load([...self::RELATIONS, 'messages']));
    }
}
