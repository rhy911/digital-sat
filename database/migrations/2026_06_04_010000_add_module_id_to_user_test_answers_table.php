<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('user_test_answers', 'module_id')) {
            Schema::table('user_test_answers', function (Blueprint $table) {
                $table->foreignId('module_id')
                    ->nullable()
                    ->after('user_test_id')
                    ->constrained('modules')
                    ->nullOnDelete();
            });
        }

        $this->backfillUnambiguousModuleIds();

        if (! $this->indexExists('user_test_answers', 'uq_user_test_module_question')) {
            Schema::table('user_test_answers', function (Blueprint $table) {
                $table->unique(['user_test_id', 'module_id', 'question_id'], 'uq_user_test_module_question');
            });
        }

        if ($this->indexExists('user_test_answers', 'user_test_answers_user_test_id_question_id_unique')) {
            Schema::table('user_test_answers', function (Blueprint $table) {
                $table->dropUnique('user_test_answers_user_test_id_question_id_unique');
            });
        }
    }

    public function down(): void
    {
        if (! $this->indexExists('user_test_answers', 'user_test_answers_user_test_id_question_id_unique')) {
            Schema::table('user_test_answers', function (Blueprint $table) {
                $table->unique(['user_test_id', 'question_id']);
            });
        }

        if ($this->indexExists('user_test_answers', 'uq_user_test_module_question')) {
            Schema::table('user_test_answers', function (Blueprint $table) {
                $table->dropUnique('uq_user_test_module_question');
            });
        }

        Schema::table('user_test_answers', function (Blueprint $table) {
            if (Schema::hasColumn('user_test_answers', 'module_id')) {
                $table->dropConstrainedForeignId('module_id');
            }
        });
    }

    private function backfillUnambiguousModuleIds(): void
    {
        DB::table('user_test_answers')
            ->join('user_tests', 'user_test_answers.user_test_id', '=', 'user_tests.id')
            ->select([
                'user_test_answers.id',
                'user_test_answers.question_id',
                'user_tests.test_id',
            ])
            ->orderBy('user_test_answers.id')
            ->chunkById(100, function ($answers) {
                foreach ($answers as $answer) {
                    $moduleIds = DB::table('module_questions')
                        ->join('section_modules', 'module_questions.module_id', '=', 'section_modules.module_id')
                        ->join('sections', 'section_modules.section_id', '=', 'sections.id')
                        ->where('module_questions.question_id', $answer->question_id)
                        ->where('sections.test_id', $answer->test_id)
                        ->distinct()
                        ->pluck('module_questions.module_id');

                    if ($moduleIds->count() === 1) {
                        DB::table('user_test_answers')
                            ->where('id', $answer->id)
                            ->update(['module_id' => $moduleIds->first()]);
                    }
                }
            }, 'user_test_answers.id', 'id');
    }

    private function indexExists(string $table, string $index): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};
