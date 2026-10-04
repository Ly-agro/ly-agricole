<?php

namespace App\Models;

use App\Enums\OperateurMobileMoney;
use App\Enums\Sexe;
use App\Enums\TypePiece;
use App\Models\Concerns\Journalise;
use Database\Factories\ProducteurFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Producteur 📱 : identifiant UUID v7 (D3), code de carte attribué par le serveur.
 * Données personnelles (loi 2013-450) : pas de fiche sans consentement tracé.
 *
 * @property string $id
 * @property string $code
 * @property string $nom
 * @property string $prenoms
 * @property Sexe|null $sexe
 * @property int|null $annee_naissance
 * @property string|null $telephone
 * @property string|null $numero_mobile_money
 * @property OperateurMobileMoney|null $operateur_mm
 * @property TypePiece|null $piece_type
 * @property string|null $piece_numero
 * @property string|null $photo
 * @property int $village_id
 * @property int|null $groupe_id
 * @property Carbon $consentement_at
 * @property int $consentement_par
 * @property int $cree_par
 * @property bool $actif
 * @property-read Village $village
 * @property-read GroupeProducteur|null $groupe
 * @property int|null $langue_id
 * @property-read Langue|null $langue
 */
#[Fillable([
    'id', 'nom', 'prenoms', 'sexe', 'annee_naissance', 'telephone', 'numero_mobile_money', 'operateur_mm',
    'piece_type', 'piece_numero', 'photo', 'village_id', 'groupe_id', 'langue_id', 'consentement_at', 'consentement_par',
    'cree_par', 'actif',
])]
class Producteur extends Model
{
    /** @use HasFactory<ProducteurFactory> */
    use HasFactory, HasUuids, Journalise;

    public const PREFIXE_CODE = 'LYP-';

    protected static function booted(): void
    {
        static::creating(function (Producteur $producteur) {
            $producteur->code ??= self::PREFIXE_CODE.str_pad((string) Compteur::suivant('producteur'), 6, '0', STR_PAD_LEFT);
        });
    }

    public function nomComplet(): string
    {
        return $this->nom.' '.$this->prenoms;
    }

    /** @return BelongsTo<Village, $this> */
    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }

    /**
     * Langue de préférence pour les messages ; vide = français (question 12).
     *
     * @return BelongsTo<Langue, $this>
     */
    public function langue(): BelongsTo
    {
        return $this->belongsTo(Langue::class);
    }

    /** @return BelongsTo<GroupeProducteur, $this> */
    public function groupe(): BelongsTo
    {
        return $this->belongsTo(GroupeProducteur::class, 'groupe_id');
    }

    /** @return HasMany<Parcelle, $this> */
    public function parcelles(): HasMany
    {
        return $this->hasMany(Parcelle::class);
    }

    /** @return BelongsTo<User, $this> */
    public function auteurConsentement(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consentement_par');
    }

    protected function casts(): array
    {
        return [
            'sexe' => Sexe::class,
            'operateur_mm' => OperateurMobileMoney::class,
            'piece_type' => TypePiece::class,
            'annee_naissance' => 'integer',
            'consentement_at' => 'datetime',
            'actif' => 'boolean',
        ];
    }
}
