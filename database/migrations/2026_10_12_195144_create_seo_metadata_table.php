<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hand-written metadata, for the pages where the generated text is wrong.
 *
 * A row here is an exception. Everything without one is described from the
 * product's own words, which is the only way a catalogue stays described as
 * it grows. See §11.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_metadata', function (Blueprint $table): void {
            $table->id();

            $table->morphs('seoable');
            // Each language needs its own title and description; a translated
            // page with English metadata is worse than none. See §4.7.
            $table->char('locale', 5)->default('en');

            $table->string('seo_title', 70)->nullable();
            $table->string('seo_description', 200)->nullable();
            $table->string('canonical_url')->nullable();
            $table->string('robots', 64)->nullable();

            $table->string('og_title')->nullable();
            $table->string('og_description', 200)->nullable();
            $table->string('og_image_path')->nullable();

            $table->timestamps();

            $table->unique(['seoable_type', 'seoable_id', 'locale'], 'seo_metadata_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_metadata');
    }
};
