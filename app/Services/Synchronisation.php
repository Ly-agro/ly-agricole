<?php

namespace App\Services;

use App\Enums\ActionJournal;
use App\Enums\OperateurMobileMoney;
use App\Enums\PratiqueCulturale;
use App\Enums\Sexe;
use App\Enums\SourcePoids;
use App\Enums\TypeFournisseur;
use App\Enums\TypePiece;
use App\Exceptions\OperationRefusee;
use App\Models\OperationRecue;
use App\Models\Parcelle;
use App\Models\PhotoTerrain;
use App\Models\Producteur;
use App\Models\Synchronisation as Envoi;
use App\Models\User;
use App\Models\Visite;
use App\Services\Geo\Contour;
use App\Support\Telephone;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

/**
 * Réception des opérations de l'appli terrain (skill terrain-hors-ligne, D2).
 *
 * - L'UUID de l'opération, généré sur le téléphone, devient l'identifiant de ce qu'elle
 *   crée (producteur, achat) : c'est la clé d'idempotence.
 * - Opérations traitées une par une, dans l'ordre reçu, chacune dans sa transaction :
 *   une opération refusée ne bloque pas les autres.
 * - Déjà acceptée ⇒ « deja_recu » (cas normal d'une réponse perdue), rien n'est refait.
 *   Rejetée ⇒ peut être renvoyée corrigée avec le même UUID.
 * - Le serveur revalide tout, par les mêmes services que le bureau.
 */
class Synchronisation
{
    public const TYPES = ['producteur', 'achat', 'parcelle', 'depense', 'visite'];

    public const MAX_OPERATIONS = 500;

    /**
     * @param  list<array<string, mixed>>  $operations
     * @return array{synchronisation_id: int, resultats: list<array{uuid: string, statut: string, motif?: string}>}
     */
    public static function recevoir(string $appareilId, array $operations, User $auteur): array
    {
        $envoi = Envoi::query()->create([
            'appareil_id' => $appareilId,
            'user_id' => $auteur->id,
            'recu_at' => now(),
            'nb_operations' => count($operations),
            'nb_acceptees' => 0,
            'nb_deja_recues' => 0,
            'nb_rejetees' => 0,
        ]);

        $resultats = [];
        foreach ($operations as $operation) {
            $resultats[] = self::traiter($envoi, $operation, $auteur);
        }

        $compte = array_count_values(array_column($resultats, 'statut'));
        $envoi->update([
            'nb_acceptees' => $compte[OperationRecue::ACCEPTE] ?? 0,
            'nb_deja_recues' => $compte[OperationRecue::DEJA_RECU] ?? 0,
            'nb_rejetees' => $compte[OperationRecue::REJETE] ?? 0,
        ]);

        return ['synchronisation_id' => $envoi->id, 'resultats' => $resultats];
    }

    /**
     * @param  array<string, mixed>  $operation
     * @return array{uuid: string, statut: string, motif?: string}
     */
    private static function traiter(Envoi $envoi, array $operation, User $auteur): array
    {
        $uuid = strtolower((string) ($operation['uuid'] ?? ''));
        $type = (string) ($operation['type'] ?? '');

        if (! Str::isUuid($uuid)) {
            // Sans UUID valide, rien ne peut être tracé : réponse seulement.
            return ['uuid' => $uuid, 'statut' => OperationRecue::REJETE, 'motif' => 'UUID absent ou invalide.'];
        }

        $deja = self::dejaAcceptee($uuid, $type);
        if ($deja !== null) {
            return $deja;
        }

        $creeAt = self::date($operation['cree_at'] ?? null);

        try {
            DB::transaction(function () use ($envoi, $uuid, $type, $operation, $auteur, $creeAt) {
                $donnees = is_array($operation['donnees'] ?? null) ? $operation['donnees'] : [];

                match ($type) {
                    'producteur' => self::creerProducteur($uuid, $donnees, $auteur),
                    'achat' => self::creerAchat($uuid, $donnees, $auteur),
                    'parcelle' => self::creerParcelle($uuid, $donnees, $auteur),
                    'depense' => self::creerDepense($uuid, $donnees, $auteur),
                    'visite' => self::creerVisite($uuid, $donnees, $auteur, $creeAt),
                    default => throw new OperationRefusee("Type d'opération inconnu : « {$type} »."),
                };

                self::tracer($envoi, $uuid, $type, $auteur, $creeAt, OperationRecue::ACCEPTE);
            });
        } catch (UniqueConstraintViolationException $e) {
            // Même UUID reçu en même temps par deux envois : l'autre a gagné.
            $deja = self::dejaAcceptee($uuid, $type);
            if ($deja !== null) {
                return $deja;
            }

            return self::rejeter($envoi, $uuid, $type, $auteur, $creeAt, 'Doublon refusé par la base (identifiant ou pièce déjà enregistrés).');
        } catch (OperationRefusee $e) {
            return self::rejeter($envoi, $uuid, $type, $auteur, $creeAt, $e->getMessage());
        } catch (ValidationException $e) {
            return self::rejeter($envoi, $uuid, $type, $auteur, $creeAt, implode(' ', $e->validator->errors()->all()));
        } catch (ModelNotFoundException $e) {
            return self::rejeter($envoi, $uuid, $type, $auteur, $creeAt, 'Référence introuvable sur le serveur (campagne, lot, compte, producteur ou prêt).');
        } catch (Throwable $e) {
            // Jamais perdue en silence : rejetée avec un motif, trace complète dans les logs.
            Log::error('Synchronisation : opération en erreur', ['uuid' => $uuid, 'type' => $type, 'exception' => $e]);

            return self::rejeter($envoi, $uuid, $type, $auteur, $creeAt, 'Erreur du serveur : renvoyer plus tard ; si elle persiste, prévenir le bureau.');
        }

        return ['uuid' => $uuid, 'statut' => OperationRecue::ACCEPTE];
    }

