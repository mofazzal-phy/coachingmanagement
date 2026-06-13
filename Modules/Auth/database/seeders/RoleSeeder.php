<?php

namespace Modules\Auth\database\seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run()
    {   
        // firstOrCreate ব্যবহার করলে ডুপ্লিকেট এরর আসবে না
        $roles = [
            'super-admin',
            'admin',
            'teacher',
            'student',
            'employee',
            'guardian'
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate([
                'name' => $role,
                'guard_name' => 'api' // যেহেতু আপনি JWT/API নিয়ে কাজ করছেন
            ]);
        }
    }
}