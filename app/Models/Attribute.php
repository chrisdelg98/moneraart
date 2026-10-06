<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An axis the catalog is organised by. Three are chosen by hand (style, room,
 * theme); the rest are computed from the uploaded files. See §4.1.
 *
 * @property string $key
 * @property bool $is_computed
 */
class Attribute extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_filterable' => 'boolean',
            'is_seo_landing' => 'boolean',
            'is_computed' => 'boolean',
        ];
    }

    /** @return HasMany<AttributeValue, $this> */
    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class)->orderBy('position');
    }

    /** Keys the administrator picks from a dropdown. */
    public const MANUAL = ['style', 'room', 'theme'];

    /** Keys derived from the uploaded artwork and files. */
    public const COMPUTED = ['ratio', 'orientation', 'color'];
}
