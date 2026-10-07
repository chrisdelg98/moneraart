<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('download_grants', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_file_id')->constrained()->cascadeOnDelete();

            // Only the hash is stored. A database dump yields no usable links —
            // the same reasoning that applies to password hashes. See §8.2.
            $table->char('token_hash', 64)->unique();

            $table->timestamp('expires_at');
            $table->unsignedSmallInteger('max_downloads')->default(5);
            $table->unsignedSmallInteger('download_count')->default(0);
            $table->timestamp('first_downloaded_at')->nullable();
            $table->timestamp('last_downloaded_at')->nullable();

            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('revoke_reason')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'expires_at']);
        });

        // Append-only. This is also the evidence that wins a payment dispute,
        // since PayPal's seller protection does not cover digital goods.
        Schema::create('download_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('download_grant_id')->constrained()->cascadeOnDelete();
            $table->char('ip_hash', 64)->nullable();
            $table->char('user_agent_hash', 64)->nullable();
            $table->char('country', 2)->nullable();
            $table->unsignedBigInteger('bytes_sent')->nullable();
            $table->string('range_header', 64)->nullable();
            $table->string('status', 16);
            $table->timestamp('created_at');

            $table->index(['download_grant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('download_logs');
        Schema::dropIfExists('download_grants');
    }
};
