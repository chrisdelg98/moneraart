<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();

            // Encrypted at rest; the HMAC blind index is the only way to find
            // a customer. See §9.
            $table->text('email_encrypted');
            $table->char('email_hash', 64)->unique();
            // Clear text on purpose: enables abuse analytics across a domain
            // without exposing any individual.
            $table->string('email_domain', 64)->nullable();
            $table->text('name_encrypted')->nullable();

            $table->char('locale', 5)->default('en');
            $table->char('country', 2)->nullable();
            $table->boolean('marketing_consent')->default(false);

            $table->timestamp('first_order_at')->nullable();
            $table->timestamp('last_order_at')->nullable();
            $table->unsignedInteger('orders_count')->default(0);
            $table->unsignedBigInteger('lifetime_value_cents')->default(0);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            // Human-facing, from its own sequence — the autoincrement id would
            // leak total order volume.
            $table->string('number', 16)->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();

            $table->string('status', 16)->default('pending');
            $table->char('currency', 3)->default('USD');
            $table->unsignedInteger('subtotal_cents')->default(0);
            $table->unsignedInteger('discount_cents')->default(0);
            $table->unsignedInteger('tax_cents')->default(0);
            $table->unsignedInteger('total_cents')->default(0);

            $table->unsignedBigInteger('coupon_id')->nullable();
            $table->string('coupon_code', 64)->nullable();

            $table->char('email_hash', 64);
            $table->char('ip_hash', 64)->nullable();
            $table->char('user_agent_hash', 64)->nullable();

            $table->timestamp('placed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('refunded_at')->nullable();

            $table->string('manual_review_reason')->nullable();
            $table->text('admin_notes')->nullable();
            $table->json('metadata')->nullable();

            // Which revision of the terms this buyer accepted. A policy edited
            // since the sale is worthless as evidence. See §7.7.2.
            $table->string('terms_version', 16)->nullable();
            $table->timestamp('terms_accepted_at')->nullable();
            $table->char('terms_accepted_ip_hash', 64)->nullable();

            $table->char('locale', 5)->default('en');
            $table->boolean('is_free')->default(false);
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('email_hash');
        });

        // A price snapshot, immutable after creation: later edits to a product
        // must never change what a past customer bought or is entitled to.
        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_type', 16);
            $table->string('title_snapshot');
            $table->string('slug_snapshot');
            $table->unsignedInteger('unit_price_cents');
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->unsignedInteger('discount_cents')->default(0);
            $table->unsignedInteger('total_cents');
            $table->json('file_manifest');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 16)->default('paypal');
            $table->string('mode', 16)->default('sandbox');

            $table->string('provider_order_id', 64)->nullable();
            $table->string('provider_capture_id', 64)->nullable();

            $table->string('status', 24)->default('pending');
            $table->unsignedInteger('amount_cents');
            $table->char('currency', 3);
            $table->integer('fee_cents')->nullable();
            $table->integer('net_cents')->nullable();

            $table->char('payer_email_hash', 64)->nullable();
            $table->string('payer_id', 64)->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamps();

            // The database-level guarantee that a replayed webhook cannot
            // create a second payment. Application logic can have bugs; a
            // unique index cannot. See §6.7.
            $table->unique(['provider', 'provider_capture_id']);
            $table->unique(['provider', 'provider_order_id']);
        });

        // The idempotency ledger. The unique insert is the lock.
        Schema::create('webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 16);
            $table->string('event_id', 64)->unique();
            $table->string('event_type', 64);
            $table->string('resource_type', 64)->nullable();
            $table->string('resource_id', 64)->nullable();
            $table->boolean('signature_verified')->default(false);
            $table->json('payload');
            $table->string('status', 16)->default('received');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('received_at');

            $table->index(['status', 'received_at']);
        });

        Schema::create('refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('provider_refund_id', 64)->unique();
            $table->unsignedInteger('amount_cents');
            $table->string('reason')->nullable();
            $table->string('status', 24);
            $table->foreignId('initiated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('raw_response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('customers');
    }
};
