<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Alertes déjà envoyées (App\Services\Alertes) : une même alerte ne part qu'une fois
     * — par jour pour les rappels quotidiens, une fois tout court pour un dépassement.
     */
    public function up(): void
    {
        Schema::create('alertes_envoyees', function (Blueprint $table) {
            $table->id();
            $table->string('cle', 191)->unique();
            $table->timestamp('envoye_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alertes_envoyees');
    }
};
