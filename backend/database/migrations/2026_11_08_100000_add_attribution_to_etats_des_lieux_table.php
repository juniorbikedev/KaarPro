<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // L'administrateur confie une mission à un agent, avec une consigne éventuelle
    public function up(): void
    {
        Schema::table('etats_des_lieux', function (Blueprint $table) {
            $table->text('consigne')->nullable()->after('agent_id');
            $table->foreignId('attribuee_par')->nullable()->after('consigne')->constrained('users')->nullOnDelete();
            $table->timestamp('attribuee_le')->nullable()->after('attribuee_par');
        });
    }

    public function down(): void
    {
        Schema::table('etats_des_lieux', function (Blueprint $table) {
            $table->dropConstrainedForeignId('attribuee_par');
            $table->dropColumn(['consigne', 'attribuee_le']);
        });
    }
};
