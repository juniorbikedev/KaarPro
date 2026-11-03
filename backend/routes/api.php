<?php

use App\Http\Controllers\Admin\PaiementController as PaiementAdminController;
use App\Http\Controllers\Admin\PartenaireController;
use App\Http\Controllers\Admin\UtilisateurController;
use App\Http\Controllers\Auth\InscriptionController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\CatalogueController;
use App\Http\Controllers\ContratController;
use App\Http\Controllers\DemandeController;
use App\Http\Controllers\MessagerieController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaiementController;
use App\Http\Controllers\Partenaire\DemandeController as DemandePartenaireController;
use App\Http\Controllers\Partenaire\IndisponibiliteController;
use App\Http\Controllers\Partenaire\PaiementController as PaiementPartenaireController;
use App\Http\Controllers\Partenaire\VehiculeController;
use App\Http\Controllers\ProfilController;
use Illuminate\Support\Facades\Route;

// Sprint 3 : catalogue public
Route::get('vehicules', [CatalogueController::class, 'index']);
Route::get('vehicules/{vehicule}', [CatalogueController::class, 'show']);
Route::get('photos/{photo}', [CatalogueController::class, 'photo'])->name('photos.show');

// Sprint 2 : comptes et rôles (client, vendeur/loueur, agent, administrateur)

Route::prefix('auth')->group(function () {
    Route::post('inscription/client', [InscriptionController::class, 'client']);
    Route::post('inscription/partenaire', [InscriptionController::class, 'partenaire']);
    Route::post('connexion', [SessionController::class, 'connexion'])->middleware('throttle:10,1');
    Route::post('deconnexion', [SessionController::class, 'deconnexion'])->middleware('auth:sanctum');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('moi', [ProfilController::class, 'show']);
    Route::put('moi', [ProfilController::class, 'update']);
    Route::put('moi/type-activite', [ProfilController::class, 'typeActivite']);

    // Sprint 3 : espace du vendeur/loueur validé
    Route::prefix('partenaire')->middleware('partenaire.valide')->group(function () {
        Route::get('vehicules', [VehiculeController::class, 'index']);
        Route::post('vehicules', [VehiculeController::class, 'store']);
        Route::get('vehicules/{vehicule}', [VehiculeController::class, 'show']);
        Route::put('vehicules/{vehicule}', [VehiculeController::class, 'update']);
        Route::post('vehicules/{vehicule}/retirer', [VehiculeController::class, 'retirer']);
        Route::post('vehicules/{vehicule}/republier', [VehiculeController::class, 'republier']);
        Route::post('vehicules/{vehicule}/photos', [VehiculeController::class, 'ajouterPhotos']);
        Route::delete('vehicules/{vehicule}/photos/{photo}', [VehiculeController::class, 'supprimerPhoto']);
        Route::post('vehicules/{vehicule}/indisponibilites', [IndisponibiliteController::class, 'store']);
        Route::delete('vehicules/{vehicule}/indisponibilites/{indisponibilite}', [IndisponibiliteController::class, 'destroy']);

        // Sprint 4 : demandes reçues
        Route::get('demandes', [DemandePartenaireController::class, 'index']);
        Route::post('demandes/{demande}/accepter', [DemandePartenaireController::class, 'accepter']);
        Route::post('demandes/{demande}/refuser', [DemandePartenaireController::class, 'refuser']);

        // Sprint 5 : encaissements
        Route::get('paiements', [PaiementPartenaireController::class, 'index']);
    });

    // Sprint 4 : demandes du client, contrats, messagerie
    Route::middleware('role:client')->group(function () {
        Route::get('demandes', [DemandeController::class, 'index']);
        Route::post('vehicules/{vehicule}/demandes', [DemandeController::class, 'store'])->middleware('throttle:20,1');
        Route::post('demandes/{demande}/annuler', [DemandeController::class, 'annuler']);
        Route::post('vehicules/{vehicule}/conversation', [MessagerieController::class, 'demarrer']);
        Route::post('contrats/{contrat}/signer', [ContratController::class, 'signer']);

        // Sprint 5 : paiement Wave / Orange Money (passerelle simulée en développement)
        Route::post('demandes/{demande}/paiements', [PaiementController::class, 'store'])->middleware('throttle:10,1');
        Route::get('paiements/{paiement}', [PaiementController::class, 'show']);
        Route::post('paiements/{paiement}/simulateur', [PaiementController::class, 'simuler']);
    });
    Route::get('contrats/{contrat}', [ContratController::class, 'show']);
    Route::get('conversations', [MessagerieController::class, 'index']);
    Route::get('conversations/{conversation}', [MessagerieController::class, 'show']);
    Route::post('conversations/{conversation}/messages', [MessagerieController::class, 'envoyer'])->middleware('throttle:30,1');

    // Sprint 5 : notifications
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::post('notifications/tout-lire', [NotificationController::class, 'toutLire']);
    Route::post('notifications/{id}/lire', [NotificationController::class, 'lire']);

    Route::prefix('admin')->middleware('role:admin')->group(function () {
        Route::get('utilisateurs', [UtilisateurController::class, 'index']);
        Route::post('agents', [UtilisateurController::class, 'storeAgent']);
        Route::patch('utilisateurs/{utilisateur}/activation', [UtilisateurController::class, 'activation']);

        Route::get('partenaires', [PartenaireController::class, 'index']);
        Route::get('partenaires/{partenaire}', [PartenaireController::class, 'show']);
        Route::post('partenaires/{partenaire}/valider', [PartenaireController::class, 'valider']);
        Route::post('partenaires/{partenaire}/refuser', [PartenaireController::class, 'refuser']);
        Route::get('documents/{document}/fichier', [PartenaireController::class, 'document']);

        Route::get('paiements', [PaiementAdminController::class, 'index']);
        Route::post('paiements/{paiement}/caution', [PaiementAdminController::class, 'restituerCaution']);
    });
});
