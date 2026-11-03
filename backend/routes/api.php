<?php

use App\Http\Controllers\Admin\PartenaireController;
use App\Http\Controllers\Admin\UtilisateurController;
use App\Http\Controllers\Auth\InscriptionController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\CatalogueController;
use App\Http\Controllers\Partenaire\IndisponibiliteController;
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
    });

    Route::prefix('admin')->middleware('role:admin')->group(function () {
        Route::get('utilisateurs', [UtilisateurController::class, 'index']);
        Route::post('agents', [UtilisateurController::class, 'storeAgent']);
        Route::patch('utilisateurs/{utilisateur}/activation', [UtilisateurController::class, 'activation']);

        Route::get('partenaires', [PartenaireController::class, 'index']);
        Route::get('partenaires/{partenaire}', [PartenaireController::class, 'show']);
        Route::post('partenaires/{partenaire}/valider', [PartenaireController::class, 'valider']);
        Route::post('partenaires/{partenaire}/refuser', [PartenaireController::class, 'refuser']);
        Route::get('documents/{document}/fichier', [PartenaireController::class, 'document']);
    });
});