    /** @return array{uuid: string, statut: string, motif?: string}|null */
    private static function dejaAcceptee(string $uuid, string $type): ?array
    {
        $trace = OperationRecue::query()->find($uuid);
        if ($trace === null || $trace->statut !== OperationRecue::ACCEPTE) {
            return null;
        }
        if ($trace->type !== $type) {
            return ['uuid' => $uuid, 'statut' => OperationRecue::REJETE,
                'motif' => "Cet UUID a déjà servi pour une opération « {$trace->type} »."];
        }

        return ['uuid' => $uuid, 'statut' => OperationRecue::DEJA_RECU];
    }

    /** @return array{uuid: string, statut: string, motif: string} */
    private static function rejeter(Envoi $envoi, string $uuid, string $type, User $auteur, ?Carbon $creeAt, string $motif): array
    {
        $motif = Str::limit($motif, 490);
        self::tracer($envoi, $uuid, $type, $auteur, $creeAt, OperationRecue::REJETE, $motif);

        return ['uuid' => $uuid, 'statut' => OperationRecue::REJETE, 'motif' => $motif];
    }

    private static function tracer(Envoi $envoi, string $uuid, string $type, User $auteur, ?Carbon $creeAt, string $statut, ?string $motif = null): void
    {
        OperationRecue::query()->updateOrCreate(['uuid' => $uuid], [
            'type' => Str::limit($type, 30, ''),
            'synchronisation_id' => $envoi->id,
            'appareil_id' => $envoi->appareil_id,
            'user_id' => $auteur->id,
            'statut' => $statut,
            'motif' => $motif,
            'cree_at' => $creeAt,
            'recu_at' => now(),
        ]);
    }

