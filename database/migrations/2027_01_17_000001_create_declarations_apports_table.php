<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('declarations_apports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investisseur_id')->constrained('users');
            $table->foreignId('campagne_id')->constrained('campagnes');
            $table->unsignedBigInteger('montant_fcfa');
            $table->date('date_virement');
            $table->string('reference_virement', 150)->nullable();
            $table->string('statut', 20)->default('en_attente')->index();
            $table->foreignId('compte_id')->nullable()->constrained('comptes_tresorerie');
            $table->foreignId('apport_id')->nullable()->constrained('apports');
            $table->foreignId('traite_par')->nullable()->constrained('users');
            $table->timestamp('traite_at')->nullable();
            $table->string('motif_refus')->nullable();
            $table->timestamps();

            $table->index(['investisseur_id', 'campagne_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('declarations_apports');
    }
};