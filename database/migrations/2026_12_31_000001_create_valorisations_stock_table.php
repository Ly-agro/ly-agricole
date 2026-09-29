<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Valorisation du stock invendu d'une campagne (contrat art. 11.3) 🔒 : saisie par la
     * direction à partir de DEUX offres de prix écrites. Registre immuable : une nouvelle
     * valorisation remplace la précédente (la plus récente fait foi), l'historique reste.
     */
    public function up(): void
    {
        Schema::create('valorisations_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campagne_id')->constrained('campagnes');
            // Kilos valorisés, en grammes (D4).
            $table->unsignedBigInteger('poids_g');
            $table->string('offre1_fournisseur');
            $table->date('offre1_date');
            $table->unsignedBigInteger('offre1_prix_kg_fcfa');
            $table->string('offre2_fournisseur');
            $table->date('offre2_date');
            $table->unsignedBigInteger('offre2_prix_kg_fcfa');
            // Valeur finalement retenue : décision de la direction, jamais calculée par le logiciel.
            $table->unsignedBigInteger('valeur_retenue_fcfa');
            $table->text('motif')->nullable();
            $table->foreignId('cree_par')->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['campagne_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('valorisations_stock');
    }
};
