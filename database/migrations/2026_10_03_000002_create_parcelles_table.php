<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Parcelles 📱. La surface est calculée à partir du contour, jamais saisie ; sans
     * contour, elle reste vide (« non relevée »).
     */
    public function up(): void
    {
        Schema::create('parcelles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('producteur_id')->constrained('producteurs');
            $table->string('nom');
            // Géométrie GeoJSON (Polygon ou MultiPolygon, WGS84), sans les propriétés du fichier importé.
            $table->json('contour')->nullable();
            $table->unsignedBigInteger('surface_m2')->nullable();
            $table->foreignId('produit_id')->nullable()->constrained('produits');
            $table->unsignedSmallInteger('annee_plantation')->nullable();
            $table->unsignedInteger('nb_arbres')->nullable();
            $table->string('sol')->nullable();
            $table->boolean('acces_eau')->nullable();
            $table->foreignId('cree_par')->constrained('users');
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->unique(['producteur_id', 'nom']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parcelles');
    }
};
