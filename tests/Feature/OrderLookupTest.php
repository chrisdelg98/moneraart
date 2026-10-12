<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\DownloadGrant;
use App\Notifications\DownloadLinksReady;
use App\Notifications\OrderHistoryLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Notification::fake();
    Storage::fake('private');
    Storage::fake('public');
});

it('renders the lookup page', function (): void {
    $this->get(route('orders.find'))->assertOk()->assertSee('Your orders');
});

it('answers the same whether or not the address has orders', function (): void {
    $order = paidOrder();

    $known = $this->post(route('orders.find.send'), ['email' => $order->customer->email]);
    $unknown = $this->post(route('orders.find.send'), ['email' => 'nobody@example.com']);

    // Any difference here turns this box into a way of asking us which
    // addresses have bought from the store.
    expect($known->getSession()->get('notice'))
        ->toBe($unknown->getSession()->get('notice'));
});

it('creates nothing for an address that has never bought', function (): void {
    $this->post(route('orders.find.send'), ['email' => 'nobody@example.com']);

    // Customer::forEmail() would have made a row for every address typed in.
    expect(Customer::count())->toBe(0);
});

it('emails the link only to an address that really has orders', function (): void {
    $order = paidOrder();

    $this->post(route('orders.find.send'), ['email' => $order->customer->email]);

    Notification::assertSentTo($order->customer->refresh(), OrderHistoryLink::class);
});

it('refuses the history page without a signature', function (): void {
    $order = paidOrder();

    $this->get(route('orders.history', $order->customer))->assertForbidden();
});

it('refuses a signature that has expired', function (): void {
    $order = paidOrder();

    $url = URL::temporarySignedRoute('orders.history', now()->addHour(), ['customer' => $order->customer->uuid]);

    $this->travel(2)->hours();

    $this->get($url)->assertForbidden();
});

it('lists what was bought, from the snapshot', function (): void {
    $order = paidOrder();
    $url = URL::temporarySignedRoute('orders.history', now()->addHour(), ['customer' => $order->customer->uuid]);

    $this->get($url)->assertOk()
        ->assertSee($order->number)
        ->assertSee($order->items->first()->title_snapshot);
});

it('leaves an unpaid order off the list', function (): void {
    $order = paidOrder();
    $pending = pendingOrder();
    $pending->forceFill(['customer_id' => $order->customer_id])->save();

    $url = URL::temporarySignedRoute('orders.history', now()->addHour(), ['customer' => $order->customer->uuid]);

    $this->get($url)->assertOk()
        ->assertSee($order->number)
        ->assertDontSee($pending->number);
});

it('reissues the files to the buyer, never to the screen', function (): void {
    $order = paidOrder();
    $order->forceFill(['status' => OrderStatus::Completed])->save();

    $url = URL::temporarySignedRoute('orders.resend', now()->addHour(), ['order' => $order->uuid]);

    $response = $this->post($url);

    // The tokens are stored hashed, so there is nothing to show — only
    // something to send. Whoever pressed this gains nothing unless they can
    // read the buyer's inbox.
    Notification::assertSentTo($order->customer, DownloadLinksReady::class);

    expect($response->getContent())->not->toContain('/d/')
        ->and(DownloadGrant::where('order_id', $order->id)->exists())->toBeTrue();
});

it('refuses to reissue without a signature', function (): void {
    $order = paidOrder();
    $order->forceFill(['status' => OrderStatus::Completed])->save();

    $this->post(route('orders.resend', $order))->assertForbidden();
});

it('refuses to reissue a refunded order', function (): void {
    $order = paidOrder();
    $order->forceFill(['status' => OrderStatus::Refunded, 'refunded_at' => now()])->save();

    $url = URL::temporarySignedRoute('orders.resend', now()->addHour(), ['order' => $order->uuid]);

    $this->post($url);

    Notification::assertNotSentTo($order->customer, DownloadLinksReady::class);
});

it('leaves no dead end when a download link cannot be found at all', function (): void {
    // Nothing to renew, so until this existed the page was a support email
    // waiting to be written.
    $this->get('/d/'.str_repeat('a', 64))
        // 404, and still a page with a way forward on it.
        ->assertNotFound()
        ->assertSee(route('orders.find'), escape: false);
});

it('limits how often a stranger can make us send mail', function (): void {
    $order = paidOrder();

    foreach (range(1, 5) as $ignored) {
        $this->post(route('orders.find.send'), ['email' => $order->customer->email]);
    }

    // The sixth is refused: this endpoint sends email on anyone's say-so.
    $this->post(route('orders.find.send'), ['email' => $order->customer->email])
        ->assertStatus(429);
});
