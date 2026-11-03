<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Demandes de location ou d'achat envoyées par un client
        Schema::create('demandes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('vehicule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('partenaire_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->index();                   // location, achat
            $table->date('date_debut')->nullable();                // location uniquement
            $table->date('date_fin')->nullable();
            $table->unsignedSmallInteger('nb_jours')->nullable();
            $table->unsignedBigInteger('montant');                 // en francs CFA
            $table->unsignedInteger('montant_caution')->nullable();
            $table->text('message')->nullable();
            $table->string('statut', 20)->default('en_attente')->index(); // en_attente, acceptee, refusee, annulee
            $table->string('motif_refus')->nullable();
            $table->timestamp('repondue_le')->nullable();
            $table->timestamps();
        });

        // Contrat généré à l'acceptation : les données sont figées au moment de la génération
        Schema::create('contrats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demande_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('numero', 30)->unique();
            $table->json('donnees');
            $table->timestamp('signe_partenaire_le')->nullable();
            $table->timestamp('signe_client_le')->nullable();
            $table->timestamps();
        });

        // Une période réservée par une location acceptée est rattachée à sa demande
        Schema::table('indisponibilites', function (Blueprint $table) {
            $table->foreignId('demande_id')->nullable()->after('vehicule_id')->constrained()->nullOnDelete();
        });

        // Messagerie : une conversation par client et par véhicule
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('partenaire_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicule_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['client_id', 'vehicule_id']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('auteur_id')->constrained('users')->cascadeOnDelete();
            $table->text('contenu');
            $table->timestamp('lu_le')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
        Schema::table('indisponibilites', function (Blueprint $table) {
            $table->dropConstrainedForeignId('demande_id');
        });
        Schema::dropIfExists('contrats');
        Schema::dropIfExists('demandes');
    }
};
