<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $version
 * @property Carbon $effective_at
 * @property Collection<int, LegalDocumentBody> $bodies
 */
class LegalDocumentVersion extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['effective_at' => 'datetime', 'published_at' => 'datetime'];
    }

    /** @return BelongsTo<LegalDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(LegalDocument::class, 'legal_document_id');
    }

    /** @return HasMany<LegalDocumentBody, $this> */
    public function bodies(): HasMany
    {
        return $this->hasMany(LegalDocumentBody::class);
    }

    public function body(?string $locale = null): ?LegalDocumentBody
    {
        $locale ??= app()->getLocale();

        return $this->bodies->firstWhere('locale', $locale)
            ?? $this->bodies->firstWhere('locale', config('store.default_locale'));
    }
}
