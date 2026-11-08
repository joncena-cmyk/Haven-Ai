<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dedicated administrator identities (separate from driver accounts).
     */
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->foreignId('admin_id')->nullable()->after('user_id')
                ->constrained('admins')->nullOnDelete();
        });

        // Carry over any existing flag-based administrators so nobody is locked out.
        // Their driver account stays untouched; only the privilege moves to the admins table.
        if (Schema::hasColumn('users', 'is_admin')) {
            $now = now();
            DB::table('users')->where('is_admin', true)->orderBy('id')->get()->each(
                function ($u) use ($now) {
                    DB::table('admins')->insert([
                        'name' => $u->name,
                        'email' => $u->email,
                        'password' => $u->password,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            );
        }
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('admin_id');
        });

        Schema::dropIfExists('admins');
    }
};
