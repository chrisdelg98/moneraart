<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lets the database do the searching.
 *
 * The shop filtered in the browser, which only worked because every product
 * was on the page — and that is what made pagination impossible. With this,
 * the server can answer "which pieces mention retro" over the whole catalogue
 * in a few milliseconds, and hand back one page of them. See §2b.
 */
return new class extends Migration
{
    public function up(): void
    {
        // SQLite, which the test suite runs on, has no FULLTEXT. The search
        // falls back to LIKE there, so the absence of the index is handled
        // rather than fatal.
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('product_translations', function (Blueprint $table): void {
            $table->fullText(['title', 'subtitle', 'description'], 'product_translations_fulltext');
        });
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('product_translations', function (Blueprint $table): void {
            $table->dropFullText('product_translations_fulltext');
        });
    }
};
