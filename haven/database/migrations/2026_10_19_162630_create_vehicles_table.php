<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('nickname')->nullable();          // e.g. "My Toyota"
            $table->string('make');                          // e.g. Toyota
            $table->string('model');                         // e.g. Corolla
            $table->unsignedSmallInteger('year');            // e.g. 2018
            $table->string('plate_number')->nullable();      // e.g. ABC-1234
            $table->string('color')->nullable();             // e.g. White
            $table->string('engine_type')->nullable();       // e.g. 2NZ-FE 1.3L
            $table->string('transmission')->nullable();      // Manual / Automatic / CVT
            $table->unsignedInteger('mileage_km')->nullable(); // current odometer
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
