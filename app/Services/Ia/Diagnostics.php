<?php

namespace App\Services\Ia;

use App\Enums\StatutDiagnostic;
use App\Exceptions\OperationRefusee;
use App\Jobs\RedigerConseilIa;
use App\Jobs\TraiterDiagnosticIa;
use App\Models\Diagnostic;
use App\Models\FicheTraitement;
use App\Models\User;
use App\Models\Visite;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Diagnostics des photos de visite (skill ly-agricole-ia-conseil).
 *
 * - Demandé par la direction, un agronome ou un agent ; traité dans la file d'attente.
 * - « proposé » (modèle sûr de lui) ou « incertain » (pas de modèle, confiance trop basse) ;
 *   seul un AGRONOME le passe à « confirmé » ou « corrigé ».
 * - Le brouillon de conseil n'est rédigé qu'APRÈS validation, à partir des seules fiches
 *   proposables, et reste interne : rien n'est envoyé à un producteur.
 */
class Diagnostics
{
    /** @return int nombre de photos confiées au service IA */
    public static function demander(Visite $visite, User $auteur): int
    {
        if (! $auteur->can('demander-avis-ia')) {
            throw new OperationRefusee('Votre rôle ne permet pas de demander un avis IA.');
        }
        $photos = $visite->photos()->get();
        if ($photos->isEmpty()) {
            throw new OperationRefusee('Cette visite n\'a pas de photo : rien à diagnostiquer.');
        }

        return DB::transaction(function () use ($visite, $photos, $auteur) {
            $n = 0;
            foreach ($photos as $photo) {
                $d = Diagnostic::query()->firstOrNew(['visite_id' => $visite->id, 'photo_id' => $photo->id]);
                // Déjà traité : on ne redemande que ce qui a échoué.
                if ($d->exists && $d->statut !== StatutDiagnostic::Erreur) {
                    continue;
                }
                $d->fill(['statut' => StatutDiagnostic::EnAttente, 'motif' => null, 'demande_par' => $auteur->id])->save();
                TraiterDiagnosticIa::dispatch($d->id)->afterCommit();
                $n++;
            }

            return $n;
        });
    }

    /** Dans la file d'attente : envoie la photo au service IA et range la réponse. */
    public static function traiter(Diagnostic $d, ClientIa $client): void
    {
        $d->loadMissing('photo', 'visite.parcelle.produit');
        try {
            $r = $client->diagnostiquer($d->photo, self::culture($d));
        } catch (Throwable $e) {
            Log::warning('IA : diagnostic impossible', ['diagnostic' => $d->id, 'erreur' => $e->getMessage()]);
            $d->update(['statut' => StatutDiagnostic::Erreur, 'motif' => mb_substr($e->getMessage(), 0, 250)]);

            return;
        }

        // Le service ne rend que « proposé » ou « incertain » ; tout autre retour est incertain.
        $propose = $r['statut'] === 'propose' && is_string($r['classe']) && $r['classe'] !== '';
        $d->update([
            'statut' => $propose ? StatutDiagnostic::Propose : StatutDiagnostic::Incertain,
            'classe_proposee' => $propose ? $r['classe'] : null,
            'confiance_pour_mille' => $r['confiance_pour_mille'],
            'modele_vision' => $r['modele'],
            'motif' => mb_substr((string) $r['motif'], 0, 250),
        ]);
    }

    /**
     * L'agronome confirme la proposition, ou la corrige (classe différente). Un diagnostic
     * incertain se valide en donnant la classe retenue.
     */
    public static function valider(Diagnostic $d, User $agronome, ?string $classe, ?string $note = null): Diagnostic
    {
        if (! $agronome->can('valider-diagnostics')) {
            throw new OperationRefusee('Seul un agronome confirme ou corrige un diagnostic.');
        }
        if (! in_array($d->statut, [StatutDiagnostic::Propose, StatutDiagnostic::Incertain, StatutDiagnostic::Confirme, StatutDiagnostic::Corrige], true)) {
            throw new OperationRefusee('Ce diagnostic n\'a pas encore été traité par le service IA.');
        }
        $classe = $classe === null || trim($classe) === '' ? $d->classe_proposee : trim($classe);
        if ($classe === null) {
            throw new OperationRefusee('Indiquer la maladie, le ravageur ou « sain » : aucune proposition à confirmer.');
        }

        $d->update([
            'statut' => $classe === $d->classe_proposee ? StatutDiagnostic::Confirme : StatutDiagnostic::Corrige,
            'classe_retenue' => mb_substr($classe, 0, 255),
            'note_agronome' => $note === null || trim($note) === '' ? null : trim($note),
            'valide_par' => $agronome->id,
            'valide_at' => now(),
        ]);

        return $d;
    }

