<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table): void {
            $table->id();

            // Stored uppercase so lookup is a plain indexed equality check
            // rather than a case-insensitive scan.
            $table->string('code', 64)->unique();
            $table->string('description')->nullable();

            $table->string('type', 16);
            // Percent: whole percentage points. Fixed: cents, in `currency`.
            $table->unsignedInteger('value');
            $table->char('currency', 3)->default('USD');

            $table->string('applies_to', 16)->default('all');

            $table->unsignedInteger('min_subtotal_cents')->nullable();
            // The ceiling that stops "50% off" meeting a large cart.
            $table->unsignedInteger('max_discount_cents')->nullable();

            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_limit_per_customer')->nullable();
            // Kept in step with coupon_usages inside the same transaction, so
            // the limit can be enforced under a lock without counting rows.
            $table->unsignedInteger('used_count')->default(0);

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['is_active', 'expires_at']);
        });

        Schema::create('coupon_products', function (Blueprint $table): void {
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            $table->primary(['coupon_id', 'product_id']);
        });

        Schema::create('coupon_collections', function (Blueprint $table): void {
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('collection_id')->constrained()->cascadeOnDelete();

            $table->primary(['coupon_id', 'collection_id']);
        });

        Schema::create('coupon_usages', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            // The per-customer limit is counted on this, so it survives the
            // customer row being erased on request. See §9.
            $table->char('email_hash', 64);
            $table->unsignedInteger('discount_cents');

            $table->timestamp('created_at')->nullable();

            // A concurrent double submit cannot consume two uses of the same
            // coupon on one order. See §14.2.
            $table->unique(['coupon_id', 'order_id']);
            $table->index(['coupon_id', 'email_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_usages');
        Schema::dropIfExists('coupon_collections');
        Schema::dropIfExists('coupon_products');
        Schema::dropIfExists('coupons');
    }
};
