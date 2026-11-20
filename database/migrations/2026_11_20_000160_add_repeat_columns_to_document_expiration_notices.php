<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_expiration_notices', function (Blueprint $table): void {
            $table->date('last_sent_on')->nullable()->after('expiration_date');
            $table->timestamp('source_updated_at')->nullable()->after('last_sent_on');
        });
    }

    public function down(): void
    {
        Schema::table('document_expiration_notices', function (Blueprint $table): void {
            $table->dropColumn(['last_sent_on', 'source_updated_at']);
        });
    }
};
