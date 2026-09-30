<?php

namespace App\Services;

use App\Jobs\EnvoyerConfirmationSms;
use App\Models\Achat;
use App\Models\ConfirmationSms;
use App\Models\Decaissement;
use App\Models\Producteur;
use App\Models\Remboursement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Confirmation SMS au producteur de chaque achat, versement et remboursement
 * (cahier §10 : protection contre un poids gonflé ou de l'argent retenu par un agent).
 *
 * La ligne est écrite DANS la transaction de l'opération (annulée avec elle) ; l'envoi
 * est mis en file APRÈS le commit. Messages courts, sans accents (compatibles avec tous
 * les téléphones). Pas de téléphone connu : rien n'est envoyé.
 */
class ConfirmationsSms
{
    public static function pourAchat(Achat $achat): ?ConfirmationSms
    {
        $achat->loadMissing('producteur', 'pret');
        if ($achat->producteur === null) {
            return null;
        }

        $message = "LY AGRICOLE: achat {$achat->reference} du {$achat->date_achat->format('d/m')}: "
            .self::kg($achat->poids_net_g).' a '.self::f($achat->prix_kg_fcfa).'/kg = '.self::f($achat->montant_fcfa).'.';
        if ($achat->pret !== null && $achat->grammes_rembourses > 0) {
            $message .= ' Retenu pret '.$achat->pret->reference.': '.self::kg($achat->grammes_rembourses).'.';
        }
        $message .= ' Paye: '.self::f($achat->montant_especes_fcfa).'.';
        if ($achat->pret !== null) {
            $message .= ' Reste du: '.self::f($achat->pret->restantDu()).'.';
        }

        return self::enregistrer($achat->producteur, $achat, $message);
    }

    public static function pourDecaissement(Decaissement $decaissement): ?ConfirmationSms
    {
        $decaissement->loadMissing('pret.producteur');
        $pret = $decaissement->pret;

        return self::enregistrer($pret->producteur, $decaissement,
            "LY AGRICOLE: pret {$pret->reference}: vous avez recu ".self::f($decaissement->montant_fcfa)
            .' le '.$decaissement->date_decaissement->format('d/m').'. Total du: '.self::f($pret->restantDu()).'.');
    }

    public static function pourRemboursement(Remboursement $remboursement): ?ConfirmationSms
    {
        $remboursement->loadMissing('pret.producteur');
        $pret = $remboursement->pret;

        return self::enregistrer($pret->producteur, $remboursement,
            "LY AGRICOLE: pret {$pret->reference}: remboursement de ".self::f($remboursement->montant_fcfa)
            .' recu le '.$remboursement->date_remboursement->format('d/m').'. Reste du: '.self::f($pret->restantDu()).'.');
    }

    private static function enregistrer(Producteur $producteur, Model $objet, string $message): ?ConfirmationSms
    {
        if ($producteur->telephone === null) {
            return null;
        }

        // Une seule confirmation par opération, même si l'opération est rejouée.
        $confirmation = ConfirmationSms::query()->firstOrCreate(
            ['objet_type' => $objet->getMorphClass(), 'objet_id' => (string) $objet->getKey()],
            [
                'producteur_id' => $producteur->id,
                'telephone' => $producteur->telephone,
                'message' => Str::limit($message, 480, ''),
                'statut' => ConfirmationSms::EN_ATTENTE,
                'pilote' => (string) config('services.sms.pilote'),
            ],
        );

        if ($confirmation->wasRecentlyCreated) {
            EnvoyerConfirmationSms::dispatch($confirmation->id);
        }

        return $confirmation;
    }

    /** Montant pour un SMS : « 212 500 F ». */
    private static function f(int $montant): string
    {
        return number_format($montant, 0, ',', ' ').' F';
    }

    /** Poids pour un SMS, en entiers : « 500 kg », « 235,295 kg ». */
    private static function kg(int $grammes): string
    {
        $reste = $grammes % 1000;

        return number_format(intdiv($grammes, 1000), 0, ',', ' ').($reste === 0 ? '' : ','.str_pad((string) $reste, 3, '0', STR_PAD_LEFT)).' kg';
    }
}
