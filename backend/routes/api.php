<?php

use App\Http\Controllers\Admin\PartenaireController;
use App\Http\Controllers\Admin\UtilisateurController;
use App\Http\Controllers\Auth\InscriptionController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\ProfilController;
use Illuminate\Support\Facades\Route;

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
