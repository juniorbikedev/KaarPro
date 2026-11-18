<?php

use App\Enums\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Le parc de TerangaAuto reste rattaché au compte d'un administrateur, qui ne peut pas être désactivé.
 * Une version intermédiaire l'avait déplacé sur un compte technique « Service commercial TerangaAuto » :
 * sur les bases concernées, le parc revient au premier administrateur actif et ce compte est supprimé.
 */
return new class extends Migration
{
    public function up(): void
    {
        $parc = DB::table('partenaires')->where('est_interne', true)->first();
        if ($parc) {
            $proprietaire = DB::table('users')->where('id', $parc->user_id)->first();
            $administrateur = DB::table('users')->where('role', Role::Admin->value)->where('est_actif', true)->orderBy('id')->value('id');
            if ($proprietaire && $proprietaire->role !== Role::Admin->value && $administrateur) {
                DB::table('partenaires')->where('id', $parc->id)->update(['user_id' => $administrateur]);
                DB::table('personal_access_tokens')->where('tokenable_id', $proprietaire->id)->delete();
                DB::table('users')->where('id', $proprietaire->id)->delete();
            }
        }

        // La migration de la version intermédiaire n'existe plus
        DB::table('migrations')->where('migration', '2026_11_18_100000_rattacher_le_parc_terangaauto_a_son_compte')->delete();
    }

    public function down(): void
    {
        // Rien à défaire : le parc reste rattaché à un administrateur
    }
};
