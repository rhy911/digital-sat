<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classroom_events', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->string('location', 150)->nullable();
            $table->boolean('all_day')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['classroom_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classroom_events');
    }
};
