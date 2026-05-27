<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Gaspar Admin',
            'email' => 'admin@gaspar.com',
            'user_role' => \App\Enums\UserRoleEnum::ADMIN,
            'password' => \Illuminate\Support\Facades\Hash::make('admin'),
        ]);

        User::factory()->create([
            'name' => 'Gaspar Gestor',
            'email' => 'gestor@gaspar.com',
            'user_role' => \App\Enums\UserRoleEnum::GESTOR,
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
        ]);

        User::factory()->create([
            'name' => 'Gaspar Executor',
            'email' => 'executor@gaspar.com',
            'user_role' => \App\Enums\UserRoleEnum::EXECUTOR,
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
        ]);

        User::factory()->create([
            'name' => 'Gaspar Viewer',
            'email' => 'viewer@gaspar.com',
            'user_role' => \App\Enums\UserRoleEnum::VIEWER,
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
        ]);
    }
}
