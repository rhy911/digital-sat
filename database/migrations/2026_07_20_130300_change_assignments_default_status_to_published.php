<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Update any existing draft assignments to published
        DB::table('assignments')->where('status', 'draft')->update([
            'status' => 'published',
            'published_at' => now(),
        ]);

        // 2. Change default value of status column in assignments table
        Schema::table('assignments', function (Blueprint $table) {
            $table->string('status', 20)->default('published')->change();
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->string('status', 20)->default('draft')->change();
        });
    }
};
