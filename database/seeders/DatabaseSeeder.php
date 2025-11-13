<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Role;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // --- 1️⃣ Seed roles ---
        $roles = [
            ['name' => 'developer'],
            ['name' => 'admin'],
            ['name' => 'user'],
            ['name' => 'member'],
            ['name' => 'teacher'],
            ['name' => 'student'],
            ['name' => 'guest'],
        ];

        foreach ($roles as $roleData) {
            Role::firstOrCreate(['name' => $roleData['name']]);
        }

        // --- 2️⃣ Create users per role ---
        $this->createDefaultUsers();
    }

    /**
     * Create default user accounts for each role
     */
    private function createDefaultUsers(): void
    {
        $defaultPassword = Hash::make('password'); // 🔐 default for all test users

        $roleUsers = [
            'developer' => ['name' => 'Dev Account', 'email' => 'developer@app.com'],
            'admin'     => ['name' => 'Admin Account', 'email' => 'admin@app.com'],
            'user'      => ['name' => 'User Account', 'email' => 'user@app.com'],
            'member'    => ['name' => 'Member Account', 'email' => 'member@app.com'],
            'teacher'   => ['name' => 'Teacher Account', 'email' => 'teacher@app.com'],
            'student'   => ['name' => 'Student Account', 'email' => 'student@app.com'],
            'guest'     => ['name' => 'Guest Account', 'email' => 'guest@app.com'],
        ];

        foreach ($roleUsers as $roleName => $userData) {
            $role = Role::where('name', $roleName)->first();

            User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => $defaultPassword,
                    'role_id' => $role->id,
                ]
            );
        }
    }
}
