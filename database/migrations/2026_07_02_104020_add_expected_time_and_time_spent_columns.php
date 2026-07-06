<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('questions', 'expected_time')) {
            Schema::table('questions', function (Blueprint $table) {
                $table->unsignedInteger('expected_time')->nullable()->after('is_complete');
            });
        }

        if (!Schema::hasColumn('user_test_answers', 'time_spent')) {
            Schema::table('user_test_answers', function (Blueprint $table) {
                $table->unsignedInteger('time_spent')->default(0)->after('selected_answer');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn('expected_time');
        });

        Schema::table('user_test_answers', function (Blueprint $table) {
            $table->dropColumn('time_spent');
        });
    }
};
