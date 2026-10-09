<?php

declare(strict_types=1);

use App\Jobs\CleanupExpiredDownloads;
use App\Jobs\ReconcilePendingWebhooks;
use App\Jobs\ReconcileStuckOrders;
use Illuminate\Support\Facades\Schedule;

/*
 * Without these, a customer whose capture call and webhook both failed has paid
 * and received nothing, and nothing recovers it. See §7.5 and §17.
 */
Schedule::job(new ReconcileStuckOrders)->everyTenMinutes()->withoutOverlapping();
Schedule::job(new ReconcilePendingWebhooks)->everyFiveMinutes()->withoutOverlapping();

/*
 * Housekeeping, not recovery. Nightly and off-peak, because it deletes in
 * chunks and there is no hurry. See §8.5 and §9.5.
 */
Schedule::job(new CleanupExpiredDownloads)->dailyAt('03:20')->withoutOverlapping();
