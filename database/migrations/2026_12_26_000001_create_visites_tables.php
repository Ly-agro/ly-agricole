<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Visites de parcelle 📱 (cahier §4) : fiche courte saisie hors ligne, avec ses
     * photos géolocalisées et datées. Une visite est un constat : ni argent ni poids.
     */
    public function up(): void
    {
        Schema::create('visites', function (Blueprint $table) {
            // UUID v7 du téléphone : clé d'idempotence de l'envoi.
            $table->uuid('id')->primary();
            $table->foreignUuid('parcelle_id')->constrained('parcelles');
            $table->date('date_visite');
            // Position du téléphone à l'enregistrement de la fiche (null : pas de signal).
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            // Codes de App\Enums\PratiqueCulturale constatés sur la parcelle.
            $table->json('pratiques')->nullable();
            $table->text('observations')->nullable();
            $table->foreignId('cree_par')->constrained('users');
            // Heure du téléphone (peut être fausse) ; created_at = heure du serveur.
            $table->timestamp('cree_at')->nullable();
            $table->timestamps();

            $table->index(['parcelle_id', 'date_visite']);
        });

        Schema::create('visite_photo', function (Blueprint $table) {
            $table->foreignUuid('visite_id')->constrained('visites');
            $table->foreignUuid('photo_id')->constrained('photos_terrain');
            $table->primary(['visite_id', 'photo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visite_photo');
        Schema::dropIfExists('visites');
    }
};
