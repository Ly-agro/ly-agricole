<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Notifications : la liste lue dans l'application (table standard de Laravel) et les
     * appareils abonnés au « push » — navigateur (Web Push) ou téléphone (Firebase).
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('abonnements_push', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            // `web` : navigateur (Web Push, clés VAPID) ; `fcm` : appli terrain (Firebase).
            $table->string('canal', 10);
            // Adresse Web Push ou jeton FCM : longs, d'où l'empreinte pour l'unicité.
            $table->text('destination');
            $table->char('empreinte', 64)->unique();
            $table->string('cle_p256dh')->nullable();
            $table->string('cle_auth')->nullable();
            $table->string('appareil')->nullable();
            $table->timestamp('dernier_envoi_at')->nullable();
            $table->unsignedSmallInteger('echecs')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'canal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abonnements_push');
        Schema::dropIfExists('notifications');
    }
};
