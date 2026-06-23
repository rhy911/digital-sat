<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('score_conversion_sets')
            ->join('tests', 'tests.id', '=', 'score_conversion_sets.test_id')
            ->where('tests.test_type', 'adaptive_full_length')
            ->where('score_conversion_sets.status', 'approved')
            ->update(['score_conversion_sets.status' => 'retired']);
    }

    public function down(): void
    {
        // Retired raw tables cannot be safely re-approved automatically.
    }
};
