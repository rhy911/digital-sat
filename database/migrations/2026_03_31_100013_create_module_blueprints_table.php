<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tạo bảng module_blueprints — bản thiết kế phân bổ câu hỏi.
     * Đảm bảo phân bổ câu hỏi đúng theo quy định CollegeBoard.
     * VD: R&W M1: information_and_ideas 12-14 câu, craft_and_structure 8-10 câu, ...
     */
    public function up(): void
    {
        Schema::create('module_blueprints', function (Blueprint $table) {
            $table->id();

            $table->enum('section_type', ['reading_writing', 'math'])
                ->comment('reading_writing | math');

            $table->integer('module_number')
                ->comment('1 | 2');

            $table->enum('difficulty_level', ['standard', 'easy', 'hard'])
                ->comment('standard | easy | hard');

            $table->string('skill_domain', 50);

            $table->integer('min_questions')
                ->comment('Số câu tối thiểu domain này');

            $table->integer('max_questions')
                ->comment('Số câu tối đa');

            $table->timestamps();
        });
    }

    /**
     * Rollback: xóa bảng module_blueprints.
     */
    public function down(): void
    {
        Schema::dropIfExists('module_blueprints');
    }
};
