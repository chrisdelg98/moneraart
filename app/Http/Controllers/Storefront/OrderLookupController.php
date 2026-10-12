<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Actions\Downloads\IssueDownloadGrants;
use App\Models\Customer;
use App\Models\DownloadGrant;
use App\Models\Order;
use App\Notifications\DownloadLinksReady;
use App\Notifications\OrderHistoryLink;
use App\Support\BlindIndex;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

/**
 * "What did I buy, and can I download it again?"
 *
 * There are no customer accounts here, which is a feature: nothing to register
 * for, nothing to be breached. The way back in is the address the order was
 * placed with, and the answer only ever arrives by email.
 *
 * That is what makes this safe to leave open. Typing someone else's address
 * tells you nothing — the page's reply is the same either way, and whatever is
 * found goes to their inbox, not to your screen. See §8.7.
 */
class OrderLookupController
{
    /** How long the emailed link stays good. Long enough to read an email. */
    private const LINK_MINUTES = 60;

    public function show(): View
    {
        return view('storefront.orders.find');
    }

    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:191'],
        ]);

        // Lookup only. Customer::forEmail() would create a row for every
        // address anybody typed into this box.
        $customer = Customer::query()
            ->where('email_hash', BlindIndex::email($data['email']))
            ->first();

        if ($customer !== null && $customer->orders()->whereNotNull('paid_at')->exists()) {
            $customer->notify(new OrderHistoryLink($this->historyUrl($customer)));
        }

        Log::channel(config('logging.default'))->info('orders.lookup_requested', [
            'found' => $customer !== null,
        ]);

        // Said the same way whether or not anything was found. A message that
        // changed would turn this box into a way of asking us which addresses
        // have bought from the store.
        return back()->with('notice', [
            'text' => 'If that address has orders with us, a link is on its way to it.',
            'isError' => false,
        ]);
    }

    /** Reached only through the signed link in that email. */
    public function history(Customer $customer): View
    {
        $orders = $customer->orders()
            ->whereNotNull('paid_at')
            ->with(['items', 'downloadGrants'])
            ->latest('paid_at')
            ->get();

        return view('storefront.orders.history', [
            'customer' => $customer,
            'orders' => $orders,
            // Each link is signed on its own, so this page cannot be used to
            // reach an order that is not on it.
            'resendUrl' => fn (Order $order): string => URL::temporarySignedRoute(
                'orders.resend',
                now()->addMinutes(self::LINK_MINUTES),
                ['order' => $order->uuid],
            ),
        ]);
    }

    /**
     * Issues a fresh set of links for one order and emails them.
     *
     * Fresh rather than the originals, because the database holds only their
     * hashes — there is nothing to show, only something to send. See §8.2.
     */
    public function resend(Order $order, IssueDownloadGrants $issue): RedirectResponse
    {
        if (! $order->status->isFulfillable()) {
            return back()->with('notice', [
                'text' => 'Those files are no longer available.',
                'isError' => true,
            ]);
        }

        DB::transaction(function () use ($order): void {
            $order->loadMissing('items');
            DownloadGrant::where('order_id', $order->getKey())->delete();
        });

        $grants = $issue($order->fresh());
        $order->customer->notify(new DownloadLinksReady($order, $grants));

        return back()->with('notice', [
            'text' => "Sent to your inbox. Any older links for order {$order->number} have stopped working.",
            'isError' => false,
        ]);
    }

    private function historyUrl(Customer $customer): string
    {
        return URL::temporarySignedRoute(
            'orders.history',
            now()->addMinutes(self::LINK_MINUTES),
            ['customer' => $customer->uuid],
        );
    }
}
