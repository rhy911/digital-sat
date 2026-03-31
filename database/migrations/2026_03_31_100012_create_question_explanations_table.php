<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tạo bảng question_explanations — giải thích chi tiết từng đáp án.
     * Giống Practice Test review của CollegeBoard.
     * Quan hệ 1:1 với questions.
     */
    public function up(): void
    {
        Schema::create('question_explanations', function (Blueprint $table) {
            $table->id();

            // Quan hệ 1:1 với questions (unique FK)
            $table->foreignId('question_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();

            $table->text('explanation')
                ->comment('Tại sao đáp án đúng là đúng (HTML)');

            $table->text('rationale_a')
                ->nullable()
                ->comment('Giải thích cho choice A');

            $table->text('rationale_b')
                ->nullable()
                ->comment('Giải thích cho choice B');

            $table->text('rationale_c')
                ->nullable()
                ->comment('Giải thích cho choice C');

            $table->text('rationale_d')
                ->nullable()
                ->comment('Giải thích cho choice D');

            $table->text('strategy_tip')
                ->nullable()
                ->comment('Mẹo giải nhanh hoặc phương pháp tiếp cận');

            $table->text('common_mistakes')
                ->nullable()
                ->comment('Lỗi sai thường gặp');

            $table->timestamps();
        });
    }

    /**
     * Rollback: xóa bảng question_explanations.
     */
    public function down(): void
    {
        Schema::dropIfExists('question_explanations');
    }
};
