<?php

namespace App\Services;

use App\Enums\ActionJournal;
use App\Enums\Role;
use App\Models\JournalActivite;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * La direction est prévenue de CHAQUE action d'un agent de terrain (demande du responsable
 * projet, 2026-10-01) : avis dans l'application, push sur ses appareils et diffusion en direct
 * (Reverb). Branché sur le journal d'activité : tout ce qui y est écrit par un agent part, sans
 * que chaque module ait à y penser.
 *
 * Exclu : connexions et déconnexions (du bruit, pas une action sur les données).
 * Le titre part sur l'écran verrouillé : nom de l'agent et type d'objet seulement, jamais un
 * nom de producteur ni un montant.
 */
class SuiviAgents
{
    private const ACTIONS_IGNOREES = [
        ActionJournal::Connexion, ActionJournal::Deconnexion, ActionJournal::EchecConnexion, ActionJournal::BlocageConnexion,
    ];

    /** Nom de l'objet, au singulier avec son article, par alias du journal. */
    private const OBJETS = [
        'producteur' => 'un producteur', 'parcelle' => 'une parcelle', 'groupe_producteurs' => 'un groupe de producteurs',
        'achat' => 'un achat', 'depense' => 'une dépense', 'pret' => 'une demande de prêt', 'visite' => 'une visite',
        'mouvement_tresorerie' => 'un mouvement de caisse', 'mouvement_stock' => 'un mouvement de stock',
        'remboursement' => 'un remboursement', 'decaissement' => 'une remise au producteur', 'lot' => 'un lot',
        'photo_terrain' => 'une photo', 'diagnostic' => 'un diagnostic', 'pisteur' => 'un pisteur',
    ];

    /** Où ouvrir l'objet : route nommée et, si elle en prend un, le nom de son paramètre. */
    private const LIENS = [
        'producteur' => ['producteurs.fiche', 'producteur'], 'pret' => ['prets.fiche', 'pret'], 'lot' => ['lots.fiche', 'lot'],
        'achat' => ['achats', null], 'depense' => ['depenses', null], 'visite' => ['visites', null],
        'parcelle' => ['producteurs', null], 'mouvement_stock' => ['lots', null], 'mouvement_tresorerie' => ['tresorerie', null],
    ];

    public static function apresJournal(JournalActivite $entree): void
    {
        if ($entree->user_id === null || in_array($entree->action, self::ACTIONS_IGNOREES, true)) {
            return;
        }
        $agent = User::query()->find($entree->user_id);
        if ($agent === null || $agent->role !== Role::Agent) {
            return;
        }

        $destinataires = User::query()->where('actif', true)->where('role', Role::Direction->value)->get();
        if ($destinataires->isEmpty()) {
            return;
        }

        $objet = self::OBJETS[$entree->objet_type ?? ''] ?? ($entree->objet_type !== null ? 'un élément ('.str_replace('_', ' ', $entree->objet_type).')' : 'une opération');
        $verbe = match ($entree->action) {
            ActionJournal::Creation => 'a enregistré',
            ActionJournal::Modification => 'a modifié',
            ActionJournal::Suppression => 'a supprimé',
            default => mb_strtolower($entree->action->libelle()).' :',
        };
        $reference = self::reference($entree);

        Notifications::envoyer(
            $destinataires,
            "{$agent->nom} (agent) {$verbe} {$objet}",
            trim("{$agent->nom} {$verbe} {$objet}".($reference !== null ? " ({$reference})" : '').', le '.$entree->at->format('d/m/Y à H:i').'.'),
            self::lien($entree),
            'activite',
            'Une action d\'agent vient d\'être enregistrée.',
        );
    }

    /** Référence lisible (ACH-000012, LYPR-000003…) si l'objet en a une : jamais un nom de producteur. */
    private static function reference(JournalActivite $entree): ?string
    {
        $valeur = $entree->apres['reference'] ?? $entree->avant['reference'] ?? $entree->apres['code'] ?? null;

        return is_string($valeur) && $valeur !== '' ? mb_substr($valeur, 0, 40) : null;
    }

    private static function lien(JournalActivite $entree): ?string
    {
        [$route, $parametre] = self::LIENS[$entree->objet_type ?? ''] ?? [null, null];
        if ($route === null || ! Route::has($route)) {
            return null;
        }
        if ($parametre === null) {
            return route($route);
        }
        if ($entree->objet_id === null || Relation::getMorphedModel((string) $entree->objet_type) === null) {
            return null;
        }
        try {
            return route($route, [$parametre => $entree->objet_id]);
        } catch (Throwable) {
            return null;
        }
    }
}
