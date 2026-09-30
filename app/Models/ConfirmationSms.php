<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Preuve envoyée au producteur (cahier §10) : une par opération (achat, versement,
 * remboursement), jamais deux (clé unique objet_type + objet_id).
 *
 * @property int $id
 * @property string $producteur_id
 * @property string $objet_type
 * @property string $objet_id
 * @property string $telephone
 * @property string $message
 * @property string $statut en_attente | envoye | echec
 * @property string $pilote
 * @property Carbon|null $envoye_at
 * @property string|null $erreur
 * @property-read Producteur $producteur
 */
class ConfirmationSms extends Model
{
    public const EN_ATTENTE = 'en_attente';

    public const ENVOYE = 'envoye';

    public const ECHEC = 'echec';

    protected $table = 'confirmations_sms';

    protected $guarded = ['id'];

    /** @return BelongsTo<Producteur, $this> */
    public function producteur(): BelongsTo
    {
        return $this->belongsTo(Producteur::class);
    }

    protected function casts(): array
    {
        return ['envoye_at' => 'datetime'];
    }
}
