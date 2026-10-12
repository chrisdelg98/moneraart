<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string|null $seo_title
 * @property string|null $seo_description
 * @property string|null $canonical_url
 * @property string|null $robots
 * @property string|null $og_title
 * @property string|null $og_description
 * @property string|null $og_image_path
 */
class SeoMetadata extends Model
{
    protected $table = 'seo_metadata';

    protected $guarded = ['id'];

    /** @return MorphTo<Model, $this> */
    public function seoable(): MorphTo
    {
        return $this->morphTo();
    }

    /** Whether anything here is worth preferring over generated text. */
    public function isEmpty(): bool
    {
        return blank($this->seo_title)
            && blank($this->seo_description)
            && blank($this->canonical_url)
            && blank($this->robots)
            && blank($this->og_title)
            && blank($this->og_description)
            && blank($this->og_image_path);
    }
}
