<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Intrants (semaine 5) : stock par magasin = somme des mouvements (🔒, D5) ; une
 * distribution à crédit rattachée à un prêt en fige la valeur (FCFA entiers, D4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intrants', function (Blueprint $table) {
            $table->id();
            // Le conditionnement fait partie du nom : « NPK 15-15-15 — sac de 50 kg ».
            $table->string('nom')->unique();
            $table->string('unite', 20);
            // Prix courant d'une unité ; la valeur d'une distribution est figée à sa date.
            $table->unsignedBigInteger('prix_unitaire_fcfa');
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('mouvements_intrants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intrant_id')->constrained('intrants');
            $table->foreignId('magasin_id')->constrained('magasins');
            $table->string('type', 20)->index();
            // Nombre d'unités, signé : + entrée, − sortie.
            $table->bigInteger('quantite');
            // Distribution à crédit : valeur figée (quantité × prix du jour), signée comme la quantité.
            $table->bigInteger('valeur_fcfa')->nullable();
            $table->unsignedBigInteger('prix_unitaire_fcfa')->nullable();
            $table->foreignUuid('pret_id')->nullable()->constrained('prets');
            $table->date('date_mouvement');
            $table->string('motif')->nullable();
            $table->foreignId('annule_id')->nullable()->unique()->constrained('mouvements_intrants');
            $table->foreignId('cree_par')->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['intrant_id', 'magasin_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mouvements_intrants');
        Schema::dropIfExists('intrants');
    }
};
