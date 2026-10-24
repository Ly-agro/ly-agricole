<?php

namespace App\Models;

use App\Enums\StatutDiagnostic;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Avis sur une photo de visite. Écrire via App\Services\Ia\Diagnostics : la validation
 * (confirmé / corrigé) n'appartient qu'à un agronome ; le brouillon de conseil reste interne.
 *
 * @property int $id
 * @property string $visite_id
 * @property string $photo_id
 * @property StatutDiagnostic $statut
 * @property string|null $classe_proposee
 * @property int|null $confiance_pour_mille
 * @property string|null $modele_vision
 * @property string|null $motif
 * @property string|null $classe_retenue
 * @property string|null $note_agronome
 * @property string|null $annotation_classe annotation PROVISOIRE (pas une validation)
 * @property string|null $annotation_source ex. « claude »
 * @property string|null $annotation_note
 * @property Carbon|null $annotation_at
 * @property int|null $valide_par
 * @property Carbon|null $valide_at
 * @property string|null $conseil_statut en_attente | brouillon | rejete | erreur
 * @property string|null $conseil_texte
 * @property list<int>|null $conseil_fiches
 * @property list<string>|null $conseil_motifs
 * @property string|null $conseil_modele
 * @property int $demande_par
 * @property Carbon $created_at
 * @property-read Visite $visite
 * @property-read PhotoTerrain $photo
 * @property-read User|null $validateur
 * @property-read User $demandeur
 */
#[Fillable([
    'visite_id', 'photo_id', 'statut', 'classe_proposee', 'confiance_pour_mille', 'modele_vision', 'motif',
    'classe_retenue', 'note_agronome', 'annotation_classe', 'annotation_source', 'annotation_note', 'annotation_at',
    'valide_par', 'valide_at', 'conseil_statut', 'conseil_texte',
    'conseil_fiches', 'conseil_motifs', 'conseil_modele', 'demande_par',
])]
class Diagnostic extends Model
{
    use Journalise;

    /** @return BelongsTo<Visite, $this> */
    public function visite(): BelongsTo
    {
        return $this->belongsTo(Visite::class);
    }

    /** @return BelongsTo<PhotoTerrain, $this> */
    public function photo(): BelongsTo
    {
        return $this->belongsTo(PhotoTerrain::class, 'photo_id');
    }

    /** @return BelongsTo<User, $this> */
    public function validateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par');
    }

    /** @return BelongsTo<User, $this> */
    public function demandeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'demande_par');
    }

    protected function casts(): array
    {
        return [
            'statut' => StatutDiagnostic::class,
            'confiance_pour_mille' => 'integer',
            'valide_at' => 'datetime',
            'annotation_at' => 'datetime',
            'conseil_fiches' => 'array',
            'conseil_motifs' => 'array',
        ];
    }
}
