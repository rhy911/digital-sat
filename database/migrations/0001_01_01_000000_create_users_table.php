<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration 
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            
            // User credentials
            $table->string('username')->unique()->nullable();
            $table->string('email')->unique();
            $table->string('password');
            
            // User role: can be student, teacher, or admin (default is student)
            $table->enum('role', ['student', 'teacher', 'admin'])->default('student');
            
            // Two-Factor Authentication fields
            $table->boolean('is_2FA_enabled')->default(false);
            $table->string('two_factor_code', 10)->nullable();
            $table->dateTime('two_factor_expired_at')->nullable();
            
            // Email verification and account status
            $table->timestamp('email_verified_at')->nullable();
            $table->boolean('is_active')->default(true)->comment('1 la active, 0 la banned');
            $table->string('avatar')->nullable();
            
            // Default fields, timestamps, and soft deletes
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
