<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Semaine 6 : achats bord-champ, lots, stock, remboursements. Poids en GRAMMES,
 * argent en FCFA entiers (D4) ; mouvements de stock et remboursements immuables 🔒 (D5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pisteurs', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('telephone', 10)->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('lots', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->foreignId('produit_id')->constrained('produits');
            $table->foreignId('campagne_id')->constrained('campagnes');
            // Magasin d'origine ; le stock par magasin se lit dans les mouvements.
            $table->foreignId('magasin_id')->constrained('magasins');
            $table->string('statut', 20)->index();
            $table->string('description')->nullable();
            $table->foreignId('cree_par')->constrained('users');
            $table->timestamps();
        });

        Schema::create('achats', function (Blueprint $table) {
            // 📱 créable hors ligne (semaine 8) : UUID v7 fourni par le téléphone (D3).
            $table->uuid('id')->primary();
            $table->string('reference', 20)->unique();
            $table->foreignId('campagne_id')->constrained('campagnes');
            $table->foreignId('lot_id')->constrained('lots');
            $table->string('fournisseur_type', 20);
            $table->foreignUuid('producteur_id')->nullable()->constrained('producteurs');
            $table->foreignId('pisteur_id')->nullable()->constrained('pisteurs');
            $table->string('fournisseur_nom')->nullable();
            $table->foreignId('point_collecte_id')->nullable()->constrained('points_collecte');
            $table->dateTime('date_achat');
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->unsignedBigInteger('poids_brut_g');
            $table->unsignedBigInteger('tare_g');
            $table->unsignedBigInteger('poids_net_g');
            $table->unsignedSmallInteger('humidite_pour_mille')->nullable();
            $table->unsignedSmallInteger('kor_centieme_lbs')->nullable();
            $table->unsignedSmallInteger('grainage_noix_kg')->nullable();
            $table->unsignedBigInteger('prix_kg_fcfa');
            // Valeur totale au prix d'achat, arrondie une fois (skill argent-et-kilos §1).
            $table->unsignedBigInteger('montant_fcfa');
            // Prêt : kilos retenus pour le remboursement ; le reste est payé en espèces.
            $table->foreignUuid('pret_id')->nullable()->constrained('prets');
            $table->unsignedBigInteger('grammes_rembourses')->default(0);
            $table->unsignedBigInteger('montant_especes_fcfa');
            $table->foreignId('compte_id')->constrained('comptes_tresorerie');
            $table->foreignId('mouvement_id')->nullable()->constrained('mouvements_tresorerie');
            $table->string('photo_pesee')->nullable();
            $table->string('statut', 20)->index();
            $table->foreignId('cree_par')->constrained('users');
            $table->foreignId('valide_par')->nullable()->constrained('users');
            $table->timestamp('valide_at')->nullable();
            $table->string('motif_refus')->nullable();
            $table->timestamps();

            $table->index(['producteur_id', 'date_achat']);
        });

        Schema::create('mouvements_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lot_id')->constrained('lots');
            $table->foreignId('magasin_id')->constrained('magasins');
            $table->string('type', 30)->index();
            // Grammes signés : + entrée, − sortie (D4).
            $table->bigInteger('grammes');
            $table->date('date_mouvement');
            $table->string('motif')->nullable();
            $table->foreignUuid('achat_id')->nullable()->constrained('achats');
            // Les deux jambes d'un transfert partagent le même lien.
            $table->uuid('lien')->nullable()->index();
            $table->foreignId('annule_id')->nullable()->unique()->constrained('mouvements_stock');
            $table->foreignId('cree_par')->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['lot_id', 'magasin_id']);
        });

        Schema::create('remboursements', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('pret_id')->constrained('prets');
            $table->string('type', 20);
            // Signé : + remboursement, − contre-passation.
            $table->bigInteger('montant_fcfa');
            // Remboursement en nature : kilos retenus et prix appliqué (règle de la question 3).
            $table->bigInteger('grammes')->nullable();
            $table->unsignedBigInteger('prix_kg_fcfa')->nullable();
            $table->string('regle_valorisation', 30)->nullable();
            $table->foreignUuid('achat_id')->nullable()->constrained('achats');
            $table->foreignId('mouvement_id')->nullable()->constrained('mouvements_tresorerie');
            $table->date('date_remboursement');
            $table->string('motif')->nullable();
            $table->foreignId('annule_id')->nullable()->unique()->constrained('remboursements');
            $table->foreignId('cree_par')->constrained('users');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remboursements');
        Schema::dropIfExists('mouvements_stock');
        Schema::dropIfExists('achats');
        Schema::dropIfExists('lots');
        Schema::dropIfExists('pisteurs');
    }
};
