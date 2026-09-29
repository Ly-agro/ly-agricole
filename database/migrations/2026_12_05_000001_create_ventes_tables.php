<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reventes (cahier §7, stade Revente) et leurs encaissements (stade Encaissement,
     * séparé : l'acheteur peut payer à la livraison ou à terme — question ouverte n° 15).
     */
    public function up(): void
    {
        Schema::create('ventes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reference', 20)->unique();
            $table->foreignId('campagne_id')->constrained('campagnes');
            $table->foreignId('lot_id')->constrained('lots');
            $table->string('type_acheteur', 20);
            $table->string('acheteur_nom');
            $table->dateTime('date_vente');
            $table->unsignedBigInteger('poids_net_g');
            $table->unsignedBigInteger('prix_kg_fcfa');
            $table->unsignedBigInteger('montant_fcfa');
            $table->string('qualite_acceptee')->nullable();
            // Chemin sur le disque privé (comme depenses.justificatif) ; peut arriver après.
            $table->string('facture')->nullable();
            $table->string('statut', 20)->index();
            $table->foreignId('cree_par')->constrained('users');
            $table->foreignId('valide_par')->nullable()->constrained('users');
            $table->timestamp('valide_at')->nullable();
            $table->string('motif_refus')->nullable();
            $table->timestamps();

            $table->index(['campagne_id', 'date_vente']);
        });

        // Registre immuable 🔒 : l'argent réellement reçu, distinct de la vente (D5).
        // Montant signé : une contre-passation est négative (même schéma que remboursements).
        Schema::create('encaissements', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('vente_id')->constrained('ventes');
            $table->bigInteger('montant_fcfa');
            $table->foreignId('compte_id')->constrained('comptes_tresorerie');
            $table->date('date_encaissement');
            $table->string('reference_paiement', 100)->nullable();
            $table->foreignId('mouvement_id')->nullable()->constrained('mouvements_tresorerie');
            $table->string('motif')->nullable();
            $table->foreignId('annule_id')->nullable()->unique()->constrained('encaissements');
            $table->foreignId('cree_par')->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['vente_id']);
        });

        // Sortie de stock d'une vente : même table que l'entrée d'un achat (mouvements_stock).
        // Ajoutée après coup (la table est de la semaine 6, ventes n'existait pas encore).
        Schema::table('mouvements_stock', function (Blueprint $table) {
            $table->foreignUuid('vente_id')->nullable()->after('achat_id')->constrained('ventes');
        });
    }

    public function down(): void
    {
        Schema::table('mouvements_stock', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vente_id');
        });
        Schema::dropIfExists('encaissements');
        Schema::dropIfExists('ventes');
    }
};