    /**
     * Fiche producteur créée sur le terrain : mêmes règles que le formulaire du bureau
     * (consentement, doublons). Un doublon « alerte » doit être confirmé sur le
     * téléphone (doublons_confirmes), sinon l'opération revient rejetée avec l'alerte.
     *
     * @param  array<string, mixed>  $d
     */
    private static function creerProducteur(string $uuid, array $d, User $auteur): Producteur
    {
        if (! $auteur->can('gerer-producteurs')) {
            throw new OperationRefusee('Votre rôle ne permet pas de créer un producteur.');
        }

        $d['telephone'] = Telephone::normaliser(self::texte($d['telephone'] ?? null));
        $d['numero_mobile_money'] = Telephone::normaliser(self::texte($d['numero_mobile_money'] ?? null));
        $d['piece_numero'] = filled($d['piece_numero'] ?? null) ? mb_strtoupper(trim((string) $d['piece_numero'])) : null;

        $v = Validator::make($d, [
            'nom' => ['required', 'string', 'max:255'],
            'prenoms' => ['required', 'string', 'max:255'],
            'sexe' => ['nullable', Rule::enum(Sexe::class)],
            'annee_naissance' => ['nullable', 'integer', 'min:1900', 'max:'.now()->year],
            'telephone' => ['nullable', Telephone::REGLE],
            'numero_mobile_money' => ['nullable', Telephone::REGLE, 'required_with:operateur_mm'],
            'operateur_mm' => ['nullable', Rule::enum(OperateurMobileMoney::class), 'required_with:numero_mobile_money'],
            'piece_type' => ['nullable', Rule::enum(TypePiece::class), 'required_with:piece_numero'],
            'piece_numero' => ['nullable', 'string', 'max:50', 'required_with:piece_type'],
            'village_id' => ['required', 'integer', Rule::exists('villages', 'id')],
            'groupe_id' => ['nullable', 'integer', Rule::exists('groupes_producteurs', 'id')->where('village_id', $d['village_id'] ?? null)],
            'langue_id' => ['nullable', 'integer:strict', Rule::exists('langues', 'id')->where('actif', true)],
            'consentement' => ['accepted'],
        ], [
            'consentement.accepted' => 'Sans l\'accord du producteur, sa fiche ne peut pas être créée.',
        ]);
        $v->validate();

        $doublons = DetectionDoublons::verifier([
            'piece_type' => $d['piece_type'] ?? null,
            'piece_numero' => $d['piece_numero'],
            'telephone' => $d['telephone'],
            'numero_mobile_money' => $d['numero_mobile_money'],
        ]);
        if ($doublons['bloquants'] !== []) {
            throw new OperationRefusee(implode(' ', $doublons['bloquants']));
        }
        if ($doublons['alertes'] !== [] && ($d['doublons_confirmes'] ?? false) !== true) {
            throw new OperationRefusee('À confirmer : '.implode(' ', $doublons['alertes']));
        }

        $producteur = Producteur::query()->create([
            'id' => $uuid,
            'nom' => trim((string) $d['nom']),
            'prenoms' => trim((string) $d['prenoms']),
            'sexe' => $d['sexe'] ?? null,
            'annee_naissance' => $d['annee_naissance'] ?? null,
            'telephone' => $d['telephone'],
            'numero_mobile_money' => $d['numero_mobile_money'],
            'operateur_mm' => $d['operateur_mm'] ?? null,
            'piece_type' => $d['piece_type'] ?? null,
            'piece_numero' => $d['piece_numero'],
            'village_id' => (int) $d['village_id'],
            'groupe_id' => $d['groupe_id'] ?? null,
            'langue_id' => $d['langue_id'] ?? null,
            'consentement_at' => now(),
            'consentement_par' => $auteur->id,
            'cree_par' => $auteur->id,
        ]);

        if ($doublons['alertes'] !== []) {
            Journal::enregistrer(ActionJournal::DoublonConfirme, $producteur, apres: ['alertes' => $doublons['alertes']], userId: $auteur->id);
        }

        return $producteur;
    }

    /**
     * Achat bord-champ pesé sur le terrain : poids en grammes et prix en FCFA, entiers
     * stricts (une chaîne ou un décimal est refusé plutôt qu'arrondi en silence).
     *
     * @param  array<string, mixed>  $d
     */
    private static function creerAchat(string $uuid, array $d, User $auteur): void
    {
        $v = Validator::make($d, [
            'campagne_id' => ['required', 'integer:strict'],
            'lot_id' => ['required', 'integer:strict'],
            'compte_id' => ['required', 'integer:strict'],
            'fournisseur_type' => ['required', Rule::enum(TypeFournisseur::class)],
            'producteur_id' => ['nullable', 'uuid'],
            'pisteur_id' => ['nullable', 'integer:strict'],
            'fournisseur_nom' => ['nullable', 'string', 'max:255'],
            'point_collecte_id' => ['nullable', 'integer:strict'],
            'date_achat' => ['required', 'date'],
            'poids_brut_g' => ['required', 'integer:strict'],
            'tare_g' => ['required', 'integer:strict'],
            'humidite_pour_mille' => ['nullable', 'integer:strict'],
            'kor_centieme_lbs' => ['nullable', 'integer:strict'],
            'grainage_noix_kg' => ['nullable', 'integer:strict'],
            'prix_kg_fcfa' => ['required', 'integer:strict'],
            'pret_id' => ['nullable', 'uuid'],
            'photo_pesee' => ['nullable', 'uuid'],
            'poids_source' => ['nullable', Rule::enum(SourcePoids::class)],
            'grammes_rembourses' => ['nullable', 'integer:strict'],
        ]);
        $v->validate();

        Achats::enregistrer([
            'id' => $uuid,
            'campagne_id' => $d['campagne_id'],
            'lot_id' => $d['lot_id'],
            'compte_id' => $d['compte_id'],
            'fournisseur_type' => TypeFournisseur::from($d['fournisseur_type']),
            'producteur_id' => $d['producteur_id'] ?? null,
            'pisteur_id' => $d['pisteur_id'] ?? null,
            'fournisseur_nom' => $d['fournisseur_nom'] ?? null,
            'point_collecte_id' => $d['point_collecte_id'] ?? null,
            'date_achat' => Carbon::parse($d['date_achat']),
            'poids_brut_g' => $d['poids_brut_g'],
            'poids_source' => $d['poids_source'] ?? null,
            'tare_g' => $d['tare_g'],
            'humidite_pour_mille' => $d['humidite_pour_mille'] ?? null,
            'kor_centieme_lbs' => $d['kor_centieme_lbs'] ?? null,
            'grainage_noix_kg' => $d['grainage_noix_kg'] ?? null,
            'prix_kg_fcfa' => $d['prix_kg_fcfa'],
            'pret_id' => $d['pret_id'] ?? null,
            'grammes_rembourses' => $d['grammes_rembourses'] ?? 0,
            'photo_pesee' => isset($d['photo_pesee']) ? strtolower((string) $d['photo_pesee']) : null,
        ], $auteur);
    }

