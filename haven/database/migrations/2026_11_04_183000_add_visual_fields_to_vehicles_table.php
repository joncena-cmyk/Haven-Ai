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
        Schema::table('vehicles', function (Blueprint $table) {
            if (! Schema::hasColumn('vehicles', 'body_type')) {
                $table->string('body_type', 30)->nullable()->default('sedan')->after('model');
            }
            if (! Schema::hasColumn('vehicles', 'image_path')) {
                $table->string('image_path', 255)->nullable()->after('color');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            if (Schema::hasColumn('vehicles', 'image_path')) {
                $table->dropColumn('image_path');
            }
            if (Schema::hasColumn('vehicles', 'body_type')) {
                $table->dropColumn('body_type');
            }
        });
    }
};
