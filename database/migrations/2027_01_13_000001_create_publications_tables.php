<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ce que la vitrine publique affiche : les prix bord-champ (café, cacao, anacarde, autres) et les
     * actualités, saisis par la direction. Un prix affiché est une INFORMATION datée et sourcée, jamais
     * le prix officiel d'une campagne (`campagnes.prix_officiel_kg_fcfa`, qui est le plancher des achats).
     */
    public function up(): void
    {
        // Registre immuable 🔒 : un prix ne se corrige pas, on en publie un nouveau (l'historique reste).
        Schema::create('prix_marche', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produit_id')->constrained('produits');
            $table->unsignedBigInteger('prix_kg_fcfa');
            // Date à laquelle ce prix s'applique (communiqué, relevé), pas la date de saisie.
            $table->date('date_effet');
            // D'où vient le chiffre : obligatoire, il s'affiche avec le prix.
            $table->string('source', 255);
            $table->string('source_url', 500)->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignId('cree_par')->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['produit_id', 'date_effet', 'id']);
        });

        Schema::create('actualites', function (Blueprint $table) {
            $table->id();
            $table->string('titre', 200);
            // Texte simple, jamais du HTML : affiché échappé.
            $table->text('contenu');
            // Brouillon tant que `publie` est faux ; `publie_le` = date affichée.
            $table->boolean('publie')->default(false);
            $table->date('publie_le')->nullable();
            $table->foreignId('cree_par')->constrained('users');
            $table->timestamps();

            $table->index(['publie', 'publie_le']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actualites');
        Schema::dropIfExists('prix_marche');
    }
};
