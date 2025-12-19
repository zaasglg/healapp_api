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
        Schema::create('alarms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diary_id')->constrained('diaries')->cascadeOnDelete();
            $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
            $table->string('name'); // Название будильника (например, "Парацетамол")
            $table->enum('type', ['medicine', 'vitamin'])->default('medicine'); // Тип: лекарство или витамин
            $table->json('days_of_week'); // Дни недели: [1,2,3,4,5,6,7] (1=пн, 7=вс)
            $table->json('times'); // Времена приёма: ["09:00", "14:00", "21:00"]
            $table->string('dosage')->nullable(); // Дозировка (например, "1 таблетка")
            $table->text('notes')->nullable(); // Дополнительные заметки
            $table->boolean('is_active')->default(true); // Активен ли будильник
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alarms'); 
    }
};
