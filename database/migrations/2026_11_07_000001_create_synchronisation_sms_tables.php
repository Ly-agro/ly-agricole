<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Semaine 7 : trace des envois de l'appli terrain (skill terrain-hors-ligne) et
 * confirmations SMS aux producteurs (cahier §10, D10).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('synchronisations', function (Blueprint $table) {
            $table->id();
            $table->string('appareil_id', 100)->index();
            $table->foreignId('user_id')->constrained('users');
            $table->timestamp('recu_at')->useCurrent();
            $table->unsignedInteger('nb_operations');
            $table->unsignedInteger('nb_acceptees');
            $table->unsignedInteger('nb_deja_recues');
            $table->unsignedInteger('nb_rejetees');
        });

        // Une ligne par UUID d'opération : dernier sort connu. Une opération rejetée peut
        // être renvoyée (corrigée) ; une opération acceptée renvoyée répond « deja_recu ».
        Schema::create('operations_recues', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('type', 30);
            $table->foreignId('synchronisation_id')->constrained('synchronisations');
            $table->string('appareil_id', 100);
            $table->foreignId('user_id')->constrained('users');
            $table->string('statut', 20);
            $table->string('motif', 500)->nullable();
            // Heure du téléphone (peut être fausse) ET heure du serveur.
            $table->timestamp('cree_at')->nullable();
            $table->timestamp('recu_at')->useCurrent();
        });

        Schema::create('confirmations_sms', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('producteur_id')->constrained('producteurs');
            $table->string('objet_type', 50);
            $table->string('objet_id', 36);
            $table->string('telephone', 10);
            $table->string('message', 480);
            $table->string('statut', 20)->index();
            $table->string('pilote', 30);
            $table->timestamp('envoye_at')->nullable();
            $table->string('erreur')->nullable();
            $table->timestamps();

            $table->unique(['objet_type', 'objet_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('confirmations_sms');
        Schema::dropIfExists('operations_recues');
        Schema::dropIfExists('synchronisations');
    }
};
