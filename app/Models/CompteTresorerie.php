<?php

namespace App\Models;

use App\Enums\SensMouvement;
use App\Enums\TypeCompte;
use App\Models\Concerns\Journalise;
use Database\Factories\CompteTresorerieFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Caisse, banque ou Mobile Money. Pas de colonne solde : le solde est la somme des
 * mouvements (D5, invariant 3). Écrire les mouvements via App\Services\Tresorerie.
 *
 * @property int $id
 * @property string $nom
 * @property TypeCompte $type
 * @property int|null $titulaire_id
 * @property int|null $campagne_id
 * @property bool $actif
 * @property-read User|null $titulaire
 * @property-read Campagne|null $campagne
 */
#[Fillable(['nom', 'type', 'titulaire_id', 'campagne_id', 'actif'])]
#[UseFactory(CompteTresorerieFactory::class)]
class CompteTresorerie extends Model
{
    /** @use HasFactory<CompteTresorerieFactory> */
    use HasFactory, Journalise;

    protected $table = 'comptes_tresorerie';

    /** Σ entrées − Σ sorties, calculé en SQL sur des entiers. */
    public function solde(): int
    {
        return (int) $this->mouvements()
            ->selectRaw('COALESCE(SUM(CASE WHEN sens = ? THEN montant_fcfa ELSE -montant_fcfa END), 0) AS solde', [SensMouvement::Entree->value])
            ->value('solde');
    }

    /** @return HasMany<MouvementTresorerie, $this> */
    public function mouvements(): HasMany
    {
        return $this->hasMany(MouvementTresorerie::class, 'compte_id');
    }

    /** @return BelongsTo<User, $this> */
    public function titulaire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'titulaire_id');
    }

    /** @return BelongsTo<Campagne, $this> */
    public function campagne(): BelongsTo
    {
        return $this->belongsTo(Campagne::class);
    }

    protected function casts(): array
    {
        return [
            'type' => TypeCompte::class,
            'actif' => 'boolean',
        ];
    }
}
