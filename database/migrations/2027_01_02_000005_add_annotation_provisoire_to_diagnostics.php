<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Annotation PROVISOIRE d'une photo, faite en attendant un agronome (réponse 55 : « en
     * session Claude »). Rangée à part de la validation : elle ne change jamais le statut du
     * diagnostic et n'entre dans un jeu d'entraînement que sur demande explicite.
     */
    public function up(): void
    {
        Schema::table('diagnostics', function (Blueprint $table) {
            $table->string('annotation_classe')->nullable()->after('note_agronome');
            $table->string('annotation_source', 30)->nullable()->after('annotation_classe');
            $table->text('annotation_note')->nullable()->after('annotation_source');
            $table->timestamp('annotation_at')->nullable()->after('annotation_note');
        });
    }

    public function down(): void
    {
        Schema::table('diagnostics', function (Blueprint $table) {
            $table->dropColumn(['annotation_classe', 'annotation_source', 'annotation_note', 'annotation_at']);
        });
    }
};
