<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Annulation d'un achat par la direction (« Supprimer », décision du 2026-10-01) : l'achat
 * reste, marqué annulé, avec qui, quand et pourquoi ; ses effets sont contre-passés.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('achats', function (Blueprint $table) {
            $table->foreignId('annule_par')->nullable()->constrained('users');
            $table->timestamp('annule_at')->nullable();
            $table->string('motif_annulation')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('achats', function (Blueprint $table) {
            $table->dropConstrainedForeignId('annule_par');
            $table->dropColumn(['annule_at', 'motif_annulation']);
        });
    }
};
