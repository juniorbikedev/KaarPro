<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Enums\StatutDemande;
use App\Enums\StatutPaiement;
use App\Enums\StatutValidation;
use App\Http\Controllers\Controller;
use App\Models\Avis;
use App\Models\Demande;
use App\Models\EtatDesLieux;
use App\Models\Paiement;
use App\Models\Partenaire;
use App\Models\Signalement;
use App\Models\User;
use App\Models\Vehicule;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

/** Cas « Consulter le tableau de bord » : réservé à l'administrateur. */
class TableauDeBordController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $reussis = Paiement::where('statut', StatutPaiement::Reussi)->get(['montant', 'montant_total', 'commission', 'montant_caution', 'statut_caution', 'paye_le']);
        $debutMois = CarbonImmutable::now()->startOfMonth();

        // Commissions des six derniers mois (calcul en PHP : identique sur SQLite et PostgreSQL)
        $mois = collect(range(5, 0))->map(function (int $recul) use ($reussis, $debutMois) {
            $debut = $debutMois->subMonths($recul);
            $duMois = $reussis->filter(fn ($p) => $p->paye_le->between($debut, $debut->endOfMonth()));

            return [
                'mois' => $debut->format('Y-m'),
                'libelle' => ucfirst($debut->locale('fr')->translatedFormat('M Y')),
                'commission' => (int) $duMois->sum('commission'),
                'transactions' => $duMois->count(),
            ];
        });

        $demandes = Demande::selectRaw('statut, count(*) as total')->groupBy('statut')->pluck('total', 'statut');

        $vehiculesDemandes = Vehicule::withCount('demandes')
            ->orderByDesc('demandes_count')
            ->limit(5)
            ->get()
            ->filter(fn ($v) => $v->demandes_count > 0)
            ->map(fn ($v) => ['id' => $v->id, 'libelle' => "{$v->marque} {$v->modele}", 'demandes' => $v->demandes_count])
            ->values();

        return response()->json(['data' => [
            'utilisateurs' => [
                'clients' => User::where('role', Role::Client)->count(),
                'partenaires' => User::where('role', Role::Vendeur)->count(),
                'agents' => User::where('role', Role::Agent)->count(),
                'partenaires_en_attente' => Partenaire::where('statut_validation', StatutValidation::EnAttente)->count(),
            ],
            'vehicules' => [
                'publies' => Vehicule::visibles()->count(),
                'en_location' => Vehicule::where('etat', 'en_location')->count(),
                'vendus' => Vehicule::where('etat', 'vendu')->count(),
            ],
            'demandes' => collect(StatutDemande::cases())->mapWithKeys(fn ($s) => [$s->value => (int) ($demandes[$s->value] ?? 0)]),
            'finances' => [
                'encaisse' => (int) $reussis->sum('montant_total'),
                'commission_totale' => (int) $reussis->sum('commission'),
                'commission_du_mois' => (int) $reussis->filter(fn ($p) => $p->paye_le->gte($debutMois))->sum('commission'),
                'cautions_bloquees' => (int) $reussis->where('statut_caution', Paiement::CAUTION_BLOQUEE)->sum('montant_caution'),
            ],
            'a_traiter' => [
                'missions_a_attribuer' => EtatDesLieux::whereNull('agent_id')->whereNull('realise_le')->count(),
                'signalements_ouverts' => Signalement::where('statut', Signalement::OUVERT)->count(),
                'partenaires_a_valider' => Partenaire::where('statut_validation', StatutValidation::EnAttente)->count(),
            ],
            'satisfaction' => [
                'note_moyenne' => round((float) Avis::where('est_masque', false)->avg('note'), 1),
                'nombre_avis' => Avis::where('est_masque', false)->count(),
            ],
            'commissions_par_mois' => $mois,
            'vehicules_les_plus_demandes' => $vehiculesDemandes,
        ]]);
    }
}
