<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Models\Order;
use Illuminate\View\View;

class OrderSuccessController
{
    public function __invoke(Order $order): View
    {
        $order->load(['items', 'payment']);

        return view('storefront.order-success', ['order' => $order]);
    }
}
