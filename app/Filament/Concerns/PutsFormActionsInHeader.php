<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
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

    /**
     * What a page overrides to offer something other than a plain Save.
     *
     * @return array<Action>
     */
    protected function getSaveActions(): array
    {
        return $this->defaultSaveActions();
    }

    /**
     * Filament's own buttons, reordered.
     *
     * It lists them primary-first, which is right at the foot of a form and
     * backwards in a header: there the rightmost button is the one the eye
     * lands on. Reversed, Cancel leads and Save closes.
     *
     * Separate from getSaveActions() so an override can still reach them —
     * parent:: would look past the trait at a class that has neither.
     *
     * @return array<Action>
     */
    protected function defaultSaveActions(): array
    {
        return array_map(
            $this->callableOutsideTheForm(...),
            array_reverse(parent::getFormActions()),
        );
    }

    /**
     * Rebinds a submit button to the method it was going to submit to.
     *
     * Filament builds these to submit the surrounding <form>. In the header
     * they are outside it, so the browser has nothing to submit and the button
     * silently does nothing — while a test calling the Livewire method
     * directly still passes. Pointing them at the method instead works from
     * either place.
     */
    private function callableOutsideTheForm(Action $action): Action
    {
        $name = $action->getName();

        // Written out rather than resolved from the name: a create page has
        // no save() and an edit page has no create(), and this trait serves
        // both. A dynamic call would be a crash waiting for whichever page
        // gets a button it does not answer to.
        if ($this instanceof CreateRecord) {
            $page = $this;

            return match ($name) {
                'create' => $action->action(fn () => $page->create()),
                'createAnother' => $action->action(fn () => $page->create(another: true)),
                default => $action,
            };
        }

        if ($this instanceof EditRecord && $name === 'save') {
            $page = $this;

            return $action->action(fn () => $page->save());
        }

        return $action;
    }
}
