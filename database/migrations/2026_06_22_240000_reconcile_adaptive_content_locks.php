<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tests')
            ->where('test_type', 'adaptive_full_length')
            ->whereNotNull('content_locked_at')
            ->whereNotExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('assignments')
                ->whereColumn('assignments.test_id', 'tests.id')
                ->where('assignments.status', 'published'))
            ->update(['content_locked_at' => null]);
    }

    public function down(): void
    {
        // Retired raw-table locks cannot be reconstructed safely.
    }
};
