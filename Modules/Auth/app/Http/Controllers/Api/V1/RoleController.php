<?php

namespace Modules\Auth\app\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Modules\Core\app\Http\Controllers\BaseApiController;

class RoleController extends BaseApiController
{
    /**
     * Get all roles.
     */
    public function index(): JsonResponse
    {
        $roles = Role::with('permissions')->get()->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
                'permissions' => $role->permissions->pluck('name'),
                'users_count' => \App\Models\User::role($role->name)->count(),
                'created_at' => $role->created_at,
            ];
        });

        return $this->success($roles->values()->all());
    }

    /**
     * Create a new role.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:roles,name',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => 'api',
        ]);

        if (!empty($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        $role->load('permissions');
        return $this->created($role, 'Role created successfully');
    }

    /**
     * Get a single role.
     */
    public function show(int $id): JsonResponse
    {
        $role = Role::with('permissions')->find($id);
        if (!$role) {
            return $this->notFound('Role not found');
        }
        return $this->success($role);
    }

    /**
     * Update a role.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $role = Role::find($id);
        if (!$role) {
            return $this->notFound('Role not found');
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|unique:roles,name,' . $id,
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        if (isset($validated['name'])) {
            $role->update(['name' => $validated['name']]);
        }

        if (isset($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        $role->load('permissions');
        return $this->success($role, 'Role updated successfully');
    }

    /**
     * Delete a role.
     */
    public function destroy(int $id): JsonResponse
    {
        $role = Role::find($id);
        if (!$role) {
            return $this->notFound('Role not found');
        }

        if ($role->name === 'super-admin') {
            return $this->forbidden('Cannot delete super-admin role');
        }

        $role->delete();
        return $this->noContent('Role deleted successfully');
    }

    /**
     * Assign role to user.
     */
    public function assignToUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|string|exists:users,id',
            'role' => 'required|string|exists:roles,name',
        ]);

        $user = \App\Models\User::find($validated['user_id']);
        $user->syncRoles([$validated['role']]);

        return $this->success($user->load('roles'), 'Role assigned successfully');
    }
}
