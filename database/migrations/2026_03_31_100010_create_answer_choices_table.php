<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tạo bảng answer_choices — đáp án trắc nghiệm A/B/C/D.
     * Hỗ trợ HTML + MathJax/KaTeX cho công thức toán.
     */
    public function up(): void
    {
        Schema::create('answer_choices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('question_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('label', 5)
                ->comment('A | B | C | D');

            $table->text('content')
                ->comment('HTML — hỗ trợ MathJax/KaTeX cho công thức');

            $table->boolean('is_correct')
                ->default(false);

            $table->integer('order')
                ->default(1);

            $table->timestamps();
        });
    }

    /**
     * Rollback: xóa bảng answer_choices.
     */
    public function down(): void
    {
        Schema::dropIfExists('answer_choices');
    }
};
