<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Semaine 9 : photos envoyées par l'appli terrain À PART des opérations (skill
 * terrain-hors-ligne : fichiers lourds, réseau faible). Une opération référence l'UUID
 * de la photo ; la photo peut arriver avant ou après.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photos_terrain', function (Blueprint $table) {
            // UUID v7 généré sur le téléphone : clé d'idempotence de l'envoi.
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users');
            $table->string('appareil_id', 100);
            $table->string('chemin');
            $table->string('mime', 50);
            $table->unsignedInteger('taille_octets');
            // Heure et position du téléphone à la prise (preuve de pesée).
            $table->timestamp('prise_at')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->timestamp('recu_at')->useCurrent();
        });

        Schema::table('parcelles', function (Blueprint $table) {
            // import (fichier GeoJSON au bureau) | gps (relevé en marchant sur le téléphone).
            $table->string('contour_origine', 10)->nullable()->after('surface_m2');
        });
    }

    public function down(): void
    {
        Schema::table('parcelles', function (Blueprint $table) {
            $table->dropColumn('contour_origine');
        });
        Schema::dropIfExists('photos_terrain');
    }
};
