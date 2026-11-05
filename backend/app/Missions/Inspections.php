<?php

namespace App\Missions;

use App\Enums\Role;
use App\Enums\TypeEtatDesLieux;
use App\Models\EtatDesLieux;
use App\Models\User;
use App\Models\Vehicule;
use App\Notifications\Alerte;
use Illuminate\Support\Facades\Notification;

/** Création des missions d'inspection : à la publication d'un véhicule, ou à la demande de l'administrateur. */
class Inspections
{
    /** Une seule inspection en attente par véhicule : si elle existe déjà, elle est renvoyée telle quelle. */
    public function demander(Vehicule $vehicule, ?User $agent = null, ?string $consigne = null, ?User $par = null): EtatDesLieux
    {
        $enAttente = $vehicule->etatsDesLieux()
            ->where('type', TypeEtatDesLieux::Inspection)
            ->whereNull('realise_le')
            ->first();
        if ($enAttente) {
            return $enAttente;
        }

        $inspection = $vehicule->etatsDesLieux()->create([
            'type' => TypeEtatDesLieux::Inspection,
            'agent_id' => $agent?->id,
            'consigne' => $consigne,
            'attribuee_par' => $agent ? $par?->id : null,
            'attribuee_le' => $agent ? now() : null,
        ]);

        $nom = "{$vehicule->marque} {$vehicule->modele} ({$vehicule->ville})";
        if ($agent) {
            $agent->notify(new Alerte('mission', 'Mission confiée',
                "L'administrateur vous confie : inspection du {$nom}.".($consigne ? " Consigne : {$consigne}" : ''), '/agent/missions'));
        } else {
            $agents = User::where('role', Role::Agent)->where('est_actif', true)->get();
            Notification::send($agents, new Alerte('mission', 'Nouvelle inspection',
                "Véhicule à inspecter : {$nom}.", '/agent/missions'));
        }

        return $inspection;
    }
}
