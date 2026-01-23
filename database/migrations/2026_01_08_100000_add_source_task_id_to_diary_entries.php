<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds source_task_id to link diary entries with tasks.
     * This enables bidirectional synchronization between:
     * - Route Sheet tasks (маршрутный лист)
     * - Pinned indicators (закрепленные показатели)
     */
    public function up(): void
    {
        Schema::table('diary_entries', function (Blueprint $table) {
            $table->foreignId('source_task_id')
                ->nullable()
                ->after('author_id')
                ->constrained('tasks')
                ->onDelete('set null');

            // Index for quick lookups by task
            $table->index('source_task_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('diary_entries', function (Blueprint $table) {
            $table->dropForeign(['source_task_id']);
            $table->dropColumn('source_task_id');
        });
    }
};
