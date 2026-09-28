<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Producteurs 📱 et groupes (docs/MODELE_DE_DONNEES.md). Données personnelles
 * (loi 2013-450) : pas de producteur sans consentement tracé.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Numéros lisibles attribués par le serveur (code de la carte producteur…).
        Schema::create('compteurs', function (Blueprint $table) {
            $table->string('nom', 50)->primary();
            $table->unsignedBigInteger('valeur')->default(0);
        });

        Schema::create('groupes_producteurs', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->foreignId('village_id')->constrained('villages');
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->unique(['village_id', 'nom']);
        });

        Schema::create('producteurs', function (Blueprint $table) {
            // UUID v7, fourni par le téléphone quand la fiche est créée hors ligne (D3).
            $table->uuid('id')->primary();
            // Code de la carte (QR) : attribué par le serveur, jamais par le téléphone.
            $table->string('code', 20)->unique();
            $table->string('nom');
            $table->string('prenoms');
            $table->char('sexe', 1)->nullable();
            $table->unsignedSmallInteger('annee_naissance')->nullable();
            $table->string('telephone', 10)->nullable()->index();
            $table->string('numero_mobile_money', 10)->nullable()->index();
            $table->string('operateur_mm', 20)->nullable();
            $table->string('piece_type', 30)->nullable();
            $table->string('piece_numero', 50)->nullable();
            // Chemin sur le disque privé : une photo d'identité n'est jamais publique.
            $table->string('photo')->nullable();
            $table->foreignId('village_id')->constrained('villages');
            $table->foreignId('groupe_id')->nullable()->constrained('groupes_producteurs');
            $table->timestamp('consentement_at');
            $table->foreignId('consentement_par')->constrained('users');
            $table->foreignId('cree_par')->constrained('users');
            $table->boolean('actif')->default(true);
            $table->timestamps();

            // Une pièce d'identité = une personne.
            $table->unique(['piece_type', 'piece_numero']);
            $table->index(['nom', 'prenoms']);
        });

        Schema::table('groupes_producteurs', function (Blueprint $table) {
            $table->foreignUuid('responsable_id')->nullable()->after('village_id')->constrained('producteurs');
        });
    }

    public function down(): void
    {
        Schema::table('groupes_producteurs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('responsable_id');
        });
        Schema::dropIfExists('producteurs');
        Schema::dropIfExists('groupes_producteurs');
        Schema::dropIfExists('compteurs');
    }
};
