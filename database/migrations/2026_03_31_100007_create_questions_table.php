<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tạo bảng questions — ngân hàng câu hỏi chung (pool).
     * Câu hỏi KHÔNG gắn cứng vào module, liên kết qua bảng module_questions.
     */
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('passage_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete()
                ->comment('NULL nếu standalone (Math thường)');

            $table->foreignId('paired_passage_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete()
                ->comment('NOT NULL nếu là cross-text question');

            $table->integer('question_number')
                ->comment('Thứ tự mặc định, có thể override trong module_questions');

            $table->text('stem')
                ->comment('Nội dung câu hỏi (HTML)');

            $table->enum('question_type', ['multiple_choice', 'student_produced_response'])
                ->default('multiple_choice')
                ->comment('multiple_choice | student_produced_response');

            $table->enum('difficulty', ['easy', 'medium', 'hard'])
                ->default('medium')
                ->comment('easy | medium | hard');

            $table->enum('section_type', ['reading_writing', 'math'])
                ->comment('Thuộc section nào');

            $table->string('skill_domain', 50)
                ->comment('VD: information_and_ideas, algebra, advanced_math, ...');

            $table->string('skill_subdomain', 100)
                ->nullable()
                ->comment('Chi tiết hơn domain, VD: linear_equations_in_one_variable');

            $table->string('spr_hint', 255)
                ->nullable()
                ->comment('Gợi ý cho SPR, VD: Enter a fraction or decimal');

            $table->boolean('calculator_allowed')
                ->default(true)
                ->comment('Math M1: một số câu không cho dùng calc');

            $table->string('external_id', 100)
                ->nullable()
                ->comment('CollegeBoard question ID nếu có');

            $table->timestamps();
        });
    }

    /**
     * Rollback: xóa bảng questions.
     */
    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
