<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
        'is_owner',
        'is_admin',
        'member_id',
        'is_active',
        'invited_at',
        'invitation_accepted_at',
        'last_login_at',
        'last_login_ip',
        'deactivated_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_owner' => 'boolean',
            'is_admin' => 'boolean',
            'is_active' => 'boolean',
            'invited_at' => 'datetime',
            'invitation_accepted_at' => 'datetime',
            'last_login_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted',
        ];
    }

    /**
     * Check if the user is the account owner.
     */
    public function isOwner(): bool
    {
        return (bool) $this->is_owner;
    }

    /**
     * Check if the user account is active.
     */
    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    /**
     * Check if the user has admin-level access (owner, admin flag, or Admin role).
     */
    public function hasAdminAccess(): bool
    {
        return $this->is_owner || $this->is_admin || $this->hasRole('Admin');
    }

    /**
     * Determine if the user has two-factor authentication fully enabled.
     */
    public function hasTwoFactorEnabled(): bool
    {
        try {
            return ! is_null($this->two_factor_secret) && ! is_null($this->two_factor_recovery_codes);
        } catch (DecryptException $e) {
            logger()->error('Failed to decrypt 2FA data for user. 2FA may be silently disabled.', [
                'user_id' => $this->id,
                'exception' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Generate a fresh set of 8 recovery codes in XXXX-XXXX format.
     *
     * @return string[]
     */
    public function generateRecoveryCodes(): array
    {
        return Collection::times(8, fn (): string => Str::upper(Str::random(4)).'-'.Str::upper(Str::random(4)))
            ->all();
    }

    /**
     * Get the user's initials.
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }
}
