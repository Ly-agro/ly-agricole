<?php

namespace Tests\Feature\Tresorerie;

use App\Enums\NatureMouvement;
use App\Enums\Role;
use App\Enums\SensMouvement;
use App\Exceptions\OperationRefusee;
use App\Exceptions\RegistreImmuableException;
use App\Models\CompteTresorerie;
use App\Models\MouvementTresorerie;
use App\Models\User;
use App\Services\Tresorerie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TresorerieTest extends TestCase
{
    use RefreshDatabase;

    private User $comptable;

    private CompteTresorerie $caisse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comptable = User::factory()->role(Role::Comptable)->create();
        $this->caisse = CompteTresorerie::factory()->create(['nom' => 'Caisse centrale']);
    }

    private function apport(CompteTresorerie $compte, int $montant): MouvementTresorerie
    {
        return Tresorerie::entree($compte, $montant, NatureMouvement::Apport, Carbon::today(), 'Apport', $this->comptable);
    }

    #[Test]
    public function le_solde_est_la_somme_des_mouvements(): void
    {
        // Invariant 3 : solde = Σ entrées − Σ sorties, recalculé ici indépendamment.
        $banque = CompteTresorerie::factory()->create();
        $this->apport($this->caisse, 21_000_000);
        $this->apport($this->caisse, 500_000);
        Tresorerie::virement($this->caisse, $banque, 7_250_000, Carbon::today(), 'Dépôt en banque', $this->comptable);

        $attendu = MouvementTresorerie::query()->where('compte_id', $this->caisse->id)->get()
            ->sum(fn (MouvementTresorerie $m) => $m->montantSigne());

        $this->assertSame(14_250_000, $attendu);
        $this->assertSame($attendu, $this->caisse->solde());
        $this->assertSame(7_250_000, $banque->solde());
    }

    #[Test]
    public function aucun_compte_ne_passe_en_negatif(): void
    {
        $banque = CompteTresorerie::factory()->create();
        $this->apport($this->caisse, 100_000);

        try {
            Tresorerie::virement($this->caisse, $banque, 100_001, Carbon::today(), 'Trop', $this->comptable);
            $this->fail('Un virement au-delà du solde a été accepté.');
        } catch (OperationRefusee $e) {
            $this->assertStringContainsString('Solde insuffisant sur « Caisse centrale »', $e->getMessage());
        }

        // Tout ou rien : aucune jambe du virement n'a été écrite.
        $this->assertSame(1, MouvementTresorerie::count());
        $this->assertSame(100_000, $this->caisse->solde());
        $this->assertSame(0, $banque->solde());

        Tresorerie::virement($this->caisse, $banque, 100_000, Carbon::today(), 'Tout', $this->comptable);
        $this->assertSame(0, $this->caisse->solde());
    }

    #[Test]
    public function montants_nuls_negatifs_dates_futures_et_comptes_desactives_sont_refuses(): void
    {
        $inactif = CompteTresorerie::factory()->create(['actif' => false]);

        foreach ([
            fn () => $this->apport($this->caisse, 0),
            fn () => $this->apport($this->caisse, -5),
            fn () => Tresorerie::entree($this->caisse, 1000, NatureMouvement::Apport, Carbon::tomorrow(), 'Demain', $this->comptable),
            fn () => $this->apport($inactif, 1000),
            fn () => Tresorerie::entree($this->caisse, 1000, NatureMouvement::Depense, Carbon::today(), 'Nature interdite', $this->comptable),
            fn () => Tresorerie::virement($this->caisse, $this->caisse, 1, Carbon::today(), 'Vers soi', $this->comptable),
        ] as $i => $tentative) {
            try {
                $tentative();
                $this->fail("Tentative n° $i acceptée.");
            } catch (OperationRefusee) {
            }
        }

        $this->assertSame(0, MouvementTresorerie::count());
    }

    #[Test]
    public function un_mouvement_ne_se_modifie_ni_ne_se_supprime(): void
    {
        // Invariant 6 sur mouvements_tresorerie.
        $mouvement = $this->apport($this->caisse, 50_000);

        foreach ([
            fn () => $mouvement->update(['montant_fcfa' => 1]),
            fn () => $mouvement->delete(),
            fn () => MouvementTresorerie::query()->update(['montant_fcfa' => 1]),
            fn () => MouvementTresorerie::query()->delete(),
        ] as $tentative) {
            try {
                $tentative();
                $this->fail('Le registre a été modifié.');
            } catch (RegistreImmuableException) {
            }
        }

        $this->assertSame(50_000, $this->caisse->solde());
    }

    #[Test]
    public function la_contre_passation_annule_par_un_mouvement_inverse_motive(): void
    {
        $erreur = $this->apport($this->caisse, 300_000);

        $annulations = Tresorerie::contrePasser($erreur, 'Montant saisi deux fois', $this->comptable);

        $this->assertCount(1, $annulations);
        $this->assertSame(SensMouvement::Sortie, $annulations[0]->sens);
        $this->assertSame($erreur->id, $annulations[0]->annule_id);
        $this->assertSame('Montant saisi deux fois', $annulations[0]->motif);
        $this->assertSame(0, $this->caisse->solde());
        // L'original reste là : l'histoire ne se réécrit pas.
        $this->assertSame(2, MouvementTresorerie::count());
    }

    #[Test]
    public function on_ne_contre_passe_ni_deux_fois_ni_une_contre_passation_ni_sans_motif(): void
    {
        $erreur = $this->apport($this->caisse, 300_000);

        $this->expectExceptionOnce(fn () => Tresorerie::contrePasser($erreur, '', $this->comptable), 'motif');

        $annulation = Tresorerie::contrePasser($erreur, 'Erreur de saisie', $this->comptable)[0];

        $this->expectExceptionOnce(fn () => Tresorerie::contrePasser($erreur, 'Encore une fois', $this->comptable), 'déjà été contre-passé');
        $this->expectExceptionOnce(fn () => Tresorerie::contrePasser($annulation, 'Annuler l\'annulation', $this->comptable), 'ne se contre-passe pas');

        $this->assertSame(0, $this->caisse->solde());
    }

    #[Test]
    public function contre_passer_une_entree_deja_depensee_est_refuse(): void
    {
        $banque = CompteTresorerie::factory()->create();
        $apport = $this->apport($this->caisse, 100_000);
        Tresorerie::virement($this->caisse, $banque, 80_000, Carbon::today(), 'Dépôt', $this->comptable);

        $this->expectExceptionOnce(fn () => Tresorerie::contrePasser($apport, 'Apport erroné', $this->comptable), 'Solde insuffisant');
        $this->assertSame(20_000, $this->caisse->solde());
    }

    #[Test]
    public function contre_passer_un_virement_annule_ses_deux_jambes(): void
    {
        $banque = CompteTresorerie::factory()->create();
        $this->apport($this->caisse, 1_000_000);
        $virement = Tresorerie::virement($this->caisse, $banque, 400_000, Carbon::today(), 'Dépôt', $this->comptable);

        $annulations = Tresorerie::contrePasser($virement['sortie'], 'Mauvais compte', $this->comptable);

        $this->assertCount(2, $annulations);
        $this->assertSame(1_000_000, $this->caisse->solde());
        $this->assertSame(0, $banque->solde());
    }

    #[Test]
    public function une_avance_va_sur_la_caisse_d_un_agent_et_devient_son_reste_a_justifier(): void
    {
        $agent = User::factory()->role(Role::Agent)->create(['nom' => 'Koffi']);
        $caisseKoffi = CompteTresorerie::factory()->caisseDe($agent)->create();
        $this->apport($this->caisse, 2_000_000);

        Tresorerie::avanceAgent($this->caisse, $caisseKoffi, 750_000, Carbon::today(), 'Avance achats semaine', $this->comptable);

        $this->assertSame(750_000, $caisseKoffi->solde());
        $this->assertSame(NatureMouvement::AvanceAgent, MouvementTresorerie::query()->where('compte_id', $caisseKoffi->id)->firstOrFail()->nature);

        $banque = CompteTresorerie::factory()->create();
        $this->expectExceptionOnce(
            fn () => Tresorerie::avanceAgent($this->caisse, $banque, 1000, Carbon::today(), 'Pas une caisse d\'agent', $this->comptable),
            'caisse d\'un agent',
        );
    }

    #[Test]
    public function des_montants_de_campagne_au_dela_de_2_puissance_31_restent_exacts(): void
    {
        // Pièges du skill argent-et-kilos : sqlite et MySQL doivent sommer pareil au-delà de 2³¹.
        $this->apport($this->caisse, 2_147_483_647);
        $this->apport($this->caisse, 2_147_483_647);
        $this->apport($this->caisse, 6);

        $this->assertSame(4_294_967_300, $this->caisse->solde());
    }

    private function expectExceptionOnce(callable $tentative, string $message): void
    {
        try {
            $tentative();
            $this->fail("Aucune exception, « $message » attendu.");
        } catch (OperationRefusee $e) {
            $this->assertStringContainsString($message, $e->getMessage());
        }
    }
}
