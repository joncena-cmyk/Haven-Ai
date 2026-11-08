<?php

namespace App\Console\Commands;

use App\Models\Admin;
use App\Services\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdminCommand extends Command
{
    protected $signature = 'admin:create
                            {email : Administrator login email}
                            {--name= : Display name (defaults to the email prefix)}
                            {--password= : Password (omit to be prompted securely)}';

    protected $description = 'Create a dedicated administrator account (or reset the password of an existing one).';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));

        if (Validator::make(['email' => $email], ['email' => 'required|email|max:255'])->fails()) {
            $this->error('Please provide a valid email address.');

            return self::FAILURE;
        }

        $existing = Admin::where('email', $email)->first();

        if ($existing && ! $this->confirm("An administrator with {$email} already exists. Reset the password?", false)) {
            return self::SUCCESS;
        }

        $password = $this->option('password');
        if (! $password) {
            $password = $this->secret('Password (min 10 chars, mixed case + number)');
            $confirm = $this->secret('Confirm password');

            if ($password !== $confirm) {
                $this->error('Passwords do not match.');

                return self::FAILURE;
            }
        }

        $validator = Validator::make(['password' => $password], [
            'password' => ['required', Password::min(10)->mixedCase()->numbers()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        if ($existing) {
            $existing->update(['password' => $password]);
            $admin = $existing;
            $event = 'admin.password_reset_cli';
        } else {
            $admin = Admin::create([
                'name' => $this->option('name') ?: ucfirst(strtok($email, '@')),
                'email' => $email,
                'password' => $password,
            ]);
            $event = 'admin.account_created_cli';
        }

        AuditLogger::log($event, ['email' => $admin->email], null, 'success', $admin->id);

        $this->info("Administrator ready: {$admin->email}");
        $this->line('Sign in at: '.url('/admin/login'));

        return self::SUCCESS;
    }
}
