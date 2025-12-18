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
        Schema::create('diary_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diary_id')->constrained('diaries')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('permission', ['view', 'edit', 'full'])->default('view');
            $table->timestamps();
            
            // One access record per user per diary
            $table->unique(['diary_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('diary_access');
    }
};
