<?php

namespace App\Models;

use App\Enums\StatutDepense;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Dépense 📱 (UUID v7). Écrire via App\Services\Depenses : c'est là que sont le seuil,
 * la séparation des tâches (valide_par ≠ cree_par) et le paiement.
 *
 * @property string $id
 * @property int $categorie_id
 * @property int $compte_id
 * @property int $montant_fcfa
 * @property Carbon $date_depense
 * @property string $beneficiaire
 * @property string|null $description
 * @property string $justificatif
 * @property int|null $campagne_id
 * @property string|null $parcelle_id
 * @property StatutDepense $statut
 * @property int $cree_par
 * @property int|null $valide_par
 * @property Carbon|null $valide_at
 * @property string|null $motif_refus
 * @property int|null $mouvement_id
 * @property-read CategorieDepense $categorie
 * @property-read CompteTresorerie $compte
 * @property-read User $auteur
 * @property-read User|null $validateur
 * @property-read Campagne|null $campagne
 */
#[Fillable([
    'id', 'categorie_id', 'compte_id', 'montant_fcfa', 'date_depense', 'beneficiaire', 'description',
    'justificatif', 'campagne_id', 'parcelle_id', 'statut', 'cree_par', 'valide_par', 'valide_at',
    'motif_refus', 'mouvement_id',
])]
class Depense extends Model
{
    use HasUuids, Journalise;

    /** @return BelongsTo<CategorieDepense, $this> */
    public function categorie(): BelongsTo
    {
        return $this->belongsTo(CategorieDepense::class, 'categorie_id');
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

    /** @return BelongsTo<Campagne, $this> */
    public function campagne(): BelongsTo
    {
        return $this->belongsTo(Campagne::class);
    }

    protected function casts(): array
    {
        return [
            'statut' => StatutDepense::class,
            'montant_fcfa' => 'integer',
            'date_depense' => 'date',
            'valide_at' => 'datetime',
        ];
    }
}
