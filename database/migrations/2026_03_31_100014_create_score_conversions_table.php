<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tạo bảng score_conversions — bảng quy đổi điểm.
     * Mỗi đề có equating table riêng (không dùng chung).
     * Cùng raw_score + M2 hard → scaled cao hơn M2 easy.
     */
    public function up(): void
    {
        Schema::create('score_conversions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('test_id')
                ->nullable()
                ->constrained()
                ->cascadeOnDelete()
                ->comment('Mỗi đề thi có bảng quy đổi riêng');

            $table->enum('section_type', ['reading_writing', 'math'])
                ->comment('reading_writing | math');

            $table->enum('m2_difficulty', ['easy', 'hard'])
                ->comment('easy | hard');

            $table->integer('raw_score')
                ->comment('Tổng câu đúng M1 + M2');

            $table->integer('scaled_score')
                ->comment('200–800');

            $table->timestamps();

            // Composite unique index
            $table->unique(
                ['test_id', 'section_type', 'm2_difficulty', 'raw_score'],
                'uq_score_conversion'
            );
        });
    }

    /**
     * Rollback: xóa bảng score_conversions.
     */
    public function down(): void
    {
        Schema::dropIfExists('score_conversions');
    }
};
