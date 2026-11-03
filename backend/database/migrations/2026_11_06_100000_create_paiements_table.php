<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Un paiement par tentative : le client peut réessayer après un échec
        Schema::create('paiements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demande_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->string('reference', 40)->unique();              // référence KaarPro transmise à l'opérateur
            $table->string('operateur', 20);                         // wave, orange_money
            $table->string('telephone', 30);
            $table->unsignedBigInteger('montant');                   // location ou prix de vente, en francs CFA
            $table->unsignedInteger('montant_caution')->default(0);
            $table->unsignedBigInteger('montant_total');             // montant + caution : ce que paie le client
            $table->decimal('taux_commission', 5, 2);
            $table->unsignedBigInteger('commission');
            $table->unsignedBigInteger('montant_partenaire');        // montant - commission
            $table->string('statut', 20)->default('en_attente')->index(); // en_attente, reussi, echoue
            $table->string('reference_operateur')->nullable();
            $table->timestamp('paye_le')->nullable();

            // Caution : bloquée au paiement, restituée (totalement ou en partie) après le retour du véhicule
            $table->string('statut_caution', 20)->nullable();        // bloquee, restituee
            $table->unsignedInteger('caution_retenue')->nullable();
            $table->string('motif_retenue')->nullable();
            $table->timestamp('caution_traitee_le')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('paiements');
    }
};
