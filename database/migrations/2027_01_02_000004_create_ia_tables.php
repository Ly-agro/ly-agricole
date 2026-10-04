<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IA (phase 3, skill ly-agricole-ia-conseil).
     *
     * - `fiches_traitement` : référentiel tenu et daté par l'agronome. Le modèle de langage ne
     *   cite que ces fiches ; une fiche chimique incomplète n'est pas proposable. Vide au départ :
     *   jamais rempli de mémoire.
     * - `diagnostics` : avis sur une photo de visite. « proposé » ou « incertain » tant qu'un
     *   agronome ne l'a pas confirmé ou corrigé ; le brouillon de conseil reste interne.
     */
    public function up(): void
    {
        Schema::create('fiches_traitement', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produit_id')->constrained('produits'); // culture
            $table->string('type', 12); // pratique | biologique | chimique
            $table->string('cible');    // maladie, ravageur ou « général »
            $table->string('titre');    // intitulé sans nom commercial ni dose
            $table->text('description')->nullable();
            // Obligatoires pour une fiche chimique (sinon elle n'est pas proposable).
            $table->string('nom_commercial')->nullable();
            $table->string('matiere_active')->nullable();
            $table->string('dose')->nullable();
            $table->unsignedSmallInteger('passages')->nullable();
            $table->unsignedSmallInteger('delai_avant_recolte_jours')->nullable();
            $table->string('toxicite_humaine')->nullable();
            $table->string('protection')->nullable();
            $table->string('effet_abeilles')->nullable();
            $table->string('reference_homologation')->nullable();
            // autorisee | retiree | interdite : une fiche ne se supprime pas.
            $table->string('statut', 12)->default('autorisee');
            $table->date('validee_le');
            $table->foreignId('validee_par')->constrained('users');
            $table->timestamps();

            $table->index(['produit_id', 'statut']);
        });

        Schema::create('diagnostics', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('visite_id')->constrained('visites');
            $table->foreignUuid('photo_id')->constrained('photos_terrain');
            // en_attente | propose | incertain | confirme | corrige | erreur
            $table->string('statut', 12)->default('en_attente');
            $table->string('classe_proposee')->nullable();
            $table->unsignedSmallInteger('confiance_pour_mille')->nullable();
            $table->string('modele_vision')->nullable();
            $table->string('motif')->nullable(); // pourquoi « incertain » ou « erreur »
            // Ce qu'un agronome a retenu (confirmé ou corrigé) : la vérité d'entraînement.
            $table->string('classe_retenue')->nullable();
            $table->text('note_agronome')->nullable();
            $table->foreignId('valide_par')->nullable()->constrained('users');
            $table->timestamp('valide_at')->nullable();
            // Brouillon de conseil (jamais envoyé tel quel) et son contrôle.
            $table->string('conseil_statut', 12)->nullable(); // en_attente | brouillon | rejete | erreur
            $table->text('conseil_texte')->nullable();
            $table->json('conseil_fiches')->nullable();
            $table->json('conseil_motifs')->nullable();
            $table->string('conseil_modele')->nullable();
            $table->foreignId('demande_par')->constrained('users');
            $table->timestamps();

            $table->unique(['visite_id', 'photo_id']);
            $table->index('statut');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnostics');
        Schema::dropIfExists('fiches_traitement');
    }
};
