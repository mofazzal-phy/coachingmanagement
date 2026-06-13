<?php

namespace Modules\Auth\app\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Modules\Core\app\Http\Controllers\BaseApiController;

class PermissionController extends BaseApiController
{
    /**
     * Get all permissions.
     */
    public function index(): JsonResponse
    {
        $permissions = Permission::all()->groupBy(function ($permission) {
            $parts = explode(' ', $permission->name);
            return end($parts); // Group by resource name (e.g., 'users', 'roles')
        });

        return $this->success($permissions);
    }

    /**
     * Create a new permission.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:permissions,name',
            'guard_name' => 'sometimes|string',
        ]);

        $permission = Permission::create([
            'name' => $validated['name'],
            'guard_name' => $validated['guard_name'] ?? 'api',
        ]);

        return $this->created($permission, 'Permission created successfully');
    }

    /**
     * Delete a permission.
     */
    public function destroy(int $id): JsonResponse
    {
        $permission = Permission::find($id);
        if (!$permission) {
            return $this->notFound('Permission not found');
        }

        $permission->delete();
        return $this->noContent('Permission deleted successfully');
    }

    /**
     * Sync permissions for a role.
     */
    public function syncRolePermissions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'role_id' => 'required|integer|exists:roles,id',
            'permissions' => 'required|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $role = \Spatie\Permission\Models\Role::find($validated['role_id']);
        $role->syncPermissions($validated['permissions']);

        return $this->success($role->load('permissions'), 'Permissions synced successfully');
    }
}
