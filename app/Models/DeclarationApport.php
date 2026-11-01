<?php

namespace App\Models;

use App\Enums\StatutDeclarationApport;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'investisseur_id', 'campagne_id', 'montant_fcfa', 'date_virement', 'reference_virement',
    'statut', 'compte_id', 'apport_id', 'traite_par', 'traite_at', 'motif_refus',
])]
class DeclarationApport extends Model
{
    use Journalise;

    protected $table = 'declarations_apports';

    /** @return BelongsTo<User, $this> */
    public function investisseur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'investisseur_id');
    }

    /** @return BelongsTo<Campagne, $this> */
    public function campagne(): BelongsTo
    {
        return $this->belongsTo(Campagne::class);
    }

    /** @return BelongsTo<CompteTresorerie, $this> */
    public function compte(): BelongsTo
    {
        return $this->belongsTo(CompteTresorerie::class, 'compte_id');
    }

    /** @return BelongsTo<Apport, $this> */
    public function apport(): BelongsTo
    {
        return $this->belongsTo(Apport::class);
    }

    /** @return BelongsTo<User, $this> */
    public function traiteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'traite_par');
    }

    protected function casts(): array
    {
        return [
            'montant_fcfa' => 'integer',
            'date_virement' => 'date',
            'statut' => StatutDeclarationApport::class,
            'traite_at' => 'datetime',
        ];
    }
}
