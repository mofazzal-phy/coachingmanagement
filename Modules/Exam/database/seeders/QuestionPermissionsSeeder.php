<?php

namespace Modules\Exam\database\seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class QuestionPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $perms = [
            'view questions',
            'create questions',
            'edit questions',
            'delete questions',
            'approve questions',
        ];

        foreach ($perms as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'api']);
        }

        $superAdmin = Role::findByName('super-admin', 'api');
        $admin = Role::findByName('admin', 'api');
        $teacher = Role::findByName('teacher', 'api');

        if ($superAdmin) {
            $superAdmin->givePermissionTo($perms);
        }

        if ($admin) {
            $admin->givePermissionTo($perms);
        }

        if ($teacher) {
            $teacher->givePermissionTo([
                'view questions',
                'create questions',
                'edit questions',
            ]);
        }
    }
}
