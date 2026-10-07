<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One customer's right to one file, counted and expiring.
 *
 * @property Carbon $expires_at
 * @property Carbon|null $revoked_at
 * @property int $download_count
 * @property int $max_downloads
 */
class DownloadGrant extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    /**
     * The plaintext token, held only in memory between generating a grant and
     * putting it in an email. Deliberately a plain property rather than an
     * attribute: an attribute would be written to a column that does not and
     * must not exist. See §8.2.
     */
    public ?string $plainToken = null;

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'first_downloaded_at' => 'datetime',
            'last_downloaded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<ProductFile, $this> */
    public function file(): BelongsTo
    {
        return $this->belongsTo(ProductFile::class, 'product_file_id');
    }

    /** @return HasMany<DownloadLog, $this> */
    public function logs(): HasMany
    {
        return $this->hasMany(DownloadLog::class);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isExhausted(): bool
    {
        return $this->download_count >= $this->max_downloads;
    }

    public function remaining(): int
    {
        return max($this->max_downloads - $this->download_count, 0);
    }

    public function isUsable(): bool
    {
        return ! $this->isRevoked() && ! $this->isExpired() && ! $this->isExhausted();
    }
}
