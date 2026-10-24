<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prêts de campagne (semaine 4). Argent en FCFA entiers, poids en grammes (D4) ;
 * décaissements immuables 🔒 (D5) ; validation par d'autres que l'auteur (D6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reference', 20)->unique();
            $table->foreignUuid('producteur_id')->constrained('producteurs');
            $table->foreignId('campagne_id')->constrained('campagnes');
            $table->unsignedBigInteger('montant_fcfa');
            $table->string('forme', 20);
            // Prix servant à ESTIMER les kilos attendus ; la valorisation d'un
            // remboursement en kilos est la question ouverte n° 3.
            $table->unsignedBigInteger('prix_reference_kg_fcfa')->nullable();
            $table->unsignedBigInteger('grammes_attendus')->nullable();
            $table->date('echeance');
            $table->string('statut', 20)->index();
            $table->unsignedTinyInteger('validations_requises');
            // Contrat art. 17.3 : proche de la direction ⇒ accord écrit obligatoire.
            $table->boolean('partie_liee')->default(false);
            $table->string('accord_ecrit')->nullable();
            $table->string('motif_refus')->nullable();
            $table->string('motif_cloture')->nullable();
            $table->foreignId('cree_par')->constrained('users');
            $table->timestamp('valide_at')->nullable();
            $table->timestamps();
        });

        // Chaque validation est une ligne : un prêt au-dessus du seuil en demande deux,
        // par deux personnes différentes, toutes deux différentes de l'auteur.
        Schema::create('validations_pret', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('pret_id')->constrained('prets');
            $table->foreignId('user_id')->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['pret_id', 'user_id']);
        });

        Schema::create('pret_parcelle', function (Blueprint $table) {
            $table->foreignUuid('pret_id')->constrained('prets');
            $table->foreignUuid('parcelle_id')->constrained('parcelles');

            $table->primary(['pret_id', 'parcelle_id']);
        });

        Schema::create('decaissements', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('pret_id')->constrained('prets');
            $table->unsignedBigInteger('montant_fcfa');
            $table->string('mode', 20);
            $table->string('reference_paiement', 100)->nullable();
            $table->foreignId('compte_id')->constrained('comptes_tresorerie');
            $table->date('date_decaissement');
            $table->string('justificatif')->nullable();
            $table->foreignId('mouvement_id')->constrained('mouvements_tresorerie');
            $table->foreignId('cree_par')->constrained('users');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decaissements');
        Schema::dropIfExists('pret_parcelle');
        Schema::dropIfExists('validations_pret');
        Schema::dropIfExists('prets');
    }
};
