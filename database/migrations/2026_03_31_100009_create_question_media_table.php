<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tạo bảng question_media — hình ảnh, biểu đồ, bảng số liệu.
     * Math section dùng nhiều graph/table.
     */
    public function up(): void
    {
        Schema::create('question_media', function (Blueprint $table) {
            $table->id();

            $table->foreignId('question_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->enum('media_type', ['image', 'graph', 'chart', 'table', 'formula', 'equation'])
                ->comment('image | graph | chart | table | formula | equation');

            $table->string('file_path', 500);

            $table->text('alt_text')
                ->nullable()
                ->comment('Mô tả cho accessibility — Bluebook hỗ trợ screen reader');

            $table->enum('position', ['passage', 'stem', 'choice'])
                ->comment('passage | stem | choice');

            $table->integer('order')
                ->default(1);

            $table->integer('width')
                ->nullable()
                ->comment('Pixel width để render đúng kích thước');

            $table->integer('height')
                ->nullable()
                ->comment('Pixel height');

            $table->timestamps();
        });
    }

    /**
     * Rollback: xóa bảng question_media.
     */
    public function down(): void
    {
        Schema::dropIfExists('question_media');
    }
};
