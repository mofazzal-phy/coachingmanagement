<?php

namespace Modules\Auth\app\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\app\Repositories\UserRepository;
use Modules\Auth\app\Http\Requests\StoreUserRequest;
use Modules\Auth\app\Http\Requests\UpdateUserRequest;
use Modules\Core\app\Http\Controllers\BaseApiController;

class UserController extends BaseApiController
{
    public function __construct(protected UserRepository $userRepository) {}

    /**
     * Get paginated users.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $this->getPerPage($request);
        $search = $request->input('search');
        $role = $request->input('role');
        $filters = $request->only(['status', 'is_active']);

        if ($role) {
            $users = $this->userRepository->findByRole($role, $perPage);
            // Load roles for each user
            $users->load('roles');
        } elseif ($search || !empty($filters)) {
            $users = $this->userRepository->searchAndFilter($search, $filters, $perPage);
            // Load roles for each user
            $users->load('roles');
        } else {
            $users = $this->userRepository->paginate($perPage, ['*'], ['roles']);
        }

        return $this->paginatedResponse($users);
    }

    /**
     * Get a single user.
     */
    public function show(string $id): JsonResponse
    {
        $user = $this->userRepository->findById($id, ['*'], ['roles']);
        if (!$user) {
            return $this->notFound('User not found');
        }
        return $this->success($user);
    }

    /**
     * Create a new user.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);
        
        // Map is_active to status field; default to 'active' if not provided
        if (isset($data['is_active'])) {
            $data['status'] = $data['is_active'] ? 'active' : 'inactive';
            unset($data['is_active']);
        } else {
            $data['status'] = 'active';
        }

        $user = $this->userRepository->createWithRole(
            $data,
            $data['role'] ?? 'student'
        );

        $user->load('roles');
        return $this->created($user, 'User created successfully');
    }

    /**
     * Update a user.
     */
    public function update(UpdateUserRequest $request, string $id): JsonResponse
    {
        $user = $this->userRepository->findById($id);
        if (!$user) {
            return $this->notFound('User not found');
        }

        $data = $request->validated();
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        // Map is_active to status field
        if (isset($data['is_active'])) {
            $data['status'] = $data['is_active'] ? 'active' : 'inactive';
            unset($data['is_active']);
        }

        $roles = [];
        if (isset($data['role'])) {
            $roles = [$data['role']];
        }

        $user = $this->userRepository->updateWithRoles($id, $data, $roles);
        $user->load('roles');

        return $this->success($user, 'User updated successfully');
    }

    /**
     * Delete a user.
     */
    public function destroy(string $id): JsonResponse
    {
        $user = $this->userRepository->findById($id);
        if (!$user) {
            return $this->notFound('User not found');
        }

        // Prevent deleting super admin
        if ($user->hasRole('super-admin')) {
            return $this->forbidden('Cannot delete super admin user');
        }

        $this->userRepository->deleteById($id);
        return $this->noContent('User deleted successfully');
    }

    /**
     * Get users by role.
     */
    public function byRole(Request $request, string $role): JsonResponse
    {
        $perPage = $this->getPerPage($request);
        $users = $this->userRepository->findByRole($role, $perPage);
        return $this->paginatedResponse($users);
    }

    /**
     * Get dashboard stats.
     */
    public function stats(): JsonResponse
    {
        return $this->success($this->userRepository->getDashboardStats());
    }
}
