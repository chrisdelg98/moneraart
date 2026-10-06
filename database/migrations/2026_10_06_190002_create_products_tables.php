<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->string('type', 16)->default('single');

            // Money as integer minor units. Never float. See §5.3.
            $table->unsignedInteger('price_cents');
            $table->unsignedInteger('sale_price_cents')->nullable();
            $table->timestamp('sale_starts_at')->nullable();
            $table->timestamp('sale_ends_at')->nullable();
            $table->char('currency', 3)->default('USD');

            $table->string('status', 16)->default('draft');
            $table->timestamp('published_at')->nullable();

            $table->unsignedBigInteger('cover_image_id')->nullable();

            // Disclosed on every product page. See §7.7 and the legal drafts.
            $table->boolean('is_ai_generated')->default(true);
            $table->string('ai_tools_note')->nullable();
            $table->string('license_type', 16)->default('personal');

            // Denormalised for listing cards — avoids a count per card.
            $table->unsignedSmallInteger('file_count')->default(0);
            $table->unsignedBigInteger('total_bytes')->default(0);
            $table->unsignedInteger('sales_count')->default(0);

            $table->integer('position')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
            $table->index(['type', 'status']);
        });

        Schema::create('product_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);

            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->mediumText('description')->nullable();
            $table->string('slug');

            $table->string('status', 16)->default('draft');
            $table->boolean('is_machine_translated')->default(false);
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'locale']);
            // The critical one: slug lookup happens on every product page request.
            $table->unique(['locale', 'slug']);
            $table->index(['locale', 'status']);
        });

        // Sellable payload. Private storage only, never web-reachable.
        Schema::create('product_files', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 16)->default('private');
            $table->string('path');
            $table->string('original_filename');
            $table->string('display_name')->nullable();
            $table->string('mime_type', 128);
            $table->unsignedBigInteger('size_bytes');
            $table->char('checksum_sha256', 64);
            $table->string('format', 8);
            $table->string('ratio', 16)->nullable();
            $table->string('print_size', 32)->nullable();
            $table->unsignedSmallInteger('dpi')->nullable();
            $table->unsignedInteger('width_px')->nullable();
            $table->unsignedInteger('height_px')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'position']);
            // Catches an accidental re-upload of the same file.
            $table->unique(['product_id', 'checksum_sha256']);
        });

        // Public previews. Never the sellable file. Watermarked at large sizes.
        Schema::create('product_images', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 16)->default('public');
            $table->string('path_original');
            // Alt text is never searched, so JSON is correct here where a
            // translation table would be overhead. See §4.7.1.
            $table->json('alt_text')->nullable();
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->char('dominant_color', 7)->nullable();
            $table->string('blurhash', 64)->nullable();
            $table->boolean('is_cover')->default(false);
            $table->boolean('is_mockup')->default(false);
            $table->boolean('watermarked')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->json('variants')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'position']);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->foreign('cover_image_id')->references('id')->on('product_images')->nullOnDelete();
        });

        Schema::create('product_attribute_value', function (Blueprint $table): void {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_value_id')->constrained()->cascadeOnDelete();
            $table->primary(['product_id', 'attribute_value_id']);
            $table->index('attribute_value_id');
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $t) => $t->dropForeign(['cover_image_id']));
        Schema::dropIfExists('product_attribute_value');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('product_files');
        Schema::dropIfExists('product_translations');
        Schema::dropIfExists('products');
    }
};
