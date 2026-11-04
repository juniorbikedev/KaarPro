<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Missions d'état des lieux créées au paiement : départ et retour pour une location, remise pour une vente
        Schema::create('etats_des_lieux', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demande_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);                                 // depart, retour, remise
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('kilometrage')->nullable();
            $table->string('niveau_carburant', 10)->nullable();         // vide, 1/4, 1/2, 3/4, plein
            $table->text('observations')->nullable();
            $table->text('dommages')->nullable();
            $table->unsignedInteger('montant_dommages')->nullable();    // retenue proposée sur la caution, en francs CFA
            $table->timestamp('realise_le')->nullable();
            $table->timestamps();
            $table->unique(['demande_id', 'type']);
        });

        Schema::create('photos_etat_des_lieux', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etat_des_lieux_id')->constrained('etats_des_lieux')->cascadeOnDelete();
            $table->string('chemin');
            $table->timestamps();
        });

        // Avis du client sur le partenaire et le véhicule, une fois la transaction terminée
        Schema::create('avis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demande_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('partenaire_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicule_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('note');                        // 1 à 5
            $table->text('commentaire')->nullable();
            $table->boolean('est_masque')->default(false);              // masqué par l'administrateur
            $table->timestamps();
        });

        // Signalement d'une annonce ou d'un utilisateur, traité par l'administrateur
        Schema::create('signalements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auteur_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('vehicule_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('utilisateur_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('motif', 30);                                // annonce_trompeuse, fraude, comportement, autre
            $table->text('description');
            $table->string('statut', 20)->default('ouvert')->index();   // ouvert, traite, rejete
            $table->text('decision')->nullable();
            $table->foreignId('traite_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('traite_le')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signalements');
        Schema::dropIfExists('avis');
        Schema::dropIfExists('photos_etat_des_lieux');
        Schema::dropIfExists('etats_des_lieux');
    }
};
