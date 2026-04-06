<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\User::create([
            'username' => 'admin_tri',
            'email' => 'tri.tran@bluebook.com',
            'password' => \Illuminate\Support\Facades\Hash::make('12345678'),
            'role' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        \App\Models\User::factory(50)->create();
    }
}
