<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tạo bảng passages — tách riêng đoạn văn để tái sử dụng.
     * Thêm genre để đảm bảo đa dạng nội dung khi tổ hợp đề.
     */
    public function up(): void
    {
        Schema::create('passages', function (Blueprint $table) {
            $table->id();

            $table->text('content')
                ->comment('Nội dung đoạn văn (HTML/Markdown)');

            $table->enum('passage_type', ['single', 'paired'])
                ->default('single')
                ->comment('single | paired');

            $table->integer('word_count')
                ->nullable()
                ->comment('Để cân đối độ dài khi chọn passage');

            $table->string('source_title', 500)
                ->nullable()
                ->comment('Tiêu đề tác phẩm gốc');

            $table->string('source_author', 255)
                ->nullable();

            $table->integer('source_year')
                ->nullable();

            $table->enum('genre', [
                'literary_narrative',
                'social_science',
                'natural_science',
                'humanities',
            ])->nullable()
                ->comment('literary_narrative | social_science | natural_science | humanities');

            $table->timestamps();
        });
    }

    /**
     * Rollback: xóa bảng passages.
     */
    public function down(): void
    {
        Schema::dropIfExists('passages');
    }
};
