<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tạo bảng sections — mỗi test có 2 sections: R&W và Math.
     * Giữa 2 section có break.
     */
    public function up(): void
    {
        Schema::create('sections', function (Blueprint $table) {
            $table->id();

            $table->foreignId('test_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name', 255)
                ->comment('VD: Reading and Writing / Math');

            $table->enum('type', ['reading_writing', 'math'])
                ->comment('reading_writing | math');

            $table->integer('order')
                ->default(1)
                ->comment('1 = R&W, 2 = Math');

            $table->timestamps();
        });
    }

    /**
     * Rollback: xóa bảng sections.
     */
    public function down(): void
    {
        Schema::dropIfExists('sections');
    }
};
