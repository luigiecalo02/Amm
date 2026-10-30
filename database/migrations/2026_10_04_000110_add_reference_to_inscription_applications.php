<?php

use App\Models\InscriptionApplication;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inscription_applications', function (Blueprint $table) {
            $table->string('reference', 20)->nullable()->unique()->after('status');
        });

        $sequences = [];

        InscriptionApplication::query()
            ->whereNull('reference')
            ->whereNotNull('submitted_at')
            ->orderBy('submitted_at')
            ->each(function (InscriptionApplication $application) use (&$sequences): void {
                $year = ($application->submitted_at ?? now())->year;
                $sequences[$year] = ($sequences[$year] ?? 0) + 1;
                $application->forceFill([
                    'reference' => sprintf('AMM-%d-%04d', $year, $sequences[$year]),
                ])->save();
            });
    }

    public function down(): void
    {
        Schema::table('inscription_applications', function (Blueprint $table) {
            $table->dropUnique(['reference']);
            $table->dropColumn('reference');
        });
    }
};
