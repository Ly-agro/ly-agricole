<?php

namespace App\Models;

use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Fiche du référentiel des traitements, tenue et datée par l'agronome. Le modèle de
 * langage ne cite QUE ces fiches (par leur repère) ; il ne voit jamais le nom commercial
 * ni la dose. Écrire via App\Services\Ia\Referentiel.
 *
 * @property int $id
 * @property int $produit_id
 * @property string $type pratique | biologique | chimique
 * @property string $cible
 * @property string $titre
 * @property string|null $description
 * @property string|null $nom_commercial
 * @property string|null $matiere_active
 * @property string|null $dose
 * @property int|null $passages
 * @property int|null $delai_avant_recolte_jours
 * @property string|null $toxicite_humaine
 * @property string|null $protection
 * @property string|null $effet_abeilles
 * @property string|null $reference_homologation
 * @property string $statut autorisee | retiree | interdite
 * @property Carbon $validee_le
 * @property int $validee_par
 * @property-read Produit $produit
 * @property-read User $validateur
 */
#[Fillable([
    'produit_id', 'type', 'cible', 'titre', 'description', 'nom_commercial', 'matiere_active', 'dose', 'passages',
    'delai_avant_recolte_jours', 'toxicite_humaine', 'protection', 'effet_abeilles', 'reference_homologation',
    'statut', 'validee_le', 'validee_par',
])]
class FicheTraitement extends Model
{
    use Journalise;

    public const TYPES = ['pratique' => 'Pratique sans produit', 'biologique' => 'Biologique ou peu toxique', 'chimique' => 'Produit chimique homologué'];

    public const STATUTS = ['autorisee' => 'Autorisée', 'retiree' => 'Retirée', 'interdite' => 'Interdite'];

    /** Champs sans lesquels une fiche chimique n'est pas proposable (règle 5). */
    public const CHAMPS_CHIMIQUE = [
        'nom_commercial' => 'nom commercial', 'matiere_active' => 'matière active', 'dose' => 'dose',
        'passages' => 'nombre de passages', 'delai_avant_recolte_jours' => 'délai avant récolte',
        'toxicite_humaine' => 'toxicité humaine', 'protection' => 'protection obligatoire', 'effet_abeilles' => 'effet sur les abeilles',
    ];

    protected $table = 'fiches_traitement';

    /** @return list<string> champs manquants (vide : complète) */
    public function manquants(): array
    {
        if ($this->type !== 'chimique') {
            return [];
        }

        return array_values(array_map(
            fn (string $cle) => self::CHAMPS_CHIMIQUE[$cle],
            array_filter(array_keys(self::CHAMPS_CHIMIQUE), fn (string $cle) => $this->getAttribute($cle) === null || $this->getAttribute($cle) === ''),
        ));
    }

    /** Proposable au modèle : autorisée et complète. */
    public function proposable(): bool
    {
        return $this->statut === 'autorisee' && $this->manquants() === [];
    }

    /** @return BelongsTo<Produit, $this> */
    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }

    /** @return BelongsTo<User, $this> */
    public function validateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validee_par');
    }

    protected function casts(): array
    {
        return [
            'validee_le' => 'date',
            'passages' => 'integer',
            'delai_avant_recolte_jours' => 'integer',
        ];
    }
}
