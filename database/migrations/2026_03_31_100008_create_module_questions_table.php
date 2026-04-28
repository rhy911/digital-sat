<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tạo bảng module_questions — bảng trung gian (pivot).
     * 1 câu hỏi có thể xuất hiện ở nhiều module khác nhau.
     * position quyết định thứ tự hiển thị cho thí sinh.
     */
    public function up(): void
    {
        Schema::create('module_questions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('module_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('question_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->integer('position')
                ->comment('Thứ tự hiển thị trong module này');

            $table->timestamp('created_at')
                ->nullable();

            // Composite unique indexes
            $table->unique(['module_id', 'question_id'], 'uq_module_question');
            $table->unique(['module_id', 'position'], 'uq_module_position');
        });
    }

    /**
     * Rollback: xóa bảng module_questions.
     */
    public function down(): void
    {
        Schema::dropIfExists('module_questions');
    }
};
