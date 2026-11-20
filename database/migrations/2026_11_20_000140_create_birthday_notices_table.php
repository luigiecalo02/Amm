<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('birthday_notices', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUlid('person_id')->constrained('people')->cascadeOnDelete();
            $table->date('occurs_on');
            $table->timestamps();

            $table->unique(
                ['organization_id', 'person_id', 'occurs_on'],
                'birthday_notice_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('birthday_notices');
    }
};
