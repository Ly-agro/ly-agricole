<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trésorerie et dépenses (semaine 3). Argent en FCFA entiers (D4). Les mouvements
 * sont un registre immuable 🔒 (D5) : le solde d'un compte est la somme de ses
 * mouvements, jamais une colonne.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comptes_tresorerie', function (Blueprint $table) {
            $table->id();
            $table->string('nom')->unique();
            $table->string('type', 20);
            // Caisse d'un agent : son solde est ce qu'il lui reste à justifier.
            $table->foreignId('titulaire_id')->nullable()->constrained('users');
            // Compte dédié aux fonds d'une campagne (contrat art. 5).
            $table->foreignId('campagne_id')->nullable()->constrained('campagnes');
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('mouvements_tresorerie', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compte_id')->constrained('comptes_tresorerie');
            $table->string('sens', 10);
            $table->unsignedBigInteger('montant_fcfa');
            $table->string('nature', 30)->index();
            $table->date('date_operation');
            $table->string('libelle');
            $table->string('reference_externe', 100)->nullable();
            // Les deux jambes d'un virement partagent le même lien.
            $table->uuid('lien')->nullable()->index();
            $table->string('source_type', 50)->nullable();
            $table->string('source_id', 36)->nullable();
            // Contre-passation : pointe l'original ; un mouvement ne s'annule qu'une fois.
            $table->foreignId('annule_id')->nullable()->unique()->constrained('mouvements_tresorerie');
            $table->string('motif')->nullable();
            $table->foreignId('cree_par')->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['compte_id', 'date_operation']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('categories_depense', function (Blueprint $table) {
            $table->id();
            $table->string('nom')->unique();
            $table->string('code_syscohada', 20)->nullable();
            // Contrat art. 10.3 : charge interdite sur les fonds d'une campagne.
            $table->boolean('exclue_fonds_campagne')->default(false);
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('depenses', function (Blueprint $table) {
            // 📱 créable hors ligne (dépense terrain), UUID v7 (D3).
            $table->uuid('id')->primary();
            $table->foreignId('categorie_id')->constrained('categories_depense');
            $table->foreignId('compte_id')->constrained('comptes_tresorerie');
            $table->unsignedBigInteger('montant_fcfa');
            $table->date('date_depense');
            $table->string('beneficiaire');
            $table->string('description')->nullable();
            $table->string('justificatif');
            $table->foreignId('campagne_id')->nullable()->constrained('campagnes');
            $table->foreignUuid('parcelle_id')->nullable()->constrained('parcelles');
            $table->string('statut', 20)->index();
            $table->foreignId('cree_par')->constrained('users');
            $table->foreignId('valide_par')->nullable()->constrained('users');
            $table->timestamp('valide_at')->nullable();
            $table->string('motif_refus')->nullable();
            $table->foreignId('mouvement_id')->nullable()->constrained('mouvements_tresorerie');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depenses');
        Schema::dropIfExists('categories_depense');
        Schema::dropIfExists('mouvements_tresorerie');
        Schema::dropIfExists('comptes_tresorerie');
    }
};
