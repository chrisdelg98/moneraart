<?php

declare(strict_types=1);

use App\Enums\OrderStatus;

it('allows only the documented transitions', function (): void {
    expect(OrderStatus::Pending->canTransitionTo(OrderStatus::Paid))->toBeTrue()
        ->and(OrderStatus::Pending->canTransitionTo(OrderStatus::ManualReview))->toBeTrue()
        ->and(OrderStatus::Paid->canTransitionTo(OrderStatus::Completed))->toBeTrue()
        ->and(OrderStatus::ManualReview->canTransitionTo(OrderStatus::Completed))->toBeTrue()
        ->and(OrderStatus::Completed->canTransitionTo(OrderStatus::Refunded))->toBeTrue();
});

it('refuses to skip payment', function (): void {
    expect(OrderStatus::Pending->canTransitionTo(OrderStatus::Completed))->toBeFalse();
});

it('refuses to resurrect a terminal order', function (): void {
    expect(OrderStatus::Refunded->isTerminal())->toBeTrue()
        ->and(OrderStatus::Cancelled->isTerminal())->toBeTrue()
        ->and(OrderStatus::Refunded->canTransitionTo(OrderStatus::Completed))->toBeFalse()
        ->and(OrderStatus::Cancelled->canTransitionTo(OrderStatus::Paid))->toBeFalse();
});

it('knows which statuses entitle a customer to files', function (): void {
    expect(OrderStatus::Paid->isFulfillable())->toBeTrue()
        ->and(OrderStatus::Completed->isFulfillable())->toBeTrue()
        ->and(OrderStatus::Pending->isFulfillable())->toBeFalse()
        ->and(OrderStatus::ManualReview->isFulfillable())->toBeFalse()
        ->and(OrderStatus::Refunded->isFulfillable())->toBeFalse();
});

it('flags manual review as needing a human', function (): void {
    expect(OrderStatus::ManualReview->needsAttention())->toBeTrue()
        ->and(OrderStatus::Completed->needsAttention())->toBeFalse();
});
