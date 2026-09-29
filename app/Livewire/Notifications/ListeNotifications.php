<?php

namespace App\Livewire\Notifications;

use App\Exceptions\OperationRefusee;
use App\Models\AbonnementPush;
use App\Models\User;
use App\Services\Notifications;
use Illuminate\Contracts\View\View;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Les avis de l'utilisateur (les 50 derniers) et l'activation du « push » sur ce
 * navigateur. Chacun ne voit que les siens.
 */
#[Title('Notifications')]
class ListeNotifications extends Component
{
    public const FILTRES = ['toutes' => 'Toutes', 'non_lues' => 'Non lues', 'a_valider' => 'À valider'];

    #[Url(as: 'voir', except: 'toutes')]
    public string $filtre = 'toutes';

    public string $statut = '';

    public function ouvrir(string $id): mixed
    {
        $avis = $this->moi()->notifications()->findOrFail($id);
        $avis->markAsRead();
        $url = $avis->data['url'] ?? null;

        return is_string($url) && $url !== '' ? $this->redirect($url) : null;
    }

    public function toutLu(): void
    {
        $this->moi()->unreadNotifications->markAsRead();
        $this->statut = 'Toutes les notifications sont marquées comme lues.';
    }

    /**
     * Appelé par le navigateur après PushManager.subscribe() (voir la vue).
     *
     * @param  array{endpoint?: mixed, keys?: array{p256dh?: mixed, auth?: mixed}}  $abonnement
     */
    public function enregistrerAbonnement(array $abonnement, string $appareil = ''): void
    {
        try {
            Notifications::abonner(
                $this->moi(), AbonnementPush::WEB, (string) ($abonnement['endpoint'] ?? ''),
                isset($abonnement['keys']['p256dh']) ? (string) $abonnement['keys']['p256dh'] : null,
                isset($abonnement['keys']['auth']) ? (string) $abonnement['keys']['auth'] : null,
                $appareil !== '' ? $appareil : null,
            );
        } catch (OperationRefusee $e) {
            throw ValidationException::withMessages(['push' => $e->getMessage()]);
        }
        $this->statut = 'Notifications activées sur ce navigateur.';
    }

    public function retirerAbonnement(string $adresse): void
    {
        Notifications::desabonner($this->moi(), $adresse);
        $this->statut = 'Notifications désactivées sur ce navigateur.';
    }

    private function moi(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    public function render(): View
    {
        $moi = $this->moi();
        $avis = $moi->notifications()
            ->when($this->filtre === 'non_lues', fn ($q) => $q->whereNull('read_at'))
            ->when($this->filtre === 'a_valider', fn ($q) => $q->where('data', 'like', '%"categorie":"a_valider"%'))
            ->latest()->limit(50)->get();

        return view('livewire.notifications.liste-notifications', [
            // Regroupés par jour : « Aujourd'hui », « Hier », puis la date.
            'parJour' => $avis->groupBy(fn (DatabaseNotification $n) => match (true) {
                $n->created_at->isToday() => "Aujourd'hui",
                $n->created_at->isYesterday() => 'Hier',
                default => $n->created_at->translatedFormat('l j F'),
            }),
            'nonLues' => $moi->unreadNotifications()->count(),
            'aValider' => $moi->unreadNotifications()->where('data', 'like', '%"categorie":"a_valider"%')->count(),
            'filtres' => self::FILTRES,
            'clePublique' => config('notifications_push.vapid.publique'),
            'appareils' => AbonnementPush::query()->where('user_id', $moi->id)->where('canal', AbonnementPush::WEB)->count(),
        ]);
    }
}
