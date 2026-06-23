<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_tests', function (Blueprint $table) {
            $table->unsignedBigInteger('active_practice_attempt')
                ->nullable()
                ->virtualAs("CASE WHEN status = 'in_progress' AND assignment_id IS NULL THEN user_id ELSE NULL END")
                ->after('status');

            $table->unique(['test_id', 'active_practice_attempt'], 'uq_user_test_active_practice');
        });
    }

    public function down(): void
    {
        Schema::table('user_tests', function (Blueprint $table) {
            $table->dropUnique('uq_user_test_active_practice');
            $table->dropColumn('active_practice_attempt');
        });
    }
};
