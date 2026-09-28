<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Référentiels de la semaine 1 (docs/MODELE_DE_DONNEES.md). Pas de suppression :
 * un référentiel sera cité par des producteurs, achats, lots… on le désactive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->string('nom')->unique();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('villages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->constrained('zones');
            $table->string('nom');
            // Coordonnées GPS : ce n'est ni de l'argent ni un poids (D4 ne s'applique pas).
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->unique(['zone_id', 'nom']);
        });

        Schema::create('produits', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('nom');
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('campagnes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produit_id')->constrained('produits');
            $table->string('code', 20);
            $table->date('debut');
            $table->date('fin');
            $table->string('statut', 20)->default('preparation')->index();
            // FCFA entiers (D4). Vide tant que l'État ne l'a pas annoncé.
            $table->unsignedBigInteger('prix_officiel_kg_fcfa')->nullable();
            $table->timestamps();

            $table->unique(['produit_id', 'code']);
        });

        Schema::create('magasins', function (Blueprint $table) {
            $table->id();
            $table->string('nom')->unique();
            $table->foreignId('village_id')->constrained('villages');
            // Grammes (D4), saisis et affichés en kg.
            $table->unsignedBigInteger('capacite_g')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('points_collecte', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->foreignId('village_id')->constrained('villages');
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->unique(['village_id', 'nom']);
        });

        Schema::create('parametres', function (Blueprint $table) {
            $table->id();
            // Clés connues du code : App\Enums\CleParametre.
            $table->string('cle', 60)->unique();
            $table->string('valeur')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parametres');
        Schema::dropIfExists('points_collecte');
        Schema::dropIfExists('magasins');
        Schema::dropIfExists('campagnes');
        Schema::dropIfExists('produits');
        Schema::dropIfExists('villages');
        Schema::dropIfExists('zones');
    }
};
