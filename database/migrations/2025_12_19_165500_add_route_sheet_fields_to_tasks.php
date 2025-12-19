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
        Schema::table('tasks', function (Blueprint $table) {
            // Assigned user (for nursing homes - пансионаты)
            $table->foreignId('assigned_to')
                ->nullable()
                ->after('patient_id')
                ->constrained('users')
                ->onDelete('set null');
            
            // Reschedule fields
            $table->dateTime('original_start_at')->nullable()->after('end_at');
            $table->dateTime('original_end_at')->nullable()->after('original_start_at');
            $table->text('reschedule_reason')->nullable()->after('comment');
            $table->foreignId('rescheduled_by')
                ->nullable()
                ->after('reschedule_reason')
                ->constrained('users')
                ->onDelete('set null');
            $table->dateTime('rescheduled_at')->nullable()->after('rescheduled_by');
            
            // Photo attachments (JSON array of URLs)
            $table->json('photos')->nullable()->after('comment');
            
            // Priority (for sorting)
            $table->tinyInteger('priority')->default(0)->after('status');
        });
        
        // Add assigned_to to task_templates for default assignment
        Schema::table('task_templates', function (Blueprint $table) {
            $table->foreignId('assigned_to')
                ->nullable()
                ->after('creator_id')
                ->constrained('users')
                ->onDelete('set null');
            
            // Related diary key (e.g., blood_pressure, temperature)
            $table->string('related_diary_key')->nullable()->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['assigned_to']);
            $table->dropColumn('assigned_to');
            $table->dropColumn('original_start_at');
            $table->dropColumn('original_end_at');
            $table->dropColumn('reschedule_reason');
            $table->dropForeign(['rescheduled_by']);
            $table->dropColumn('rescheduled_by');
            $table->dropColumn('rescheduled_at');
            $table->dropColumn('photos');
            $table->dropColumn('priority');
        });
        
        Schema::table('task_templates', function (Blueprint $table) {
            $table->dropForeign(['assigned_to']);
            $table->dropColumn('assigned_to');
            $table->dropColumn('related_diary_key');
        });
    }
};
