<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_test_module_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_test_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained('modules')->restrictOnDelete();
            $table->foreignId('issued_next_module_id')->nullable()->constrained('modules')->nullOnDelete();
            $table->json('result');
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->unique(['user_test_id', 'module_id'], 'attempt_module_submission_unique');
        });

        DB::table('user_tests')
            ->where('status', 'in_progress')
            ->whereNull('current_module_id')
            ->orderBy('id')
            ->chunkById(100, function ($attempts) {
                foreach ($attempts as $attempt) {
                    $firstModuleId = DB::table('modules')
                        ->join('section_modules', 'section_modules.module_id', '=', 'modules.id')
                        ->join('sections', 'sections.id', '=', 'section_modules.section_id')
                        ->where('sections.test_id', $attempt->test_id)
                        ->orderBy('sections.order')
                        ->orderBy('modules.order')
                        ->orderBy('modules.id')
                        ->value('modules.id');

                    if ($firstModuleId) {
                        DB::table('user_tests')
                            ->where('id', $attempt->id)
                            ->whereNull('current_module_id')
                            ->update([
                                'current_module_id' => $firstModuleId,
                                'current_module_started_at' => null,
                                'current_module_elapsed_seconds' => 0,
                            ]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_test_module_submissions');
    }
};
