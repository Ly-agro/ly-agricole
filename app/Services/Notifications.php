<?php

namespace App\Services;

use App\Exceptions\OperationRefusee;
use App\Models\AbonnementPush;
use App\Models\User;
use App\Notifications\AvisLy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Notifications de la plateforme (liste dans l'application + « push »). Point d'entrée
 * unique pour tous les blocs : la SOURCE d'un avis (achat à valider, écart de poids,
 * caisse basse…) reste à son module, qui appelle `envoyer()`.
 *
 * Un avis ne contient jamais de donnée personnelle d'un producteur au-delà de son nom :
 * une notification s'affiche sur l'écran verrouillé du téléphone.
 */
class Notifications
{
    /**
     * Le TITRE part aussi en push (écran verrouillé) : ni nom de producteur, ni montant.
     * Le texte complet reste dans l'application ; `$textePush`, générique, le remplace sur
     * l'appareil (par défaut : « Ouvrir LY AGRICOLE pour voir le détail. »).
     *
     * @param  iterable<User>  $destinataires
     * @param  string  $categorie  a_valider | valide | refuse | alerte
     */
    public static function envoyer(iterable $destinataires, string $titre, string $texte, ?string $url = null, string $categorie = 'alerte', ?string $textePush = null): void
    {
        $users = collect($destinataires)->filter(fn (User $u) => $u->actif)->unique('id')->values();
        if ($users->isEmpty()) {
            return;
        }

        Notification::send($users, new AvisLy($titre, $texte, $url, $categorie, $textePush));
    }

    /**
     * Utilisateurs actifs qui ont le droit `$droit`, sauf `$sauf` (l'auteur : il ne valide
     * pas sa propre saisie, inutile de le prévenir).
     *
     * @return Collection<int, User>
     */
    public static function ayantLeDroit(string $droit, ?int $sauf = null): Collection
    {
        return User::query()->where('actif', true)
            ->when($sauf !== null, fn ($q) => $q->whereKeyNot($sauf))
            ->get()->filter(fn (User $u) => $u->can($droit))->values();
    }

    /**
     * Enregistre un appareil. Un même navigateur ou téléphone passé à un autre utilisateur
     * (poste partagé) lui est réattribué : l'ancien ne reçoit plus rien dessus.
     */
    public static function abonner(User $user, string $canal, string $destination, ?string $p256dh = null, ?string $auth = null, ?string $appareil = null): AbonnementPush
    {
        if (! in_array($canal, [AbonnementPush::WEB, AbonnementPush::FCM], true)) {
            throw new OperationRefusee("Canal de notification inconnu : « {$canal} ».");
        }
        if ($canal === AbonnementPush::WEB && (! str_starts_with($destination, 'https://') || $p256dh === null || $auth === null)) {
            throw new OperationRefusee('Abonnement du navigateur incomplet : réessayer depuis le bouton « Activer ».');
        }

        return AbonnementPush::query()->updateOrCreate(
            ['empreinte' => hash('sha256', $destination)],
            [
                'user_id' => $user->id,
                'canal' => $canal,
                'destination' => $destination,
                'cle_p256dh' => $p256dh,
                'cle_auth' => $auth,
                'appareil' => $appareil === null ? null : mb_substr($appareil, 0, 255),
                'echecs' => 0,
            ],
        );
    }

    public static function desabonner(User $user, string $destination): void
    {
        AbonnementPush::query()->where('user_id', $user->id)->where('empreinte', hash('sha256', $destination))->delete();
    }

    /** Envoi réussi : compteur remis à zéro. */
    public static function reussi(AbonnementPush $abonnement): void
    {
        $abonnement->forceFill(['dernier_envoi_at' => now(), 'echecs' => 0])->save();
    }

    /** Échec : appareil disparu (oublié tout de suite) ou échec passager (compté). */
    public static function echec(AbonnementPush $abonnement, bool $definitif): void
    {
        if ($definitif || $abonnement->echecs + 1 >= AbonnementPush::ECHECS_MAX) {
            $abonnement->delete();

            return;
        }
        $abonnement->forceFill(['echecs' => $abonnement->echecs + 1])->save();
    }
}
