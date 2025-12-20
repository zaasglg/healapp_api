<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Добавляет колонку type в таблицу users для хранения типа пользователя:
     * - organization: владелец/сотрудник организации
     * - private_caregiver: частная сиделка
     * - client: клиент
     */
    public function up(): void
    {
        // Добавляем колонку type в users
        Schema::table('users', function (Blueprint $table) {
            $table->string('type')->default('client')->after('id');
            $table->index('type');
        });

        // Миграция существующих данных
        // Владельцы организаций получают type = 'organization'
        $owners = \DB::table('organizations')
            ->select('owner_id')
            ->get()
            ->pluck('owner_id');

        \DB::table('users')
            ->whereIn('id', $owners)
            ->update(['type' => 'organization']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });
    }
};
