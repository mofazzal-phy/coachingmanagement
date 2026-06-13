<?php
namespace Modules\Auth\database\seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    public function run()
    {
        // =============================================
        // Define all module permissions
        // =============================================
        $modules = [
            'users' => ['view users', 'create users', 'edit users', 'delete users'],
            'roles' => ['view roles', 'create roles', 'edit roles', 'delete roles'],
            'permissions' => ['view permissions', 'create permissions', 'delete permissions'],
            'students' => ['view students', 'create students', 'edit students', 'delete students'],
            'guardians' => ['view guardians', 'create guardians', 'edit guardians', 'delete guardians'],
            'admissions' => ['view admissions', 'create admissions', 'edit admissions', 'delete admissions'],
            'academic sessions' => ['view academic sessions', 'create academic sessions', 'edit academic sessions', 'delete academic sessions'],
            'classes' => ['view classes', 'create classes', 'edit classes', 'delete classes'],
            'sections' => ['view sections', 'create sections', 'edit sections', 'delete sections'],
            'subjects' => ['view subjects', 'create subjects', 'edit subjects', 'delete subjects'],
            'academic groups' => ['view academic groups', 'create academic groups', 'edit academic groups', 'delete academic groups'],
            'rooms' => ['view rooms', 'create rooms', 'edit rooms', 'delete rooms'],
            'routine periods' => ['view routine periods', 'create routine periods', 'edit routine periods', 'delete routine periods'],
            'class routines' => ['view class routines', 'create class routines', 'edit class routines', 'delete class routines'],
            'routine exceptions' => ['view routine exceptions', 'create routine exceptions', 'edit routine exceptions', 'delete routine exceptions'],
            'attendance' => ['view attendance', 'create attendance', 'edit attendance', 'delete attendance'],
            'student attendance' => ['view student attendance', 'create student attendance', 'edit student attendance', 'delete student attendance'],
            'teacher attendance' => ['view teacher attendance', 'create teacher attendance', 'edit teacher attendance', 'delete teacher attendance'],
            'employee attendance' => ['view employee attendance', 'create employee attendance', 'edit employee attendance', 'delete employee attendance'],
            'attendance sessions' => ['view attendance sessions', 'create attendance sessions', 'edit attendance sessions', 'delete attendance sessions'],
            'biometric devices' => ['view biometric devices', 'create biometric devices', 'edit biometric devices', 'delete biometric devices'],
            'attendance reports' => ['view attendance reports', 'generate attendance reports', 'export attendance reports'],
            'attendance dashboard' => ['view attendance dashboard'],
            'device simulator' => ['view device simulator', 'use device simulator'],
            'exams' => ['view exams', 'create exams', 'edit exams', 'delete exams'],
            'exam types' => ['view exam types', 'create exam types', 'edit exam types', 'delete exam types'],
            'exam routines' => ['view exam routines', 'create exam routines', 'edit exam routines', 'delete exam routines'],
            'exam results' => ['view exam results', 'create exam results', 'edit exam results', 'delete exam results'],
            'questions' => ['view questions', 'create questions', 'edit questions', 'delete questions', 'approve questions'],
            'fee types' => ['view fee types', 'create fee types', 'edit fee types', 'delete fee types'],
            'fee structures' => ['view fee structures', 'create fee structures', 'edit fee structures', 'delete fee structures'],
            'fee collections' => ['view fee collections', 'create fee collections', 'edit fee collections', 'delete fee collections'],
            'expenses' => ['view expenses', 'create expenses', 'edit expenses', 'delete expenses'],
            'expense categories' => ['view expense categories', 'create expense categories', 'edit expense categories', 'delete expense categories'],
            'employees' => ['view employees', 'create employees', 'edit employees', 'delete employees'],
            'departments' => ['view departments', 'create departments', 'edit departments', 'delete departments'],
            'designations' => ['view designations', 'create designations', 'edit designations', 'delete designations'],
            'staff attendance' => ['view staff attendance', 'create staff attendance', 'edit staff attendance', 'delete staff attendance'],
            'leave requests' => ['view leave requests', 'create leave requests', 'edit leave requests', 'delete leave requests'],
            'leave types' => ['view leave types', 'create leave types', 'edit leave types', 'delete leave types'],
            'payroll' => ['view payroll', 'create payroll', 'edit payroll', 'delete payroll'],
            'notifications' => ['view notifications', 'create notifications', 'delete notifications'],
            'notice board' => ['view notice board', 'create notice board', 'edit notice board', 'delete notice board', 'approve notice board'],
            'cms pages' => ['view cms pages', 'create cms pages', 'edit cms pages', 'delete cms pages'],
            'sliders' => ['view sliders', 'create sliders', 'edit sliders', 'delete sliders'],
            'events' => ['view events', 'create events', 'edit events', 'delete events'],
            'gallery' => ['view gallery', 'create gallery', 'edit gallery', 'delete gallery'],
            'testimonials' => ['view testimonials', 'create testimonials', 'edit testimonials', 'delete testimonials'],
            'cms foundation' => [
                'approve cms content',
                'view cms analytics',
                'view cms audit logs',
                'upload cms media',
            ],
            'study materials' => ['view study materials', 'create study materials', 'edit study materials', 'delete study materials'],
            'success stories' => ['view success stories', 'create success stories', 'edit success stories', 'delete success stories'],
            'download center' => ['view download center', 'create download center', 'edit download center', 'delete download center'],
            'reports' => ['view reports', 'generate reports', 'export reports'],
            'teachers' => ['view teachers', 'create teachers', 'edit teachers', 'delete teachers'],
            'courses' => ['view courses', 'create courses', 'edit courses', 'delete courses'],
            'batches' => ['view batches', 'create batches', 'edit batches', 'delete batches'],
            'enrollments' => ['view enrollments', 'create enrollments', 'edit enrollments', 'delete enrollments'],
            'settings' => ['view settings', 'edit settings'],
        ];

        // Flatten and create all permissions
        $allPermissions = [];
        foreach ($modules as $perms) {
            foreach ($perms as $perm) {
                $allPermissions[] = $perm;
                Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'api']);
            }
        }

        // =============================================
        // Assign permissions to roles
        // =============================================
        $superAdmin = Role::findByName('super-admin');
        $admin = Role::findByName('admin');
        $teacher = Role::findByName('teacher');
        $student = Role::findByName('student');
        $employee = Role::findByName('employee');
        $guardian = Role::findByName('guardian');

        // Super Admin gets ALL permissions
        $superAdmin->givePermissionTo(Permission::all());

        // Admin gets everything EXCEPT delete permissions
        $adminPermissions = Permission::where('name', 'not like', 'delete %')->get();
        $admin->givePermissionTo($adminPermissions);

        // Teacher permissions
        $teacher->givePermissionTo([
            'view teachers', 'edit teachers',
            // View only
            'view students', 'view guardians',
            'view academic sessions', 'view classes', 'view sections', 'view subjects',
            'view class routines', 'view routine exceptions',
            'view attendance', 'create attendance', 'edit attendance',
            'view exams', 'view exam types', 'view exam routines',
            'view exam results', 'create exam results', 'edit exam results',
            'view questions', 'create questions', 'edit questions',
            'view fee types', 'view fee structures', 'view fee collections',
            'view notice board',
            'view events',
            'view gallery',
            'view study materials', 'create study materials', 'edit study materials',
        ]);

        // Student permissions
        $student->givePermissionTo([
            'view academic sessions', 'view classes', 'view sections', 'view subjects',
            'view class routines', 'view routine exceptions',
            'view attendance',
            'view exams', 'view exam types', 'view exam routines',
            'view exam results',
            'view fee types', 'view fee structures', 'view fee collections',
            'view notice board',
            'view events',
            'view gallery',
            'view study materials',
            'view download center',
        ]);

        // Employee permissions
        $employee->givePermissionTo([
            'view employees', 'view departments', 'view designations',
            'view attendance',
            'view staff attendance', 'create staff attendance',
            'view employee attendance',
            'view leave requests', 'create leave requests',
            'view leave types',
            'view payroll',
            'view notice board',
            'view events',
            'view gallery',
            'view download center',
        ]);

        // Guardian permissions
        $guardian->givePermissionTo([
            'view students',
            'view attendance',
            'view exams', 'view exam results',
            'view fee collections',
            'view notice board',
            'view events',
            'view gallery',
            'view download center',
        ]);
    }
}
