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
        Schema::table('assignments', function (Blueprint $table) {
            if (!Schema::hasColumn('assignments', 'target_audience')) {
                $table->string('target_audience', 20)->default('all')->after('assign_type');
            }
            if (!Schema::hasColumn('assignments', 'clock_mode')) {
                $table->string('clock_mode', 20)->default('chained')->after('target_audience');
            }
        });

        Schema::table('assignment_recipients', function (Blueprint $table) {
            if (!Schema::hasColumn('assignment_recipients', 'extra_attempts')) {
                $table->unsignedSmallInteger('extra_attempts')->default(0)->after('status');
            }
        });

        Schema::table('user_tests', function (Blueprint $table) {
            if (!Schema::hasColumn('user_tests', 'teacher_feedback')) {
                $table->text('teacher_feedback')->nullable()->after('completed_at');
            }
            if (!Schema::hasColumn('user_tests', 'feedback_at')) {
                $table->timestamp('feedback_at')->nullable()->after('teacher_feedback');
            }
            if (!Schema::hasColumn('user_tests', 'feedback_by')) {
                $table->foreignId('feedback_by')->nullable()->after('feedback_at')->constrained('users')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_tests', function (Blueprint $table) {
            if (Schema::hasColumn('user_tests', 'feedback_by')) {
                $table->dropForeign(['feedback_by']);
                $table->dropColumn('feedback_by');
            }
            if (Schema::hasColumn('user_tests', 'feedback_at')) {
                $table->dropColumn('feedback_at');
            }
            if (Schema::hasColumn('user_tests', 'teacher_feedback')) {
                $table->dropColumn('teacher_feedback');
            }
        });

        Schema::table('assignment_recipients', function (Blueprint $table) {
            if (Schema::hasColumn('assignment_recipients', 'extra_attempts')) {
                $table->dropColumn('extra_attempts');
            }
        });

        Schema::table('assignments', function (Blueprint $table) {
            if (Schema::hasColumn('assignments', 'clock_mode')) {
                $table->dropColumn('clock_mode');
            }
            if (Schema::hasColumn('assignments', 'target_audience')) {
                $table->dropColumn('target_audience');
            }
        });
    }
};
