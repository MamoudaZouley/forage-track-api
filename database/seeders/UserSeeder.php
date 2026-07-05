<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Mamouda Zouley',
            'email' => 'admin@forage.ne',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);

        User::create([
            'name' => 'Ibrahim Maïkano',
            'email' => 'ibrahim@forage.ne',
            'password' => Hash::make('password123'),
            'role' => 'user',
        ]);

        User::create([
            'name' => 'Halilou Sounbalou',
            'email' => 'halilou@forage.ne',
            'password' => Hash::make('password123'),
            'role' => 'user',
        ]);
    }
}