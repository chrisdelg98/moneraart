<?php

declare(strict_types=1);

namespace App\Actions\Products;

final readonly class ImportResult
{
    /** @param list<string> $errors */
    private function __construct(
        public bool $ok,
        public int $created,
        public array $errors,
    ) {}

    public static function succeeded(int $created): self
    {
        return new self(true, $created, []);
    }

    /** @param list<string> $errors */
    public static function failed(array $errors): self
    {
        return new self(false, 0, $errors);
    }

    public function summary(): string
    {
        if ($this->ok) {
            return $this->created === 1
                ? '1 product imported as a draft.'
                : "{$this->created} products imported as drafts.";
        }

        $count = count($this->errors);

        return $count === 1
            ? 'Nothing was imported — 1 problem to fix.'
            : "Nothing was imported — {$count} problems to fix.";
    }
}
