<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_tests', function (Blueprint $table) {
            $table->boolean('current_module_paused')->default(false)->after('current_module_elapsed_seconds');
            $table->char('attempt_controller_token_hash', 64)->nullable()->after('current_module_paused');
            $table->dateTime('attempt_controller_last_seen_at')->nullable()->after('attempt_controller_token_hash');
        });
    }

    public function down(): void
    {
        Schema::table('user_tests', function (Blueprint $table) {
            $table->dropColumn([
                'current_module_paused',
                'attempt_controller_token_hash',
                'attempt_controller_last_seen_at',
            ]);
        });
    }
};
