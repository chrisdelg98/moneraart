<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Filament\Actions\Action;
use Filament\Support\Enums\Size;

/**
 * Row actions as small outlined buttons rather than bare text.
 *
 * Filament renders them as links, which in a table of links reads as more
 * data: the eye has no way to tell "Art Deco" from "Edit" until it has read
 * both. An outline says "this does something" before the word is read.
 *
 * Outlined rather than filled, because a row can carry four of them and four
 * solid blocks would outweigh the row they belong to.
 */
final class RowActions
{
    /**
     * @param  array<Action>  $actions
     * @return array<Action>
     */
    public static function style(array $actions): array
    {
        return array_map(
            // Colour is left alone: an action that set danger keeps its red
            // outline, which is the whole point of having set it.
            fn (Action $action): Action => $action->button()->size(Size::Small)->outlined(),
            $actions,
        );
    }
}
