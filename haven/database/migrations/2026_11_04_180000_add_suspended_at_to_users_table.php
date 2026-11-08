<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'suspended_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('suspended_at')->nullable()->after('is_admin');
            });
        }

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['event', 'created_at'], 'audit_logs_event_created_idx');
            $table->index(['user_id', 'created_at'], 'audit_logs_user_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_event_created_idx');
            $table->dropIndex('audit_logs_user_created_idx');
        });

        if (Schema::hasColumn('users', 'suspended_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('suspended_at');
            });
        }
    }
};
