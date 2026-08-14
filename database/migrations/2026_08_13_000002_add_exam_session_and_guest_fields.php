<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_tests', function (Blueprint $table) {
            $table->foreignId('exam_session_id')->nullable()->after('assignment_id')
                ->constrained('exam_sessions')->nullOnDelete();
            $table->string('guest_name', 191)->nullable()->after('exam_session_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_guest')->default(false)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('user_tests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('exam_session_id');
            $table->dropColumn('guest_name');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_guest');
        });
    }
};