    /**
     * Parcelle relevée en marchant (contour GPS) : la surface est calculée ICI, par la
     * même méthode que pour un contour importé ; jamais celle du téléphone.
     *
     * @param  array<string, mixed>  $d
     */
    private static function creerParcelle(string $uuid, array $d, User $auteur): void
    {
        if (! $auteur->can('gerer-producteurs')) {
            throw new OperationRefusee('Votre rôle ne permet pas de créer une parcelle.');
        }

        Validator::make($d, [
            'producteur_id' => ['required', 'uuid', Rule::exists('producteurs', 'id')->where('actif', true)],
            'nom' => ['required', 'string', 'max:255', Rule::unique('parcelles', 'nom')->where('producteur_id', $d['producteur_id'] ?? null)],
            'contour' => ['required', 'array'],
            'contour.type' => ['required', Rule::in(['Polygon'])],
            'contour.coordinates' => ['required', 'array'],
            'produit_id' => ['nullable', 'integer:strict', Rule::exists('produits', 'id')],
            'nb_arbres' => ['nullable', 'integer:strict', 'min:0', 'max:1000000'],
        ], [
            'nom.unique' => 'Ce producteur a déjà une parcelle de ce nom.',
            'producteur_id.exists' => 'Producteur introuvable ou désactivé.',
        ])->validate();

        try {
            $contour = Contour::depuisGeometrie(['type' => 'Polygon', 'coordinates' => $d['contour']['coordinates']]);
        } catch (InvalidArgumentException $e) {
            throw new OperationRefusee('Contour GPS refusé : '.$e->getMessage());
        }

        Parcelle::query()->create([
            'id' => $uuid,
            'producteur_id' => $d['producteur_id'],
            'nom' => trim((string) $d['nom']),
            'contour' => $contour->geometrie,
            'contour_origine' => 'gps',
            'produit_id' => $d['produit_id'] ?? null,
            'nb_arbres' => $d['nb_arbres'] ?? null,
            'cree_par' => $auteur->id,
        ]);
    }

    /**
     * Dépense terrain : même service que le bureau (seuil, caisse de l'agent, art. 10.3).
     * Le justificatif est une photo du terrain, envoyée AVANT l'opération par le téléphone.
     *
     * @param  array<string, mixed>  $d
     */
    private static function creerDepense(string $uuid, array $d, User $auteur): void
    {
        if (! $auteur->can('saisir-depenses')) {
            throw new OperationRefusee('Votre rôle ne permet pas de saisir une dépense.');
        }

        Validator::make($d, [
            'categorie_id' => ['required', 'integer:strict'],
            'compte_id' => ['required', 'integer:strict'],
            'montant_fcfa' => ['required', 'integer:strict', 'min:1'],
            'date_depense' => ['required', 'date', 'before_or_equal:today'],
            'beneficiaire' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'campagne_id' => ['nullable', 'integer:strict'],
            'justificatif_photo' => ['required', 'uuid'],
        ], [
            'justificatif_photo.required' => 'Le justificatif (photo du reçu) est obligatoire.',
            'date_depense.before_or_equal' => 'La date d\'une dépense ne peut pas être dans le futur.',
        ])->validate();

        $photo = PhotoTerrain::query()->find(strtolower((string) $d['justificatif_photo']));
        if ($photo === null) {
            throw new OperationRefusee('La photo du justificatif n\'est pas encore arrivée : renvoyer quand elle sera partie.');
        }
        if ($photo->user_id !== $auteur->id) {
            throw new OperationRefusee('Ce justificatif a été envoyé par un autre utilisateur.');
        }

        Depenses::saisir([
            'id' => $uuid,
            'categorie_id' => $d['categorie_id'],
            'compte_id' => $d['compte_id'],
            'montant_fcfa' => $d['montant_fcfa'],
            'date_depense' => Carbon::parse($d['date_depense']),
            'beneficiaire' => (string) $d['beneficiaire'],
            'description' => $d['description'] ?? null,
            'campagne_id' => $d['campagne_id'] ?? null,
        ], $photo->chemin, $auteur);
    }

