<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Commission d'un pisteur (question 6) : sa règle (vide tant que la direction ne la choisit
     * pas) et la commission calculée sur chaque achat (vide si le calcul n'est pas activé ou si
     * le pisteur n'a pas de règle). C'est une somme DUE : son paiement (quand, comment) n'est pas
     * encore défini.
     */
    public function up(): void
    {
        Schema::table('pisteurs', function (Blueprint $table) {
            $table->string('commission_mode', 10)->nullable()->after('telephone');
            // FCFA par kilo, ou pour mille du montant, selon le mode. Entier (D4).
            $table->unsignedInteger('commission_valeur')->nullable()->after('commission_mode');
        });

        Schema::table('achats', function (Blueprint $table) {
            $table->unsignedBigInteger('commission_pisteur_fcfa')->nullable()->after('montant_especes_fcfa');
        });
    }

    public function down(): void
    {
        Schema::table('achats', function (Blueprint $table) {
            $table->dropColumn('commission_pisteur_fcfa');
        });
        Schema::table('pisteurs', function (Blueprint $table) {
            $table->dropColumn(['commission_mode', 'commission_valeur']);
        });
    }
};
