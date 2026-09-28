<?php

namespace App\Models;

use App\Enums\Role;
use App\Models\Concerns\Journalise;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Pas d'inscription publique : les comptes sont créés par l'admin.
 * Un compte désactivé (`actif = false`) ne peut plus se connecter.
 * Un compte sans rôle n'a aucun droit.
 *
 * @property int $id
 * @property string $nom
 * @property string|null $telephone
 * @property string $email
 * @property Role|null $role
 * @property bool $actif
 */
#[Fillable(['nom', 'telephone', 'email', 'role', 'password', 'actif'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Journalise, Notifiable;

    public function aLeRole(Role ...$roles): bool
    {
        return $this->role !== null && in_array($this->role, $roles, true);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'actif' => 'boolean',
            'role' => Role::class,
        ];
    }
}
