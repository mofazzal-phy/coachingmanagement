<?php

namespace Modules\Auth\database\seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // User data array jate code clean thake
        $users = [
            [
                'name'  => 'Super Admin',
                'email' => 'mofazzal.phy@gmail.com',
                'role'  => 'super-admin'
            ],
            [
                'name'  => 'Admin User',
                'email' => 'admin@example.com',
                'role'  => 'admin'
            ],
            [
                'name'  => 'Teacher User',
                'email' => 'teacher@example.com',
                'role'  => 'teacher'
            ],
            [
                'name'  => 'Student User',
                'email' => 'student@example.com',
                'role'  => 'student'
            ],
            [
                'name'  => 'Employee User',
                'email' => 'employee@example.com',
                'role'  => 'employee'
            ],
            [
                'name'  => 'Guardian User',
                'email' => 'guardian@example.com',
                'role'  => 'guardian'
            ],
        ];

        foreach ($users as $userData) {
            // updateOrCreate: email thakle update korbe, na thakle notun banabe
            // password automatic Argon2id hash hobe jehetu apni hashing.php te seta set korechen
            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name'     => $userData['name'],
                    'password' => Hash::make('123456789'),
                    'status'   => 'active',
                ]
            );

            // Keep only expected role to avoid stale mixed assignments.
            $user->syncRoles([$userData['role']]);
        }
    }
}