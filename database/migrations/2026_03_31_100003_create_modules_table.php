<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tạo bảng modules — mỗi section có 2+ module.
     * Module 1 luôn standard. Module 2 được chọn dựa trên kết quả M1.
     * R&W: M1 = 27 câu / 32 phút, M2 = 27 câu / 32 phút.
     * Math: M1 = 22 câu / 35 phút, M2 = 22 câu / 35 phút.
     */
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->nullable()->unique();

            $table->foreignId('section_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->integer('module_number')
                ->comment('1 = Module 1, 2 = Module 2');

            $table->enum('difficulty_level', ['standard', 'easy', 'hard'])
                ->default('standard')
                ->comment('standard (M1) | easy | hard (M2)');

            $table->integer('duration_minutes')
                ->default(32);

            $table->integer('total_questions')
                ->default(27);

            $table->integer('order')
                ->default(1);

            $table->timestamps();
        });
    }

    /**
     * Rollback: xóa bảng modules.
     */
    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
