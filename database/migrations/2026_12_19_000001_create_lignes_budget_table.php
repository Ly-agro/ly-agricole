<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Budget de campagne (cahier §8) : un montant prévu par poste. C'est un plan, pas un
     * registre : une ligne se modifie (journalisée, avant → après), mais jamais sur une
     * campagne clôturée. Le réel n'est pas stocké : il se recalcule depuis les registres.
     */
    public function up(): void
    {
        Schema::create('lignes_budget', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campagne_id')->constrained('campagnes');
            // `achats`, `prets` ou `categorie` (une catégorie de dépense).
            $table->string('poste', 20);
            $table->foreignId('categorie_id')->nullable()->constrained('categories_depense');
            $table->unsignedBigInteger('montant_fcfa');
            $table->string('note')->nullable();
            $table->foreignId('cree_par')->constrained('users');
            $table->foreignId('modifie_par')->nullable()->constrained('users');
            $table->timestamps();

            // Un poste une seule fois par campagne. NULL n'étant pas comparé par l'index
            // (achats, prêts), App\Services\Budgets le vérifie aussi, sous verrou.
            $table->unique(['campagne_id', 'poste', 'categorie_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lignes_budget');
    }
};
