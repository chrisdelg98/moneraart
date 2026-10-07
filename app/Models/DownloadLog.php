<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only: no updated_at, never edited. */
class DownloadLog extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /** @return BelongsTo<DownloadGrant, $this> */
    public function grant(): BelongsTo
    {
        return $this->belongsTo(DownloadGrant::class, 'download_grant_id');
    }
}
