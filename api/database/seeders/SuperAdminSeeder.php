<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds (or updates) the Sajio Super Admin account.
 *
 * Email comes from SUPER_ADMIN_EMAIL env; the password is a random temporary
 * one printed to the console when a new account is created.
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('app.super_admin_email');

        if (! $email) {
            $this->command?->warn('SUPER_ADMIN_EMAIL is not set — skipping Super Admin seed.');

            return;
        }

        $existing = User::query()
            ->where('role', UserRole::SuperAdmin)
            ->first();

        if ($existing) {
            $this->command?->info('Super Admin already exists: '.$existing->email);

            return;
        }

        $password = Str::password(14, symbols: false);

        User::query()->create([
            'name' => 'Sajio Super Admin',
            'email' => strtolower($email),
            'password' => $password,
            'role' => UserRole::SuperAdmin,
            'restaurant_id' => null,
            'email_verified_at' => now(),
        ]);

        $this->command?->info('Super Admin created: '.strtolower($email));
        $this->command?->info('Temporary password: '.$password);
    }
}
