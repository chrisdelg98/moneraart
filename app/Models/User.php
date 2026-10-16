<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property string $email
 * @property string|null $app_authentication_secret
 * @property array<string>|null $app_authentication_recovery_codes
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes'])]
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Present on every instance, so reading them is never a missing attribute.
     *
     * A freshly created model holds only what was inserted, and strict mode
     * throws rather than quietly returning null — which is the behaviour we
     * want everywhere except for a column that is legitimately absent until
     * somebody opts in.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'app_authentication_secret' => null,
        'app_authentication_recovery_codes' => null,
    ];

    /**
     * Who may open the admin panel.
     *
     * Filament's own default grants access only in the `local` environment,
     * which silently returns 403 everywhere else — including tests and
     * production. Access is stated here rather than inherited.
     *
     * The role gate (owner / admin / support) arrives with the users table
     * extension in Phase 5; until then an authenticated user is an operator.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return true;
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
            // Encrypted, not hashed: we have to read these back to check a
            // code. A dump of this table must not hand over the ability to
            // pass the challenge it exists to protect.
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
        ];
    }

    // ── Two-factor ───────────────────────────────────────────────────────

    public function getAppAuthenticationSecret(): ?string
    {
        return $this->app_authentication_secret;
    }

    public function saveAppAuthenticationSecret(?string $secret): void
    {
        $this->app_authentication_secret = $secret;
        $this->save();
    }

    /**
     * What the authenticator app shows beside the code.
     *
     * The address, because an owner with a personal account and a store
     * account needs to tell the two entries apart at a glance.
     */
    public function getAppAuthenticationHolderName(): string
    {
        return $this->email;
    }

    /** @return array<string>|null */
    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->app_authentication_recovery_codes;
    }

    /** @param array<string>|null $codes */
    public function saveAppAuthenticationRecoveryCodes(?array $codes): void
    {
        $this->app_authentication_recovery_codes = $codes;
        $this->save();
    }

    /** Whether this operator has actually turned it on. */
    public function hasTwoFactorEnabled(): bool
    {
        return filled($this->app_authentication_secret);
    }
}
