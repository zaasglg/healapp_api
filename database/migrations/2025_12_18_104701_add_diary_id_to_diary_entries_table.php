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
        Schema::table('diary_entries', function (Blueprint $table) {
            // Add diary_id column
            $table->foreignId('diary_id')->nullable()->after('id')->constrained('diaries')->onDelete('cascade');
        });

        // Migrate existing data: create diaries for patients that have diary entries
        $patientIds = \DB::table('diary_entries')
            ->select('patient_id')
            ->distinct()
            ->pluck('patient_id');

        foreach ($patientIds as $patientId) {
            $diaryId = \DB::table('diaries')->insertGetId([
                'patient_id' => $patientId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            \DB::table('diary_entries')
                ->where('patient_id', $patientId)
                ->update(['diary_id' => $diaryId]);
        }

        Schema::table('diary_entries', function (Blueprint $table) {
            // Make diary_id required after migration
            $table->foreignId('diary_id')->nullable(false)->change();
            
            // Drop patient_id column as it's now accessed through diary
            $table->dropForeign(['patient_id']);
            $table->dropColumn('patient_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('diary_entries', function (Blueprint $table) {
            $table->foreignId('patient_id')->nullable()->after('id')->constrained('patients')->onDelete('cascade');
        });

        // Migrate data back
        $entries = \DB::table('diary_entries')
            ->join('diaries', 'diary_entries.diary_id', '=', 'diaries.id')
            ->select('diary_entries.id', 'diaries.patient_id')
            ->get();

        foreach ($entries as $entry) {
            \DB::table('diary_entries')
                ->where('id', $entry->id)
                ->update(['patient_id' => $entry->patient_id]);
        }

        Schema::table('diary_entries', function (Blueprint $table) {
            $table->foreignId('patient_id')->nullable(false)->change();
            $table->dropForeign(['diary_id']);
            $table->dropColumn('diary_id');
        });
    }
};
