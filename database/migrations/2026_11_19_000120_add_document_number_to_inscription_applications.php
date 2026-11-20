<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inscription_applications', function (Blueprint $table): void {
            $table->string('document_number', 40)->nullable()->after('email');
        });

        $applications = DB::table('inscription_applications')->get();

        foreach ($applications as $application) {
            $draft = is_string($application->form_draft)
                ? json_decode($application->form_draft, true)
                : (array) $application->form_draft;
            $fromPerson = $application->person_id
                ? DB::table('people')->where('id', $application->person_id)->value('document_number')
                : null;
            $digits = preg_replace('/\D+/', '', (string) ($fromPerson ?: ($draft['document_number'] ?? ''))) ?? '';

            if ($digits !== '') {
                DB::table('inscription_applications')->where('id', $application->id)->update([
                    'document_number' => $digits,
                ]);
            }
        }

        $groups = DB::table('inscription_applications')
            ->whereNotNull('document_number')
            ->select('organization_id', 'document_number')
            ->groupBy('organization_id', 'document_number')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($groups as $group) {
            $rows = DB::table('inscription_applications')
                ->where('organization_id', $group->organization_id)
                ->where('document_number', $group->document_number)
                ->orderByRaw("CASE status WHEN 'access_granted' THEN 0 WHEN 'verified' THEN 1 WHEN 'submitted' THEN 2 ELSE 3 END")
                ->orderByDesc('updated_at')
                ->get();

            foreach ($rows->skip(1) as $extra) {
                if (in_array($extra->status, ['draft', 'ready_for_form'], true)) {
                    DB::table('inscription_applications')->where('id', $extra->id)->delete();

                    continue;
                }

                DB::table('inscription_applications')->where('id', $extra->id)->update([
                    'document_number' => null,
                ]);
            }
        }

        Schema::table('inscription_applications', function (Blueprint $table): void {
            $table->unique(['organization_id', 'document_number']);
        });
    }

    public function down(): void
    {
        Schema::table('inscription_applications', function (Blueprint $table): void {
            $table->dropUnique(['organization_id', 'document_number']);
            $table->dropColumn('document_number');
        });
    }
};
