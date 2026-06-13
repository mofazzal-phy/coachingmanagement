<?php

namespace Modules\Auth\app\Repositories;

use App\Models\User;
use Modules\Core\app\Repositories\BaseRepository;

class UserRepository extends BaseRepository
{
    protected function getModelClass(): string
    {
        return User::class;
    }

    /**
     * Find user by email.
     */
    public function findByEmail(string $email): ?User
    {
        return $this->findByField('email', $email);
    }

    /**
     * Get users by role.
     */
    public function findByRole(string $role, int $perPage = 15)
    {
        return User::role($role)->paginate($perPage);
    }

    /**
     * Create user with role assignment.
     */
    public function createWithRole(array $data, string $role): User
    {
        $this->beginTransaction();
        try {
            $user = $this->create($data);
            $user->assignRole($role);
            $this->commit();
            return $user;
        } catch (\Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * Update user and sync roles.
     */
    public function updateWithRoles(string $id, array $data, array $roles = []): User
    {
        $this->beginTransaction();
        try {
            $user = $this->update($id, $data);
            if (!empty($roles)) {
                $user->syncRoles($roles);
            }
            $this->commit();
            return $user;
        } catch (\Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * Get dashboard stats.
     */
    public function getDashboardStats(): array
    {
        return [
            'total_users' => $this->count(),
            'total_admins' => User::role('admin')->count(),
            'total_teachers' => User::role('teacher')->count(),
            'total_students' => User::role('student')->count(),
            'total_employees' => User::role('employee')->count(),
            'total_guardians' => User::role('guardian')->count(),
        ];
    }
}
