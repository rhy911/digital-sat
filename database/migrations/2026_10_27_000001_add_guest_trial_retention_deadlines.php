<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_tests', function (Blueprint $table) {
            $table->dateTime('guest_trial_expires_at')->nullable()->after('is_public_trial');
            $table->dateTime('guest_trial_absolute_expires_at')->nullable()->after('guest_trial_expires_at');
            $table->index(['is_public_trial', 'guest_trial_expires_at', 'id'], 'user_tests_public_trial_expiry_index');
        });
    }

    public function down(): void
    {
        Schema::table('user_tests', function (Blueprint $table) {
            $table->dropIndex('user_tests_public_trial_expiry_index');
            $table->dropColumn(['guest_trial_expires_at', 'guest_trial_absolute_expires_at']);
        });
    }
};
