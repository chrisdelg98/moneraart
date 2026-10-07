<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The idempotency ledger. The unique index on event_id is the lock — a
 * duplicate insert throwing is how a replayed webhook is detected. See §6.7.
 *
 * @property array<string, mixed> $payload
 */
class WebhookEvent extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'signature_verified' => 'boolean',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
