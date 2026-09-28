<?php

namespace Tests\Feature\Journal;

use App\Enums\ActionJournal;
use App\Exceptions\RegistreImmuableException;
use App\Models\Concerns\Immuable;
use App\Models\JournalActivite;
use App\Services\Journal;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Invariant 6 (docs/MODELE_DE_DONNEES.md) : aucune modification possible d'un
 * registre immuable.
 */
class JournalImmuableTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{Closure(JournalActivite): mixed}> */
    public static function tentatives(): array
    {
        return [
            'update() sur le modèle' => [fn (JournalActivite $l) => $l->update(['action' => ActionJournal::Suppression])],
            'save() après changement' => [function (JournalActivite $l) {
                $l->ip = '10.0.0.1';

                return $l->save();
            }],
            'saveQuietly() (sans événements)' => [function (JournalActivite $l) {
                $l->ip = '10.0.0.1';

                return $l->saveQuietly();
            }],
            'delete() sur le modèle' => [fn (JournalActivite $l) => $l->delete()],
            'deleteQuietly() (sans événements)' => [fn (JournalActivite $l) => $l->deleteQuietly()],
            'query()->update()' => [fn () => JournalActivite::query()->update(['ip' => '10.0.0.1'])],
            'where()->delete()' => [fn (JournalActivite $l) => JournalActivite::where('id', $l->id)->delete()],
            'query()->increment()' => [fn () => JournalActivite::query()->increment('user_id')],
            'upsert()' => [fn (JournalActivite $l) => JournalActivite::query()->upsert([['id' => $l->id, 'action' => 'creation']], ['id'], ['action'])],
            'destroy()' => [fn (JournalActivite $l) => JournalActivite::destroy($l->id)],
        ];
    }

    /**
     * @param  Closure(JournalActivite): mixed  $tentative
     */
    #[Test]
    #[DataProvider('tentatives')]
    public function une_ligne_du_journal_ne_peut_etre_ni_modifiee_ni_supprimee(Closure $tentative): void
    {
        $ligne = Journal::enregistrer(ActionJournal::Connexion, apres: ['email' => 'x@ly-agricole.test']);
        $avant = $ligne->fresh()?->getAttributes();

        try {
            $tentative($ligne);
            $this->fail('Aucune exception : le journal a été modifié.');
        } catch (RegistreImmuableException $e) {
            $this->assertStringContainsString('journal_activite', $e->getMessage());
        }

        $this->assertSame(1, JournalActivite::count());
        $this->assertSame($avant, $ligne->fresh()?->getAttributes());
    }

    #[Test]
    public function un_registre_immuable_sans_son_constructeur_de_requetes_protege_refuse_de_demarrer(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('#[UseEloquentBuilder(BuilderImmuable::class)]');

        new class extends Model
        {
            use Immuable;

            protected $table = 'journal_activite';
        };
    }

    #[Test]
    public function on_peut_toujours_ajouter_des_lignes(): void
    {
        Journal::enregistrer(ActionJournal::Connexion);
        Journal::enregistrer(ActionJournal::Deconnexion);

        $this->assertSame(2, JournalActivite::count());
    }
}
