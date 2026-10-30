<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inscription_links', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('token', 64)->unique();
            $table->string('name')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('uses_count')->default(0);
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('inscription_applications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('inscription_link_id')->constrained('inscription_links')->cascadeOnDelete();
            $table->foreignUlid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUlid('person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->foreignUlid('access_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('draft')->index();
            $table->string('email')->nullable();
            $table->json('extracted_data')->nullable();
            $table->json('form_draft')->nullable();
            $table->json('uploads')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('access_granted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inscription_applications');
        Schema::dropIfExists('inscription_links');
    }
};
