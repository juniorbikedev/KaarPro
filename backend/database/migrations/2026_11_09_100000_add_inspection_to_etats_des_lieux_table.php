<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * L'agent inspecte aussi les véhicules publiés : une inspection est une mission rattachée au véhicule,
     * sans demande. Le résultat (conforme ou non) et les points contrôlés sont conservés.
     */
    public function up(): void
    {
        Schema::table('etats_des_lieux', function (Blueprint $table) {
            $table->unsignedBigInteger('demande_id')->nullable()->change();
            $table->foreignId('vehicule_id')->nullable()->after('demande_id')->constrained()->cascadeOnDelete();
            $table->boolean('conforme')->nullable()->after('montant_dommages');
            $table->json('points_controle')->nullable()->after('conforme');
        });

        // Les missions existantes portent sur le véhicule de leur demande
        DB::table('etats_des_lieux')->whereNull('vehicule_id')->orderBy('id')->each(function ($mission) {
            DB::table('etats_des_lieux')->where('id', $mission->id)->update([
                'vehicule_id' => DB::table('demandes')->where('id', $mission->demande_id)->value('vehicule_id'),
            ]);
        });

        // Date de la dernière inspection conforme, affichée dans le catalogue
        Schema::table('vehicules', function (Blueprint $table) {
            $table->timestamp('verifie_le')->nullable()->after('statut_publication');
        });
    }

    public function down(): void
    {
        Schema::table('vehicules', function (Blueprint $table) {
            $table->dropColumn('verifie_le');
        });
        Schema::table('etats_des_lieux', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vehicule_id');
            $table->dropColumn(['conforme', 'points_controle']);
        });
    }
};