    /**
     * Visite de parcelle : un constat, ni argent ni poids. Les photos sont envoyées AVANT
     * la fiche (comme le justificatif d'une dépense) et y sont rattachées par leur UUID.
     * La parcelle peut avoir été relevée dans le même envoi : elle est traitée avant.
     *
     * @param  array<string, mixed>  $d
     */
    private static function creerVisite(string $uuid, array $d, User $auteur, ?Carbon $creeAt): void
    {
        if (! $auteur->can('saisir-visites')) {
            throw new OperationRefusee('Votre rôle ne permet pas de saisir une visite.');
        }

        Validator::make($d, [
            'parcelle_id' => ['required', 'uuid', Rule::exists('parcelles', 'id')->where('actif', true)],
            'date_visite' => ['required', 'date', 'before_or_equal:today'],
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'pratiques' => ['nullable', 'array', 'max:20'],
            'pratiques.*' => ['string', 'distinct', Rule::enum(PratiqueCulturale::class)],
            'observations' => ['nullable', 'string', 'max:2000'],
            'photos' => ['nullable', 'array', 'max:10'],
            'photos.*' => ['uuid', 'distinct'],
        ], [
            'parcelle_id.exists' => 'Parcelle introuvable ou désactivée (relevée sur un autre téléphone et pas encore envoyée ?).',
            'date_visite.before_or_equal' => 'La date d\'une visite ne peut pas être dans le futur.',
            'pratiques.*.enum' => 'Pratique inconnue : mettre l\'appli à jour.',
        ])->validate();

        /** @var list<string> $pratiques */
        $pratiques = array_values($d['pratiques'] ?? []);
        $observations = filled($d['observations'] ?? null) ? trim((string) $d['observations']) : null;
        $idsPhotos = array_map(fn ($id) => strtolower((string) $id), array_values($d['photos'] ?? []));

        if ($pratiques === [] && $observations === null && $idsPhotos === []) {
            throw new OperationRefusee('Fiche de visite vide : cocher une pratique, écrire une observation ou prendre une photo.');
        }

        $photos = PhotoTerrain::query()->whereKey($idsPhotos)->get();
        if ($photos->count() !== count($idsPhotos)) {
            throw new OperationRefusee('Une photo de la visite n\'est pas encore arrivée : renvoyer quand elle sera partie.');
        }
        if ($photos->contains(fn (PhotoTerrain $p) => $p->user_id !== $auteur->id)) {
            throw new OperationRefusee('Une photo de la visite a été envoyée par un autre utilisateur.');
        }

        $visite = Visite::query()->create([
            'id' => $uuid,
            'parcelle_id' => strtolower((string) $d['parcelle_id']),
            'date_visite' => Carbon::parse((string) $d['date_visite'])->toDateString(),
            'lat' => $d['lat'] ?? null,
            'lng' => $d['lng'] ?? null,
            'pratiques' => $pratiques === [] ? null : $pratiques,
            'observations' => $observations,
            'cree_par' => $auteur->id,
            'cree_at' => $creeAt,
        ]);
        $visite->photos()->attach($idsPhotos);
    }

    private static function texte(mixed $valeur): ?string
    {
        return is_scalar($valeur) ? (string) $valeur : null;
    }

    private static function date(mixed $valeur): ?Carbon
    {
        try {
            return is_string($valeur) && $valeur !== '' ? Carbon::parse($valeur) : null;
        } catch (Throwable) {
            return null;
        }
    }
}
