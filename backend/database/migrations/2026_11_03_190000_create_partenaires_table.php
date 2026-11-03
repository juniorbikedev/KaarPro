<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Profil « vendeur/loueur » rattaché à un utilisateur de rôle vendeur
        Schema::create('partenaires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('nom_commercial');
            $table->string('type_activite', 20); // vente, location, vente_et_location
            $table->string('statut_validation', 20)->default('en_attente')->index(); // en_attente, valide, refuse
            $table->text('motif_refus')->nullable();
            $table->timestamp('valide_le')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partenaires');
    }
};
