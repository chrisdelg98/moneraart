<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property string $key @property string $slug */
class LegalDocument extends Model
{
    protected $guarded = ['id'];

    /** @return HasMany<LegalDocumentVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(LegalDocumentVersion::class);
    }

    /** The version in force right now. */
    public function current(): ?LegalDocumentVersion
    {
        return $this->versions()
            ->whereNotNull('published_at')
            ->where('effective_at', '<=', now())
            ->orderByDesc('effective_at')
            ->with('bodies')
            ->first();
    }
}
