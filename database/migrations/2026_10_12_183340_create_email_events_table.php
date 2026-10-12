<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What happened to each email we tried to send.
 *
 * Without it a message that SMTP rejected disappears into failed_jobs, which
 * nobody reads. Every purchase here depends on an email arriving, so a send
 * that did not happen is the same to the customer as never having been sold
 * anything. See §15 and §19.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_events', function (Blueprint $table): void {
            $table->id();

            // Hashed like everywhere else. The domain stays clear so a run of
            // failures at one provider is visible without reading addresses.
            $table->char('email_hash', 64);
            $table->string('email_domain', 64)->nullable();

            // The notification class, short: DownloadLinksReady, OrderConfirmed.
            $table->string('type', 64);
            $table->string('subject')->nullable();

            $table->string('status', 16);
            $table->text('error')->nullable();

            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamp('created_at');

            $table->index(['status', 'created_at']);
            $table->index(['email_hash', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_events');
    }
};
