<?php

declare(strict_types=1);

namespace App\Services\Seo;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Everything the head of one page needs to say about itself.
 *
 * A value object rather than loose variables, so a page that forgets a field
 * gets the sensible default instead of a blank tag — an empty description is
 * worse than a generated one.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class PageMeta implements Arrayable
{
    /**
     * @param  array<int, array<string, mixed>>  $jsonLd
     */
    public function __construct(
        public string $title,
        public ?string $description = null,
        public ?string $canonical = null,
        public string $robots = 'index,follow',
        public ?string $image = null,
        public array $jsonLd = [],
    ) {}

    /** Pages that must never be indexed, whatever the store's setting says. */
    public function noindex(): self
    {
        return new self(
            $this->title,
            $this->description,
            $this->canonical,
            'noindex,nofollow',
            $this->image,
            $this->jsonLd,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'canonical' => $this->canonical,
            'robots' => $this->robots,
            'image' => $this->image,
            'jsonLd' => $this->jsonLd,
        ];
    }
}
