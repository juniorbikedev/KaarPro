<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pièces justificatives : pièce d'identité d'un partenaire, puis carte grise et assurance d'un véhicule
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->morphs('documentable');
            $table->string('type', 30); // piece_identite, carte_grise, assurance
            $table->string('chemin');
            $table->string('nom_original');
            $table->boolean('est_verifie')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
