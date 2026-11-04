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
        Schema::create('learning_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->unique();
            $table->json('completed_modules')->nullable();
            $table->unsignedInteger('quiz_best_score')->default(0);
            $table->unsignedInteger('quiz_total')->default(10);
            $table->unsignedInteger('quiz_attempts')->default(0);
            $table->timestamp('last_quiz_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('learning_progress');
    }
};

