<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $email = config('services.admin.email');
        $password = config('services.admin.password');
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || ! is_string($password) || strlen($password) < 12) {
            throw new \RuntimeException('Set ADMIN_EMAIL and a strong ADMIN_PASSWORD (at least 12 characters) before seeding an admin.');
        }
        User::firstOrCreate(['email' => $email], [
            'name' => 'Admin User',
            'password' => Hash::make($password),
        ]);
    }
}
