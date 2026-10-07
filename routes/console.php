<?php

declare(strict_types=1);

use App\Jobs\ReconcilePendingWebhooks;
use App\Jobs\ReconcileStuckOrders;
use Illuminate\Support\Facades\Schedule;

/*
 * Without these, a customer whose capture call and webhook both failed has paid
 * and received nothing, and nothing recovers it. See §7.5 and §17.
 */
Schedule::job(new ReconcileStuckOrders)->everyTenMinutes()->withoutOverlapping();
Schedule::job(new ReconcilePendingWebhooks)->everyFiveMinutes()->withoutOverlapping();
