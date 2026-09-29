<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Apports de campagne (contrat art. 5 et 9) : fonds collectés auprès des
     * investisseurs, et apport propre de LY (facultatif, art. 9). Registre immuable 🔒.
     */
    public function up(): void
    {
        Schema::create('apports', function (Blueprint $table) {
            $table->id();
            // Null = apport de LY elle-même (le contrat le prévoit, facultatif).
            $table->foreignId('investisseur_id')->nullable()->constrained('users');
            $table->foreignId('campagne_id')->constrained('campagnes');
            // Signé : une contre-passation est négative (même schéma que remboursements).
            $table->bigInteger('montant_fcfa');
            $table->date('date_apport');
            $table->string('motif')->nullable();
            $table->foreignId('mouvement_id')->nullable()->constrained('mouvements_tresorerie');
            $table->foreignId('annule_id')->nullable()->unique()->constrained('apports');
            $table->foreignId('cree_par')->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['campagne_id', 'investisseur_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apports');
    }
};
