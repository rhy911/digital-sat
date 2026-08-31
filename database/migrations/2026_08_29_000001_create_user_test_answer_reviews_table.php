<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_test_answer_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_test_answer_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('student_error_type', 40)->nullable();
            $table->string('teacher_error_type', 40)->nullable();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_test_answer_reviews');
    }
};
