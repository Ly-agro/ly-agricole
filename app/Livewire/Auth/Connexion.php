<?php

namespace App\Livewire\Auth;

use App\Enums\ActionJournal;
use App\Services\Journal;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('components.layouts.invite')]
#[Title('Connexion')]
class Connexion extends Component
{
    public const TENTATIVES_MAX = 5;

    #[Validate('required|string|email', as: 'adresse e-mail')]
    public string $email = '';

    #[Validate('required|string', as: 'mot de passe')]
    public string $password = '';

    public bool $remember = false;

    public function connecter(): void
    {
        $this->validate();

        $cle = $this->cleLimitation();

        if (RateLimiter::tooManyAttempts($cle, self::TENTATIVES_MAX)) {
            event(new Lockout(request()));
            Journal::enregistrer(ActionJournal::BlocageConnexion, apres: ['email' => $this->email]);

            throw ValidationException::withMessages([
                'email' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($cle)]),
            ]);
        }

        // `actif => true` dans les identifiants : un compte désactivé reçoit le même
        // message qu'un mauvais mot de passe, sans révéler que le compte existe.
        $identifiants = ['email' => $this->email, 'password' => $this->password, 'actif' => true];

        if (! Auth::attempt($identifiants, $this->remember)) {
            RateLimiter::hit($cle);
            $this->reset('password');

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($cle);
        session()->regenerate();

        $this->redirectIntended(route('tableau-de-bord', absolute: false));
    }

    private function cleLimitation(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }
}
