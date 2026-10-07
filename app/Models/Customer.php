<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\BlindIndex;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;

/**
 * Only what delivering a purchase requires. No address, no phone, no password.
 *
 * Email and name are encrypted at rest; the HMAC blind index is the only way
 * to find a customer, which means exact lookup only. See §9.
 *
 * @property string $email
 * @property string $email_hash
 * @property bool $marketing_consent
 * @property string $locale
 */
class Customer extends Model
{
    use HasUuids, Notifiable;

    protected $guarded = ['id'];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected function casts(): array
    {
        return [
            'email_encrypted' => 'encrypted',
            'name_encrypted' => 'encrypted',
            'marketing_consent' => 'boolean',
            'first_order_at' => 'datetime',
            'last_order_at' => 'datetime',
        ];
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public static function forEmail(string $email, string $locale = 'en'): self
    {
        $email = mb_strtolower(trim($email));

        return self::firstOrCreate(
            ['email_hash' => BlindIndex::email($email)],
            [
                'email_encrypted' => $email,
                'email_domain' => str($email)->after('@')->toString(),
                'locale' => $locale,
            ],
        );
    }

    public function getEmailAttribute(): string
    {
        return (string) $this->email_encrypted;
    }
}
