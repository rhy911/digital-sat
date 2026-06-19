<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::update('
            UPDATE questions q
            JOIN module_questions mq ON q.id = mq.question_id
            JOIN modules m ON mq.module_id = m.id
            SET q.created_by = m.created_by
            WHERE q.created_by IS NULL AND m.created_by IS NOT NULL
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // One-way data migration, no-op down
    }
};
