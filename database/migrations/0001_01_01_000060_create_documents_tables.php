<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_types', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('entity_type')->index();
            $table->string('description')->nullable();
            $table->string('icon')->nullable();
            $table->string('color', 20)->nullable();
            $table->boolean('requires_expiration')->default(false);
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('document_type_id')->constrained('document_types')->restrictOnDelete();
            $table->foreignUlid('person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->foreignUlid('motorcycle_id')->nullable()->constrained('motorcycles')->nullOnDelete();
            $table->string('document_number')->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiration_date')->nullable()->index();
            $table->string('status')->default('pending_review')->index();
            $table->json('metadata')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignUlid('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['person_id', 'document_type_id']);
            $table->index(['motorcycle_id', 'document_type_id']);
        });

        Schema::create('document_files', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('document_id')->constrained()->cascadeOnDelete();
            $table->string('disk');
            $table->string('path');
            $table->string('filename');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->string('checksum', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['document_id', 'created_at']);
        });

        Schema::create('document_scans', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('document_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('document_type_id')->constrained('document_types')->restrictOnDelete();
            $table->string('original_file');
            $table->string('status')->default('pending')->index();
            $table->string('provider')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('document_extractions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('document_scan_id')->constrained('document_scans')->cascadeOnDelete();
            $table->longText('raw_text')->nullable();
            $table->json('extracted_data')->nullable();
            $table->decimal('confidence', 5, 2)->nullable();
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_extractions');
        Schema::dropIfExists('document_scans');
        Schema::dropIfExists('document_files');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('document_types');
    }
};
