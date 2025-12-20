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
        // Добавляем status в diary_access
        Schema::table('diary_access', function (Blueprint $table) {
            $table->string('status')->default('active')->after('permission');
            $table->index(['diary_id', 'status']);
        });

        // Добавляем owner_id в patients (владелец карточки - клиент)
        Schema::table('patients', function (Blueprint $table) {
            $table->foreignId('owner_id')
                  ->nullable()
                  ->after('id')
                  ->constrained('users')
                  ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('diary_access', function (Blueprint $table) {
            $table->dropIndex(['diary_id', 'status']);
            $table->dropColumn('status');
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->dropColumn('owner_id');
        });
    }
};
