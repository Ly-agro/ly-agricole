<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Décision de la direction sur le plafond de prêt d'un producteur (note de fiabilité,
     * question 35) 🔒. On garde la proposition du logiciel, les données qui l'ont fournie, la
     * date du calcul, le plafond RETENU et qui l'a décidé. Registre immuable : une nouvelle
     * décision remplace la précédente, l'historique reste.
     *
     * Trace seulement : ce plafond n'est pas appliqué automatiquement à la création d'un
     * prêt (celui des Paramètres l'est déjà).
     */
    public function up(): void
    {
        Schema::create('decisions_plafond', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('producteur_id')->constrained('producteurs');
            // Campagne pour laquelle le plafond est décidé (facultatif).
            $table->foreignId('campagne_id')->nullable()->constrained('campagnes');
            // Ce que le logiciel proposait ; null = aucune proposition.
            $table->unsignedBigInteger('plafond_propose_fcfa')->nullable();
            $table->string('raison_proposition', 500);
            $table->unsignedBigInteger('plafond_retenu_fcfa');
            $table->text('motif')->nullable();
            // Instantané des données utilisées pour la proposition.
            $table->json('donnees');
            $table->timestamp('calcule_at');
            $table->foreignId('cree_par')->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['producteur_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decisions_plafond');
    }
};
