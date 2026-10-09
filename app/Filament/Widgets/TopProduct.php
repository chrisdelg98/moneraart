<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\ProductImage;
use App\Support\Money;

/**
 * One row of the selling-this-month panel.
 *
 * A typed object rather than an array shape: the Blade reads better for it,
 * and `url` being nullable is then a fact the template cannot forget.
 */
final readonly class TopProduct
{
    public function __construct(
        public string $title,
        public int $sales,
        public Money $revenue,
        public ?ProductImage $cover,
        /** Null when the product has been deleted since the sale. */
        public ?string $url,
    ) {}
}
