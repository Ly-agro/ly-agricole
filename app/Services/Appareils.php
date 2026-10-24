<?php

namespace App\Services;

use App\Enums\ActionJournal;
use App\Exceptions\OperationRefusee;
use App\Models\Synchronisation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Téléphones connectés à l'appli terrain (question 25, décision du responsable projet : pas
 * d'expiration des jetons, mais une liste des appareils, la dernière synchronisation, et la
 * possibilité de COUPER un téléphone perdu ou volé).
 *
 * Un appareil = un jeton d'accès (créé à la connexion du téléphone, nommé « LY Terrain xxxxxxxx »
 * d'après la fin de l'identifiant de l'appareil). Révoquer supprime le jeton : la requête suivante
 * du téléphone est refusée (401) et l'appli demande de se reconnecter ; ce qui est déjà au bureau
 * y reste, ce qui n'était pas encore envoyé reste sur le téléphone perdu, inatteignable. La révocation
 * exige un MOTIF et laisse une ligne au journal (qui, quand, quel appareil, pourquoi).
 *
 * Le lien entre un jeton et ses synchronisations se fait par la fin de l'identifiant de l'appareil
 * (8 caractères) : c'est ce que le téléphone met dans le nom de son jeton.
 */
class Appareils
{
    public const MOTIF_MIN = 3;

    /**
     * Appareils connectés, le dernier servi d'abord.
     *
     * @return Collection<int, array{jeton: PersonalAccessToken, utilisateur: User|null, cree_le: Carbon|null, dernier_usage: Carbon|null, derniere_synchro: Synchronisation|null}>
     */
    public static function lister(): Collection
    {
        $jetons = PersonalAccessToken::query()->where('tokenable_type', (new User)->getMorphClass())->orderByDesc('last_used_at')->orderByDesc('id')->get();
        $utilisateurs = User::query()->whereIn('id', $jetons->pluck('tokenable_id'))->get()->keyBy('id');

        return $jetons->map(function (PersonalAccessToken $jeton) use ($utilisateurs) {
            $suffixe = self::suffixe($jeton->name);

            return [
                'jeton' => $jeton,
                'utilisateur' => $utilisateurs->get($jeton->tokenable_id),
                'cree_le' => $jeton->created_at,
                'dernier_usage' => $jeton->last_used_at,
                'derniere_synchro' => $suffixe === null ? null : Synchronisation::query()
                    ->where('user_id', $jeton->tokenable_id)->where('appareil_id', 'like', '%'.$suffixe)
                    ->orderByDesc('recu_at')->first(),
            ];
        });
    }

    /** Coupe UN appareil (un téléphone perdu, volé, ou remplacé). */
    public static function revoquer(User $auteur, PersonalAccessToken $jeton, string $motif): void
    {
        self::verifier($auteur, $motif);
        $utilisateur = User::query()->find($jeton->tokenable_id);

        Journal::enregistrer(ActionJournal::RevocationAppareil, $utilisateur, apres: [
            'appareil' => $jeton->name, 'motif' => trim($motif), 'portee' => 'un appareil',
        ], userId: $auteur->id);
        $jeton->delete();
    }

    /** Coupe TOUS les appareils d'un utilisateur ; rend le nombre de téléphones coupés. */
    public static function revoquerTous(User $auteur, User $cible, string $motif): int
    {
        self::verifier($auteur, $motif);
        $jetons = PersonalAccessToken::query()->where('tokenable_type', $cible->getMorphClass())->where('tokenable_id', $cible->id)->get();
        if ($jetons->isEmpty()) {
            throw new OperationRefusee("{$cible->nom} n'a aucun appareil connecté.");
        }

        Journal::enregistrer(ActionJournal::RevocationAppareil, $cible, apres: [
            'appareils' => $jetons->pluck('name')->all(), 'motif' => trim($motif), 'portee' => 'tous les appareils',
        ], userId: $auteur->id);
        $jetons->each->delete();

        return $jetons->count();
    }

    private static function verifier(User $auteur, string $motif): void
    {
        if (! $auteur->can('gerer-appareils')) {
            throw new OperationRefusee('Votre rôle ne permet pas de couper un appareil.');
        }
        if (mb_strlen(trim($motif)) < self::MOTIF_MIN) {
            throw new OperationRefusee('Le motif est obligatoire (ex. « téléphone perdu »).');
        }
    }

    /** Fin de l'identifiant d'appareil contenue dans le nom du jeton (« LY Terrain a1b2c3d4 »). */
    private static function suffixe(string $nomJeton): ?string
    {
        return preg_match('/([0-9a-f]{8})\s*$/i', $nomJeton, $m) === 1 ? strtolower($m[1]) : null;
    }
}
