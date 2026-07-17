<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classroom_memberships', function (Blueprint $table) {
            $table->string('display_name', 100)->nullable()->after('student_id');
        });
    }

    public function down(): void
    {
        Schema::table('classroom_memberships', function (Blueprint $table) {
            $table->dropColumn('display_name');
        });
    }
};
