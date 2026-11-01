<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\StatutCampagne;
use App\Enums\StatutDeclarationApport;
use App\Exceptions\OperationRefusee;
use App\Models\Campagne;
use App\Models\CompteTresorerie;
use App\Models\DeclarationApport;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DeclarationsApport
{
    public static function declarer(User $investisseur, int $campagneId, int $montant, Carbon $date, ?string $reference): DeclarationApport
    {
        if (! $investisseur->aLeRole(Role::Investisseur)) {
            throw new OperationRefusee('Seul un investisseur peut déclarer son apport.');
        }
        if (! $investisseur->actif) {
            throw new OperationRefusee('Votre compte est désactivé.');
        }
        if ($montant <= 0) {
            throw new OperationRefusee('Le montant déclaré doit être supérieur à zéro.');
        }
        if ($date->isAfter(Carbon::today())) {
            throw new OperationRefusee('La date du virement ne peut pas être dans le futur.');
        }

        $campagne = Campagne::query()->findOrFail($campagneId);
        if ($campagne->statut !== StatutCampagne::Ouverte) {
            throw new OperationRefusee('Seule une campagne ouverte peut recevoir une déclaration d’apport.');
        }
        $campagne->exigerEnCours('apport', $date);

        return DeclarationApport::query()->create([
            'investisseur_id' => $investisseur->id,
            'campagne_id' => $campagne->id,
            'montant_fcfa' => $montant,
            'date_virement' => $date->toDateString(),
            'reference_virement' => filled($reference) ? trim((string) $reference) : null,
            'statut' => StatutDeclarationApport::EnAttente,
        ]);
    }

    public static function confirmer(DeclarationApport $declaration, int $compteId, User $validateur): DeclarationApport
    {
        if (! $validateur->can('gerer-apports')) {
            throw new OperationRefusee('Votre rôle ne permet pas de confirmer un apport.');
        }

        return DB::transaction(function () use ($declaration, $compteId, $validateur) {
            $declaration = DeclarationApport::query()->lockForUpdate()->findOrFail($declaration->id);
            if ($declaration->statut !== StatutDeclarationApport::EnAttente) {
                throw new OperationRefusee('Cette déclaration a déjà été traitée.');
            }
            $compte = CompteTresorerie::query()->findOrFail($compteId);

            $apport = Apports::enregistrer(
                $declaration->investisseur_id,
                $declaration->campagne_id,
                $compte->id,
                $declaration->montant_fcfa,
                Carbon::parse($declaration->date_virement),
                $validateur,
                trim('Déclaration #'.$declaration->id.($declaration->reference_virement ? ' — '.$declaration->reference_virement : '')),
                declarationPrealable: true,
            );

            $declaration->update([
                'statut' => StatutDeclarationApport::Confirmee,
                'compte_id' => $compte->id,
                'apport_id' => $apport->id,
                'traite_par' => $validateur->id,
                'traite_at' => now(),
            ]);

            return $declaration->refresh();
        });
    }

    public static function refuser(DeclarationApport $declaration, string $motif, User $validateur): DeclarationApport
    {
        if (! $validateur->can('gerer-apports')) {
            throw new OperationRefusee('Votre rôle ne permet pas de traiter une déclaration d’apport.');
        }
        $motif = trim($motif);
        if (mb_strlen($motif) < 5) {
            throw new OperationRefusee('Le motif du refus est obligatoire (5 caractères au moins).');
        }

        return DB::transaction(function () use ($declaration, $motif, $validateur) {
            $declaration = DeclarationApport::query()->lockForUpdate()->findOrFail($declaration->id);
            if ($declaration->statut !== StatutDeclarationApport::EnAttente) {
                throw new OperationRefusee('Cette déclaration a déjà été traitée.');
            }

            $declaration->update([
                'statut' => StatutDeclarationApport::Refusee,
                'motif_refus' => $motif,
                'traite_par' => $validateur->id,
                'traite_at' => now(),
            ]);

            return $declaration->refresh();
        });
    }
}
