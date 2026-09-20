<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed a single development Super Admin account (Arif Hamdani).
     *
     * Idempotent: safe to run multiple times without duplicating the user.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'arif@annur.test'],
            [
                'name' => 'Arif Hamdani',
                'password' => Hash::make('password'),
                'role' => User::ROLE_SUPER_ADMIN,
                'position' => 'Admin TU',
                'is_active' => true,
            ]
        );
    }
}
