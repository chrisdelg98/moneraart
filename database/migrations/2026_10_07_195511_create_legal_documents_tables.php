<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versioned and immutable: editing the terms creates a new version rather than
 * overwriting the current one, because in a dispute the question is what this
 * buyer agreed to on this date. See §7.7.2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_documents', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 32)->unique();
            $table->string('slug', 64)->unique();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('legal_document_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('legal_document_id')->constrained()->cascadeOnDelete();
            $table->string('version', 16);
            $table->timestamp('effective_at');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['legal_document_id', 'version']);
        });

        // Both languages live on one version, so publishing an update never
        // leaves a locale on stale text. See §4.7.1.
        Schema::create('legal_document_bodies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('legal_document_version_id')->constrained()->cascadeOnDelete();
            $table->char('locale', 5);
            $table->string('title');
            $table->longText('body');
            $table->timestamps();

            $table->unique(['legal_document_version_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_document_bodies');
        Schema::dropIfExists('legal_document_versions');
        Schema::dropIfExists('legal_documents');
    }
};
