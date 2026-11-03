<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partenaire_id')->constrained()->cascadeOnDelete();
            $table->string('immatriculation', 20)->unique();
            $table->string('marque', 60);
            $table->string('modele', 80);
            $table->unsignedSmallInteger('annee');
            $table->string('categorie', 20)->index();   // citadine, berline, suv, 4x4, utilitaire, minibus
            $table->string('carburant', 20);            // essence, diesel, hybride, electrique
            $table->string('boite', 20);                // manuelle, automatique
            $table->unsignedTinyInteger('places');
            $table->unsignedInteger('kilometrage');
            $table->string('ville', 60)->index();
            $table->text('description')->nullable();
            $table->string('mode_offre', 20)->index(); // vente, location, vente_et_location
            $table->unsignedInteger('prix_jour')->nullable();       // en francs CFA
            $table->unsignedBigInteger('prix_vente')->nullable();   // en francs CFA
            $table->unsignedInteger('montant_caution')->nullable(); // en francs CFA
            $table->string('etat', 20)->default('disponible');      // disponible, en_location, en_maintenance, vendu
            $table->string('statut_publication', 20)->default('publie')->index(); // publie, retire
            $table->timestamps();
        });

        Schema::create('photos_vehicule', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicule_id')->constrained()->cascadeOnDelete();
            $table->string('chemin');
            $table->unsignedTinyInteger('ordre')->default(0);
            $table->timestamps();
        });

        // Périodes bloquées par le loueur (entretien, usage personnel…)
        Schema::create('indisponibilites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicule_id')->constrained()->cascadeOnDelete();
            $table->date('date_debut');
            $table->date('date_fin');
            $table->string('motif', 120)->nullable();
            $table->timestamps();
            $table->index(['vehicule_id', 'date_debut', 'date_fin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indisponibilites');
        Schema::dropIfExists('photos_vehicule');
        Schema::dropIfExists('vehicules');
    }
};
