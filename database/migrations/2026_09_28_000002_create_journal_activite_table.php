<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Registre immuable 🔒 (docs/MODELE_DE_DONNEES.md) : qui a fait quoi, quand.
     */
    public function up(): void
    {
        Schema::create('journal_activite', function (Blueprint $table) {
            $table->id();
            // Pas de `nullOnDelete` : il réécrirait le journal. Un compte ne se
            // supprime pas, il se désactive.
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('action', 50);
            $table->string('objet_type', 50)->nullable();
            // Texte : les objets créés hors ligne auront un UUID (D3).
            $table->string('objet_id', 36)->nullable();
            $table->json('avant')->nullable();
            $table->json('apres')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('appareil')->nullable();
            $table->timestamp('at')->useCurrent();

            $table->index(['objet_type', 'objet_id']);
            $table->index('action');
            $table->index('at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_activite');
    }
};
