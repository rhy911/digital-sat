<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tạo bảng spr_correct_answers — đáp án SPR (Student Produced Response).
     * Bluebook SPR chấp nhận nhiều dạng đáp án tương đương.
     * VD: 5/2, 2.5, 2.50, 10/4 đều hợp lệ cho cùng một câu hỏi.
     */
    public function up(): void
    {
        Schema::create('spr_correct_answers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('question_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('answer', 100)
                ->comment('Một dạng đáp án hợp lệ');

            $table->enum('answer_type', ['exact', 'range', 'fraction_equivalent'])
                ->default('exact')
                ->comment('exact | range | fraction_equivalent');

            $table->decimal('tolerance', 10, 4)
                ->nullable()
                ->comment('Sai số cho phép nếu answer_type = range');

            $table->timestamp('created_at')
                ->nullable();
        });
    }

    /**
     * Rollback: xóa bảng spr_correct_answers.
     */
    public function down(): void
    {
        Schema::dropIfExists('spr_correct_answers');
    }
};
