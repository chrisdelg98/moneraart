<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use Filament\Actions\Action;
use Filament\Resources\Pages\Page;

/**
 * Moves Save and Cancel from the foot of a form to its header.
 *
 * The product form is tall enough that the buttons sit below the fold, so
 * saving means scrolling past everything you just filled in to reach them.
 * At the top they are visible from the first field to the last.
 *
 * Applied as a trait rather than configured per page, so a form added later
 * does not quietly get the old behaviour. See §13.3.
 *
 * @phpstan-require-extends Page
 */
trait PutsFormActionsInHeader
{
    /** @return array<Action> */
    protected function getFormActions(): array
    {
        return [];
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        // Record actions first — delete, restore — then the save, so the
        // primary button is always the rightmost thing on the page.
        return [...$this->getRecordActions(), ...$this->getSaveActions()];
    }

    /** @return array<Action> */
    protected function getRecordActions(): array
    {
        return [];
    }

    /** @return array<Action> */
    protected function getSaveActions(): array
    {
        // Filament orders these primary-first, which is right at the foot of
        // a form and backwards in a header: there the rightmost button is the
        // one the eye lands on. Reversed, Cancel leads and Save closes.
        return array_reverse(parent::getFormActions());
    }
}
