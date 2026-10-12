<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\BlindIndex;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $type
 * @property string $status
 * @property string|null $email_domain
 * @property string|null $error
 * @property Carbon $created_at
 */
class EmailEvent extends Model
{
    public const SENT = 'sent';

    public const FAILED = 'failed';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Records one attempt.
     *
     * Takes the address in clear and keeps none of it: the hash is what makes
     * a customer's history findable, the domain is what makes a provider-wide
     * outage visible, and neither is the address itself.
     */
    public static function record(
        string $email,
        string $type,
        string $status,
        ?string $subject = null,
        ?string $error = null,
        ?int $orderId = null,
    ): self {
        return self::create([
            'email_hash' => BlindIndex::email($email),
            'email_domain' => str($email)->after('@')->lower()->limit(64, '')->toString() ?: null,
            'type' => class_basename($type),
            'subject' => $subject,
            'status' => $status,
            // Truncated: a stack trace in a column nobody reads is noise, and
            // the first line is what says what went wrong.
            'error' => $error === null ? null : mb_substr($error, 0, 2000),
            'order_id' => $orderId,
            'created_at' => now(),
        ]);
    }
}
