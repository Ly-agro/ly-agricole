<?php

namespace App\Models;

use App\Enums\SourcePoids;
use App\Enums\StatutAchat;
use App\Enums\TypeFournisseur;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Achat bord-champ 📱 (UUID v7). Écrire via App\Services\Achats : pesée, qualité,
 * prix plancher, seuil de validation, stock, paiement et remboursement en kilos.
 *
 * @property string $id
 * @property string $reference
 * @property int $campagne_id
 * @property int $lot_id
 * @property TypeFournisseur $fournisseur_type
 * @property string|null $producteur_id
 * @property int|null $pisteur_id
 * @property string|null $fournisseur_nom
 * @property int|null $point_collecte_id
 * @property Carbon $date_achat
 * @property int $poids_brut_g
 * @property int $tare_g
 * @property int $poids_net_g
 * @property SourcePoids|null $poids_source balance Bluetooth ou saisie à la main ; null = avant cette fonction
 * @property int|null $humidite_pour_mille
 * @property int|null $kor_centieme_lbs
 * @property int|null $grainage_noix_kg
 * @property int $prix_kg_fcfa
 * @property int $montant_fcfa
 * @property string|null $pret_id
 * @property int $grammes_rembourses
 * @property int $montant_especes_fcfa
 * @property int|null $commission_pisteur_fcfa commission DUE au pisteur (calcul activé par la direction) ; null = non calculée
 * @property int $compte_id
 * @property int|null $mouvement_id
 * @property StatutAchat $statut
 * @property int $cree_par
 * @property int|null $valide_par
 * @property Carbon|null $valide_at
 * @property string|null $motif_refus
 * @property-read Campagne $campagne
 * @property-read Lot $lot
 * @property-read Producteur|null $producteur
 * @property-read Pisteur|null $pisteur
 * @property-read Pret|null $pret
 * @property string|null $photo_pesee UUID dans photos_terrain (la photo peut arriver après)
 * @property-read CompteTresorerie $compte
 * @property-read PhotoTerrain|null $photoPesee
 * @property-read User $auteur
 * @property-read User|null $validateur
 */
#[Fillable([
    'id', 'campagne_id', 'lot_id', 'fournisseur_type', 'producteur_id', 'pisteur_id', 'fournisseur_nom',
    'point_collecte_id', 'date_achat', 'lat', 'lng', 'poids_brut_g', 'tare_g', 'poids_net_g', 'poids_source', 'humidite_pour_mille',
    'kor_centieme_lbs', 'grainage_noix_kg', 'prix_kg_fcfa', 'montant_fcfa', 'pret_id', 'grammes_rembourses',
    'montant_especes_fcfa', 'commission_pisteur_fcfa', 'compte_id', 'mouvement_id', 'photo_pesee', 'statut', 'cree_par', 'valide_par',
    'valide_at', 'motif_refus',
])]
class Achat extends Model
{
    use HasUuids, Journalise;

    public const PREFIXE_REFERENCE = 'ACH-';

    protected static function booted(): void
    {
        static::creating(function (Achat $achat) {
            $achat->reference ??= self::PREFIXE_REFERENCE.str_pad((string) Compteur::suivant('achat'), 6, '0', STR_PAD_LEFT);
        });
    }

    public function nomFournisseur(): string
    {
        return match ($this->fournisseur_type) {
            TypeFournisseur::Producteur => $this->producteur?->nomComplet() ?? '—',
            TypeFournisseur::Pisteur => $this->pisteur->nom ?? '—',
            TypeFournisseur::Cooperative => (string) $this->fournisseur_nom,
        };
    }

    /** @return BelongsTo<Campagne, $this> */
    public function campagne(): BelongsTo
    {
        return $this->belongsTo(Campagne::class);
    }

    /** @return BelongsTo<Lot, $this> */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    /** @return BelongsTo<Producteur, $this> */
    public function producteur(): BelongsTo
    {
        return $this->belongsTo(Producteur::class);
    }

    /** @return BelongsTo<Pisteur, $this> */
    public function pisteur(): BelongsTo
    {
        return $this->belongsTo(Pisteur::class);
    }

    /** @return BelongsTo<Pret, $this> */
    public function pret(): BelongsTo
    {
        return $this->belongsTo(Pret::class);
    }

    /** @return BelongsTo<PhotoTerrain, $this> */
    public function photoPesee(): BelongsTo
    {
        return $this->belongsTo(PhotoTerrain::class, 'photo_pesee');
    }

    /** @return BelongsTo<CompteTresorerie, $this> */
    public function compte(): BelongsTo
    {
        return $this->belongsTo(CompteTresorerie::class, 'compte_id');
    }

    /** @return BelongsTo<User, $this> */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    /** @return BelongsTo<User, $this> */
    public function validateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par');
    }

    protected function casts(): array
    {
        return [
            'fournisseur_type' => TypeFournisseur::class,
            'statut' => StatutAchat::class,
            'date_achat' => 'datetime',
            'valide_at' => 'datetime',
            'poids_brut_g' => 'integer',
            'tare_g' => 'integer',
            'poids_net_g' => 'integer',
            'poids_source' => SourcePoids::class,
            'humidite_pour_mille' => 'integer',
            'kor_centieme_lbs' => 'integer',
            'grainage_noix_kg' => 'integer',
            'prix_kg_fcfa' => 'integer',
            'montant_fcfa' => 'integer',
            'grammes_rembourses' => 'integer',
            'montant_especes_fcfa' => 'integer',
            'commission_pisteur_fcfa' => 'integer',
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
        ];
    }
}
