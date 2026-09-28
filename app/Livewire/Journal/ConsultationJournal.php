<?php

namespace App\Livewire\Journal;

use App\Enums\ActionJournal;
use App\Models\JournalActivite;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Lecture seule : le journal ne se modifie pas, ici comme ailleurs.
 */
#[Title('Journal d\'activité')]
class ConsultationJournal extends Component
{
    use WithPagination;

    public const PAR_PAGE = 50;

    #[Url(as: 'action', except: '')]
    public string $filtreAction = '';

    #[Url(as: 'utilisateur', except: '')]
    public string $filtreUtilisateur = '';

    public function mount(): void
    {
        $this->authorize('voir-journal');
    }

    public function updated(string $propriete): void
    {
        if (str_starts_with($propriete, 'filtre')) {
            $this->validate([
                'filtreAction' => ['nullable', Rule::enum(ActionJournal::class)],
                'filtreUtilisateur' => ['nullable', 'integer'],
            ]);
            $this->resetPage();
        }
    }

    public function render(): View
    {
        // Revérifié à chaque rendu : un changement de filtre est une requête comme une autre.
        $this->authorize('voir-journal');

        $lignes = JournalActivite::query()
            ->with('user:id,nom')
            ->when(ActionJournal::tryFrom($this->filtreAction), fn ($q, $action) => $q->where('action', $action))
            ->when(ctype_digit($this->filtreUtilisateur), fn ($q) => $q->where('user_id', (int) $this->filtreUtilisateur))
            ->orderByDesc('id')
            ->paginate(self::PAR_PAGE);

        return view('livewire.journal.consultation-journal', [
            'lignes' => $lignes,
            'actions' => ActionJournal::cases(),
            'utilisateurs' => User::query()->orderBy('nom')->get(['id', 'nom']),
        ]);
    }
}
