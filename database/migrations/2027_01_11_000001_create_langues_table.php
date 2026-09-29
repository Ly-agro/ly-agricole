<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Langue de préférence d'un producteur pour les messages (question 12). Le français est la
     * langue par défaut : un producteur sans langue (`langue_id` vide) est en français. Les
     * langues locales sont à confirmer par le responsable projet : aucune n'est pré-remplie, la
     * direction les ajoute quand elles sont connues.
     */
    public function up(): void
    {
        Schema::create('langues', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('nom');
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::table('producteurs', function (Blueprint $table) {
            $table->foreignId('langue_id')->nullable()->after('groupe_id')->constrained('langues');
        });
    }

    public function down(): void
    {
        Schema::table('producteurs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('langue_id');
        });
        Schema::dropIfExists('langues');
    }
};
