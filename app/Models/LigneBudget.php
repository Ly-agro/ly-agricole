<?php

namespace App\Models;

use App\Enums\PosteBudget;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Montant prévu pour un poste d'une campagne. Écrire via App\Services\Budgets (droits,
 * campagne clôturée, catégorie exclue par l'art. 10.3, un poste une seule fois).
 *
 * @property int $id
 * @property int $campagne_id
 * @property PosteBudget $poste
 * @property int|null $categorie_id
 * @property int $montant_fcfa
 * @property string|null $note
 * @property int $cree_par
 * @property int|null $modifie_par
 * @property-read Campagne $campagne
 * @property-read CategorieDepense|null $categorie
 */
#[Fillable(['campagne_id', 'poste', 'categorie_id', 'montant_fcfa', 'note', 'cree_par', 'modifie_par'])]
class LigneBudget extends Model
{
    use Journalise;

    protected $table = 'lignes_budget';

    public function libelle(): string
    {
        return $this->poste === PosteBudget::Categorie
            ? ($this->categorie === null ? 'Catégorie supprimée' : $this->categorie->nom)
            : $this->poste->libelle();
    }

    /** @return BelongsTo<Campagne, $this> */
    public function campagne(): BelongsTo
    {
        return $this->belongsTo(Campagne::class);
    }

    /** @return BelongsTo<CategorieDepense, $this> */
    public function categorie(): BelongsTo
    {
        return $this->belongsTo(CategorieDepense::class, 'categorie_id');
    }

    protected function casts(): array
    {
        return [
            'poste' => PosteBudget::class,
            'montant_fcfa' => 'integer',
        ];
    }
}
