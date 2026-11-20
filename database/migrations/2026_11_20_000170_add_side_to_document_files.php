<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('document_files', 'side')) {
            return;
        }

        Schema::table('document_files', function (Blueprint $table): void {
            $table->string('side', 16)->nullable()->after('filename');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('document_files', 'side')) {
            return;
        }

        Schema::table('document_files', function (Blueprint $table): void {
            $table->dropColumn('side');
        });
    }
};
