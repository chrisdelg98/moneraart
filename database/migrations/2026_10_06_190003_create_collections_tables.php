<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collections', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->string('type', 16)->default('manual');
            $table->json('rules')->nullable();
            $table->unsignedBigInteger('cover_image_id')->nullable();
            $table->string('status', 16)->default('draft');

            // A collection only earns a crawlable page once it holds enough
            // content. A nightly job maintains this both ways. See §11.5.
            $table->boolean('is_indexable')->default(false);
            $table->unsignedSmallInteger('min_products_for_index')->default(4);

            $table->integer('position')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
            $table->foreign('cover_image_id')->references('id')->on('product_images')->nullOnDelete();
        });

        Schema::create('collection_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('collection_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('slug');
            $table->string('status', 16)->default('draft');
            $table->boolean('is_machine_translated')->default(false);
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['collection_id', 'locale']);
            $table->unique(['locale', 'slug']);
        });

        Schema::create('collection_product', function (Blueprint $table): void {
            $table->foreignId('collection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->primary(['collection_id', 'product_id']);
            $table->index('product_id');
        });

        /*
         * A bundle delivers the union of its children's files, deduplicated by
         * checksum. Files are never duplicated on disk — a 60-piece bundle is
         * 60 small grant rows, not 60 copied files. See §8.3.
         */
        Schema::create('bundle_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bundle_product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('child_product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);

            $table->unique(['bundle_product_id', 'child_product_id']);
            $table->index('child_product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bundle_items');
        Schema::dropIfExists('collection_product');
        Schema::dropIfExists('collection_translations');
        Schema::dropIfExists('collections');
    }
};
