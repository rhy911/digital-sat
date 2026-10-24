<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('home_preview_opened_at')->nullable();
            $table->timestamp('home_report_opened_at')->nullable();
            $table->string('home_guide_preference', 16)->default('auto');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn([
            'home_preview_opened_at', 'home_report_opened_at', 'home_guide_preference',
        ]));
    }
};
