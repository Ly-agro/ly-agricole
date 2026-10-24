<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Récupération des actualités sur internet : flux RSS / Atom choisis par la direction. Chaque
     * élément récupéré arrive en BROUILLON (titre, court extrait, lien vers l'article d'origine) et
     * n'apparaît sur la vitrine qu'une fois relu et publié par la direction.
     */
    public function up(): void
    {
        Schema::create('sources_actualites', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 150);
            $table->string('url', 500);
            $table->boolean('actif')->default(true);
            $table->timestamp('derniere_recuperation_at')->nullable();
            // ok | erreur ; le message dit pourquoi (jamais une trace technique brute).
            $table->string('dernier_statut', 10)->nullable();
            $table->string('dernier_message', 300)->nullable();
            $table->unsignedSmallInteger('dernier_nb')->default(0);
            $table->foreignId('cree_par')->constrained('users');
            $table->timestamps();
        });

        Schema::table('actualites', function (Blueprint $table) {
            // interne = écrite par la direction ; externe = venue d'un flux, à relire.
            $table->string('origine', 10)->default('interne')->after('publie_le');
            $table->string('source_nom', 150)->nullable()->after('origine');
            $table->string('lien_source', 500)->nullable()->after('source_nom');
            $table->date('date_source')->nullable()->after('lien_source');
            // Empreinte source + lien : la même actualité n'est jamais importée deux fois.
            $table->string('identifiant_externe', 40)->nullable()->unique()->after('date_source');
        });
    }

    public function down(): void
    {
        Schema::table('actualites', function (Blueprint $table) {
            $table->dropUnique(['identifiant_externe']);
            $table->dropColumn(['origine', 'source_nom', 'lien_source', 'date_source', 'identifiant_externe']);
        });
        Schema::dropIfExists('sources_actualites');
    }
};
