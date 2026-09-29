<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Motif (facultatif) de la dernière modification d'un poste de budget (réponse 29 du
     * questionnaire) : il entre au journal avec l'ancien et le nouveau montant.
     */
    public function up(): void
    {
        Schema::table('lignes_budget', function (Blueprint $table) {
            $table->string('motif_modification')->nullable()->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('lignes_budget', function (Blueprint $table) {
            $table->dropColumn('motif_modification');
        });
    }
};
