<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tạo bảng paired_passages — cross-text: 2 đoạn văn song song
     * cho dạng câu hỏi so sánh.
     */
    public function up(): void
    {
        Schema::create('paired_passages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('passage_a_id')
                ->constrained('passages')
                ->cascadeOnDelete();

            $table->foreignId('passage_b_id')
                ->constrained('passages')
                ->cascadeOnDelete();

            $table->enum('relationship', ['contrasting', 'complementary', 'cause_effect'])
                ->nullable()
                ->comment('contrasting | complementary | cause_effect');

            $table->timestamp('created_at')
                ->nullable();
        });
    }

    /**
     * Rollback: xóa bảng paired_passages.
     */
    public function down(): void
    {
        Schema::dropIfExists('paired_passages');
    }
};
