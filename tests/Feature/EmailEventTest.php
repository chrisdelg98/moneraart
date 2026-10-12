<?php

declare(strict_types=1);

use App\Filament\Resources\EmailEvents\EmailEventResource;
use App\Filament\Resources\EmailEvents\Pages\ListEmailEvents;
use App\Filament\Widgets\SchedulerHealth;
use App\Models\EmailEvent;
use App\Models\User;
use App\Notifications\OrderConfirmed;
use App\Support\BlindIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('private');
    Storage::fake('public');
});

it('writes a row for an email that went out', function (): void {
    $order = paidOrder();

    $order->customer->notify(new OrderConfirmed($order));

    $event = EmailEvent::firstOrFail();

    expect($event->status)->toBe(EmailEvent::SENT)
        ->and($event->type)->toBe('OrderConfirmed')
        ->and($event->order_id)->toBe($order->id);
});

it('keeps the address hashed and the domain in clear', function (): void {
    $order = paidOrder();

    $order->customer->notify(new OrderConfirmed($order));

    $event = EmailEvent::firstOrFail();

    // The hash is what makes one customer's history findable; the domain is
    // what makes a run of failures at one provider visible. Neither is the
    // address itself.
    expect($event->email_hash)->toBe(BlindIndex::email('buyer@example.com'))
        ->and($event->email_domain)->toBe('example.com')
        ->and($event->getAttributes())->not->toHaveKey('email');
});

it('writes a row when a send fails, with what went wrong', function (): void {
    $order = paidOrder();

    Event::dispatch(new NotificationFailed(
        $order->customer,
        new OrderConfirmed($order),
        'mail',
        ['exception' => new RuntimeException('Connection could not be established')],
    ));

    $event = EmailEvent::where('status', EmailEvent::FAILED)->firstOrFail();

    // Until this existed, this went into failed_jobs and nowhere a person
    // looks — while a customer who paid waited for a message.
    expect($event->error)->toContain('Connection could not be established')
        ->and($event->type)->toBe('OrderConfirmed');
});

it('says so on the dashboard when email has been failing', function (): void {
    $this->actingAs(User::factory()->create());

    expect(SchedulerHealth::canView())->toBeFalse();

    EmailEvent::record('buyer@example.com', 'OrderConfirmed', EmailEvent::FAILED, error: 'SMTP refused');

    expect(SchedulerHealth::canView())->toBeTrue();

    livewire(SchedulerHealth::class)->assertSee('failed to send');
});

it('ignores a failure older than a day', function (): void {
    $this->actingAs(User::factory()->create());

    EmailEvent::record('buyer@example.com', 'OrderConfirmed', EmailEvent::FAILED)
        ->forceFill(['created_at' => now()->subDays(3)])->save();

    // An outage fixed last week is not something to be warned about today.
    expect(SchedulerHealth::canView())->toBeFalse();
});

it('never lets the log be written to by hand', function (): void {
    $this->actingAs(User::factory()->create());

    $event = EmailEvent::record('buyer@example.com', 'OrderConfirmed', EmailEvent::SENT);

    expect(EmailEventResource::canCreate())->toBeFalse()
        ->and(EmailEventResource::canEdit($event))->toBeFalse();

    $this->get('/admin/email-events')->assertOk();

    livewire(ListEmailEvents::class)->assertSee('example.com');
});