    /** Demande un brouillon de conseil (après validation seulement). */
    public static function demanderConseil(Diagnostic $d, User $agronome, string $observation): void
    {
        if (! $agronome->can('valider-diagnostics')) {
            throw new OperationRefusee('Seul un agronome demande un brouillon de conseil.');
        }
        if (! $d->statut->valide()) {
            throw new OperationRefusee('Confirmer ou corriger le diagnostic avant de rédiger un conseil.');
        }
        if (mb_strlen(trim($observation)) < 5) {
            throw new OperationRefusee('Décrire ce qui a été observé (5 caractères au moins).');
        }

        $d->update(['conseil_statut' => 'en_attente', 'conseil_texte' => null, 'conseil_fiches' => null, 'conseil_motifs' => null]);
        RedigerConseilIa::dispatch($d->id, trim($observation))->afterCommit();
    }

    /** Dans la file d'attente : rédaction à partir des seules fiches proposables, puis contrôle. */
    public static function rediger(Diagnostic $d, string $observation, ClientIa $client): void
    {
        $d->loadMissing('visite.parcelle.produit');
        $fiches = FicheTraitement::query()->where('produit_id', $d->visite->parcelle->produit_id)->where('statut', 'autorisee')
            ->orderBy('id')->get()->filter(fn (FicheTraitement $f) => $f->proposable())->values();

        try {
            $r = $client->conseil([
                'culture' => self::culture($d),
                'observation' => mb_substr($observation, 0, 2000),
                'diagnostic' => $d->classe_retenue,
                'fiches' => $fiches->map(fn (FicheTraitement $f) => $f->only([
                    'id', 'type', 'cible', 'titre', 'description', 'nom_commercial', 'matiere_active', 'dose', 'passages',
                    'delai_avant_recolte_jours', 'toxicite_humaine', 'protection', 'effet_abeilles',
                ]))->all(),
                'noms_connus' => Referentiel::nomsConnus(),
            ]);
        } catch (Throwable $e) {
            Log::warning('IA : conseil impossible', ['diagnostic' => $d->id, 'erreur' => $e->getMessage()]);
            $d->update(['conseil_statut' => 'erreur', 'conseil_motifs' => [mb_substr($e->getMessage(), 0, 250)]]);

            return;
        }

        // Défense en profondeur : on revérifie que les fiches citées sont bien celles fournies.
        $fournies = $fiches->pluck('id')->all();
        $citees = array_values(array_filter($r['fiches_citees'], fn ($id) => in_array($id, $fournies, true)));
        $rejete = $r['statut'] !== 'brouillon' || count($citees) !== count($r['fiches_citees']);

        $d->update([
            'conseil_statut' => $rejete ? 'rejete' : 'brouillon',
            'conseil_texte' => $r['texte'],
            'conseil_fiches' => $citees,
            'conseil_motifs' => $r['motifs_rejet'],
            'conseil_modele' => $r['modele'],
        ]);
    }

    /**
     * Brouillon lisible : chaque repère [FICHE-n] remplacé par le texte VALIDÉ de la fiche
     * (nom, dose, délai avant récolte, protection). Seul endroit où ces données apparaissent.
     */
    public static function rendreConseil(Diagnostic $d): string
    {
        $fiches = FicheTraitement::query()->whereKey($d->conseil_fiches ?? [])->get()->keyBy('id');

        return (string) preg_replace_callback('/\[FICHE-(\d+)\]/i', function (array $m) use ($fiches) {
            $f = $fiches->get((int) $m[1]);
            if ($f === null) {
                return '[fiche inconnue]';
            }
            if ($f->type !== 'chimique') {
                return "« {$f->titre} »";
            }

            return "« {$f->titre} » ({$f->nom_commercial}, {$f->matiere_active} : {$f->dose}, {$f->passages} passage(s), "
                ."délai avant récolte {$f->delai_avant_recolte_jours} jours, protection : {$f->protection})";
        }, (string) $d->conseil_texte);
    }

    private static function culture(Diagnostic $d): string
    {
        return $d->visite->parcelle->produit->nom ?? 'culture non précisée';
    }
}
