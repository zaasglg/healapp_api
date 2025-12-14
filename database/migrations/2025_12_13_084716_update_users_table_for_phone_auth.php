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
        Schema::table('users', function (Blueprint $table) {
            // Remove email columns
            $table->dropUnique(['email']);
            $table->dropColumn(['email', 'email_verified_at']);
            
            // Add phone columns
            $table->string('phone')->unique()->after('middle_name');
            $table->timestamp('phone_verified_at')->nullable()->after('phone');
            $table->string('verification_code', 4)->nullable()->after('phone_verified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Remove phone columns
            $table->dropUnique(['phone']);
            $table->dropColumn(['phone', 'phone_verified_at', 'verification_code']);
            
            // Restore email columns
            $table->string('email')->unique()->after('middle_name');
            $table->timestamp('email_verified_at')->nullable()->after('email');
        });
    }
};
