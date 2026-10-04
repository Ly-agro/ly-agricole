<?php

namespace Tests\Feature\Impression;

use App\Enums\CleParametre;
use App\Enums\FormePret;
use App\Enums\ModeDecaissement;
use App\Enums\NatureMouvement;
use App\Enums\Role;
use App\Enums\StatutCampagne;
use App\Enums\StatutLot;
use App\Enums\TypeFournisseur;
use App\Models\Achat;
use App\Models\Campagne;
use App\Models\CompteTresorerie;
use App\Models\Lot;
use App\Models\Magasin;
use App\Models\Parametre;
use App\Models\Producteur;
use App\Models\User;
use App\Services\Achats;
use App\Services\Prets;
use App\Services\Tickets;
use App\Services\Tresorerie;
use App\Support\Ticket58;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Tickets 58 mm (imprimante thermique) : largeur, contenu, droits. */
class TicketsTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private User $comptable;

    private User $direction;

    private Campagne $campagne;

    private Lot $lot;

    private CompteTresorerie $caisseAgent;

    private CompteTresorerie $centrale;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->role(Role::Agent)->create(['nom' => 'Koné Ibrahim']);
        $this->comptable = User::factory()->role(Role::Comptable)->create();
        $this->direction = User::factory()->role(Role::Direction)->create();
        $this->campagne = Campagne::factory()->statut(StatutCampagne::Ouverte)->create(['prix_officiel_kg_fcfa' => 400]);
        $this->lot = Lot::query()->create([
            'produit_id' => $this->campagne->produit_id, 'campagne_id' => $this->campagne->id,
            'magasin_id' => Magasin::factory()->create()->id, 'statut' => StatutLot::Ouvert, 'cree_par' => $this->comptable->id,
        ]);
        $this->centrale = CompteTresorerie::factory()->create();
        $this->caisseAgent = CompteTresorerie::factory()->caisseDe($this->agent)->create();
        Tresorerie::entree($this->centrale, 10_000_000, NatureMouvement::Apport, Carbon::today(), 'Fonds', $this->direction);
        Tresorerie::avanceAgent($this->centrale, $this->caisseAgent, 2_000_000, Carbon::today(), 'Avance', $this->direction);
    }

    private function acheter(): Achat
    {
        return Achats::enregistrer([
            'campagne_id' => $this->campagne->id, 'lot_id' => $this->lot->id,
            'fournisseur_type' => TypeFournisseur::Producteur,
            'producteur_id' => Producteur::factory()->create(['nom' => 'Coulibaly', 'prenoms' => 'Awa'])->id,
            'date_achat' => now()->subHour(), 'poids_brut_g' => 505_000, 'tare_g' => 5_000,
            'humidite_pour_mille' => 85, 'kor_centieme_lbs' => 4850,
            'prix_kg_fcfa' => 425, 'compte_id' => $this->caisseAgent->id,
        ], $this->agent);
    }

    private function assertTientSur58mm(Ticket58 $ticket): void
    {
        foreach ($ticket->lignes() as $l) {
            $max = $l['grand'] ? intdiv(Ticket58::LARGEUR, 2) : Ticket58::LARGEUR;
            $this->assertLessThanOrEqual($max, mb_strlen($l['texte']), "Ligne trop large : « {$l['texte']} »");
        }
    }

    #[Test]
    public function le_ticket_d_achat_tient_en_32_colonnes_et_reprend_le_bon(): void
    {
        Parametre::query()->updateOrCreate(['cle' => CleParametre::SeuilValidationAchat], ['valeur' => '5000000']);
        $achat = $this->acheter();

        $ticket = Tickets::achat($achat);
        $this->assertTientSur58mm($ticket);
        $texte = $ticket->enTexte();

        foreach (['LY AGRICOLE', "BON D'ACHAT", $achat->reference, 'Coulibaly Awa', 'Koné Ibrahim', '8,5 %', '48,50', 'PAYÉ EN ESPÈCES', 'Fournisseur : ____'] as $attendu) {
            $this->assertStringContainsString($attendu, $texte);
        }
        // Libellé à gauche, valeur calée à droite sur la même ligne de 32 caractères.
        $this->assertMatchesRegularExpression('/^Poids net +500 kg$/mu', $texte);
        $this->assertMatchesRegularExpression('/^Montant +212.500 FCFA$/mu', $texte);
        $this->assertStringNotContainsString('EN ATTENTE', $texte);
    }

    #[Test]
    public function un_achat_a_valider_ou_refuse_le_dit_en_tete(): void
    {
        $achat = $this->acheter(); // pas de seuil : à valider
        $this->assertStringContainsString('EN ATTENTE DE VALIDATION', Tickets::achat($achat)->enTexte());
        $this->assertMatchesRegularExpression('/^À PAYER +212.500 FCFA$/mu', Tickets::achat($achat)->enTexte());

        Achats::refuser($achat, $this->comptable, 'Pesée illisible');
        $this->assertStringContainsString('ACHAT REFUSÉ', Tickets::achat($achat->refresh())->enTexte());
    }

    #[Test]
    public function le_ticket_de_remise_donne_le_restant_du(): void
    {
        Parametre::query()->updateOrCreate(['cle' => CleParametre::SeuilValidationPret], ['valeur' => '10000000']);
        $pret = Prets::valider(Prets::demander([
            'producteur_id' => Producteur::factory()->create()->id, 'campagne_id' => $this->campagne->id,
            'montant_fcfa' => 300_000, 'forme' => FormePret::Especes, 'echeance' => Carbon::today()->addMonths(6),
        ], $this->agent), $this->direction);
        $d = Prets::decaisser($pret, ['compte_id' => $this->centrale->id, 'mode' => ModeDecaissement::Especes, 'montant_fcfa' => 200_000, 'date' => Carbon::today()],
            $this->comptable, 'prets/recus/r.jpg');

        $ticket = Tickets::remiseArgent($pret->refresh(), $d);
        $this->assertTientSur58mm($ticket);
        $this->assertMatchesRegularExpression('/^Valeur remise +200.000 FCFA$/mu', $ticket->enTexte());
        $this->assertMatchesRegularExpression('/^RESTANT DÛ +200.000 FCFA$/mu', $ticket->enTexte());

        $this->actingAs($this->comptable)->get(route('tickets.remise', [$pret, 'argent', $d->id]))
            ->assertOk()->assertSee('Imprimer (58 mm)')->assertSee('RESTANT DÛ');
    }

    #[Test]
    public function un_texte_trop_long_est_coupe_sans_depasser(): void
    {
        $t = (new Ticket58)->texte(str_repeat('Anacarde ', 10))->paire('Un libellé beaucoup trop long pour tenir sur une seule ligne', '1 000 FCFA')
            ->texte(str_repeat('X', 70));
        $this->assertTientSur58mm($t);
        $this->assertMatchesRegularExpression('/ligne +1 000 FCFA$/mu', $t->enTexte());
    }

    #[Test]
    public function l_agent_imprime_ses_achats_seulement(): void
    {
        $achat = $this->acheter();

        $this->actingAs($this->agent)->get(route('tickets.achat', $achat))->assertOk()->assertSee($achat->reference);
        $this->actingAs(User::factory()->role(Role::Agent)->create())->get(route('tickets.achat', $achat))->assertForbidden();
        $this->actingAs($this->comptable)->get(route('tickets.achat', $achat))->assertOk();
        $this->actingAs(User::factory()->role(Role::Investisseur)->create())->get(route('tickets.achat', $achat))->assertForbidden();
    }
}
