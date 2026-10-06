<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One generic taxonomy instead of six bespoke columns. See §4.1.
 *
 * `ratio`, `orientation` and `color` are computed from the uploaded files and
 * images, never typed by the administrator — only `style`, `room` and `theme`
 * are chosen by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attributes', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 32)->unique();
            $table->string('label', 64);
            $table->boolean('is_filterable')->default(true);
            $table->boolean('is_seo_landing')->default(false);
            $table->boolean('is_computed')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('attribute_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->string('value', 64);
            $table->unsignedSmallInteger('position')->default(0);
            $table->unsignedInteger('products_count')->default(0);
            $table->timestamps();

            $table->unique(['attribute_id', 'value']);
        });

        Schema::create('attribute_value_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attribute_value_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('label', 96);
            $table->string('slug', 96);
            $table->timestamps();

            $table->unique(['attribute_value_id', 'locale']);
            $table->unique(['locale', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_value_translations');
        Schema::dropIfExists('attribute_values');
        Schema::dropIfExists('attributes');
    }
};
