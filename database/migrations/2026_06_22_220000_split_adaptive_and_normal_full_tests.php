<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE tests MODIFY test_type ENUM('full_length','adaptive_full_length','section_only','module_only','short_test','custom_test') NOT NULL DEFAULT 'full_length'");
        DB::statement("ALTER TABLE score_conversions MODIFY m2_difficulty ENUM('standard','easy','hard') NOT NULL DEFAULT 'standard'");

        Schema::table('user_tests', function (Blueprint $table) {
            $table->unsignedSmallInteger('score_reading_writing_lower')->nullable()->after('score_reading_writing');
            $table->unsignedSmallInteger('score_reading_writing_upper')->nullable()->after('score_reading_writing_lower');
            $table->unsignedSmallInteger('score_math_lower')->nullable()->after('score_math');
            $table->unsignedSmallInteger('score_math_upper')->nullable()->after('score_math_lower');
            $table->unsignedSmallInteger('total_score_lower')->nullable()->after('total_score');
            $table->unsignedSmallInteger('total_score_upper')->nullable()->after('total_score_lower');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->enum('irt_calibration_status', ['provisional', 'calibrated'])->default('provisional')->after('irt_c');
            $table->string('irt_calibration_version', 64)->nullable()->after('irt_calibration_status');
        });

        Schema::create('user_test_score_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_test_id')->constrained()->cascadeOnDelete();
            $table->uuid('run_id');
            $table->string('reason', 255);
            $table->json('previous_score');
            $table->json('revised_score');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['user_test_id', 'run_id']);
        });

        $this->classifyExistingFullTests();
    }

    private function classifyExistingFullTests(): void
    {
        DB::table('tests')->where('test_type', 'full_length')->orderBy('id')->each(function ($test) {
            $sections = DB::table('sections')->where('test_id', $test->id)->whereNull('deleted_at')->get();
            if ($sections->count() !== 2) {
                DB::table('tests')->where('id', $test->id)->update(['status' => 'draft']);

                return;
            }

            $adaptive = true;
            $normal = true;
            foreach ($sections as $section) {
                $modules = DB::table('modules')
                    ->join('section_modules', 'section_modules.module_id', '=', 'modules.id')
                    ->where('section_modules.section_id', $section->id)
                    ->whereNull('modules.deleted_at')
                    ->get(['modules.module_number', 'modules.difficulty_level']);
                $hasM1 = $modules->where('module_number', 1)->count() === 1;
                $m2 = $modules->where('module_number', 2);
                $adaptive = $adaptive && $hasM1 && $m2->count() === 2
                    && $m2->pluck('difficulty_level')->sort()->values()->all() === ['easy', 'hard'];
                $normal = $normal && $hasM1 && $m2->count() === 1;
            }

            if ($adaptive) {
                DB::table('tests')->where('id', $test->id)->update(['test_type' => 'adaptive_full_length']);
            } elseif (! $normal) {
                DB::table('tests')->where('id', $test->id)->update(['status' => 'draft']);
            }
        });
    }

    public function down(): void
    {
        DB::table('tests')->where('test_type', 'adaptive_full_length')->update(['test_type' => 'full_length']);
        Schema::dropIfExists('user_test_score_revisions');
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn(['irt_calibration_status', 'irt_calibration_version']);
        });
        Schema::table('user_tests', function (Blueprint $table) {
            $table->dropColumn([
                'score_reading_writing_lower', 'score_reading_writing_upper',
                'score_math_lower', 'score_math_upper',
                'total_score_lower', 'total_score_upper',
            ]);
        });
        DB::table('score_conversions')->where('m2_difficulty', 'standard')->update(['m2_difficulty' => 'easy']);
        DB::statement("ALTER TABLE score_conversions MODIFY m2_difficulty ENUM('easy','hard') NOT NULL");
        DB::statement("ALTER TABLE tests MODIFY test_type ENUM('full_length','section_only','module_only','short_test','custom_test') NOT NULL DEFAULT 'full_length'");
    }
};
