<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parc de TerangaAuto : l'entreprise publie ses propres véhicules sous un profil partenaire « interne »,
 * géré par les administrateurs, validé d'office et exempté de commission.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partenaires', function (Blueprint $table) {
            $table->boolean('est_interne')->default(false)->after('type_activite')->index();
        });
    }

    public function down(): void
    {
        Schema::table('partenaires', function (Blueprint $table) {
            $table->dropIndex(['est_interne']);
            $table->dropColumn('est_interne');
        });
    }
};
