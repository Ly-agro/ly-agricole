<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Source du poids brut d'un achat (balance Bluetooth ou saisie à la main). Nullable : les
     * achats d'avant n'ont pas de source connue, on ne leur en invente pas.
     */
    public function up(): void
    {
        Schema::table('achats', function (Blueprint $table) {
            $table->string('poids_source', 10)->nullable()->after('poids_net_g');
        });
    }

    public function down(): void
    {
        Schema::table('achats', function (Blueprint $table) {
            $table->dropColumn('poids_source');
        });
    }
};
