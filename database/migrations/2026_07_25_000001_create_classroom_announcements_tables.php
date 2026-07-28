<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classroom_announcements', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->boolean('pinned')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['classroom_id', 'pinned']);
        });

        Schema::create('classroom_announcement_comments', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->foreignId('announcement_id')->constrained('classroom_announcements')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index('announcement_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classroom_announcement_comments');
        Schema::dropIfExists('classroom_announcements');
    }
};
