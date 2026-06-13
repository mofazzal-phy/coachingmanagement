<?php

namespace Modules\Auth\app\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Modules\Core\app\Http\Controllers\BaseApiController;
use Modules\Auth\app\Http\Requests\LoginRequest;
use Modules\Auth\app\Http\Requests\RegisterRequest;
use Modules\Auth\app\Services\AuthService;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends BaseApiController
{
    public function __construct(protected AuthService $authService) {}

    public function login(LoginRequest $request)
    {
        $credentials = $request->validated();
        $loginField = $request->input('login_field', 'email');
        $loginValue = $request->input('login_value', $credentials['email'] ?? '');

        // Resolve the user via email (default) or username, verifying the password.
        $user = null;
        if (JWTAuth::attempt($credentials)) {
            $user = auth()->user();
        } elseif ($loginField === 'name') {
            $candidate = User::where('name', $loginValue)->first();
            if ($candidate && Hash::check($credentials['password'], $candidate->password)) {
                $user = $candidate;
            }
        }

        if (!$user) {
            return $this->error('Invalid credentials', 401);
        }

        // Pending/disabled accounts cannot log in (e.g. a public cash enrollment that
        // is awaiting admin approval creates an inactive account first).
        if (($user->status ?? 'active') === 'inactive') {
            return $this->error('Your account is awaiting approval. You can log in once the admin confirms your enrollment.', 403);
        }

        $token = JWTAuth::fromUser($user);
        auth()->setUser($user);
        return $this->respondWithToken($token);
    }

    public function register(RegisterRequest $request)
    {
        $user = $this->authService->register($request->validated());
        $token = JWTAuth::fromUser($user);
        return $this->respondWithToken($token, 201);
    }

    public function me()
    {
        return $this->success($this->transformUser(auth()->user()?->load('roles')));
    }

    public function logout()
    {
        auth()->logout();
        return $this->success(null, 'Successfully logged out');
    }

    /**
     * Refresh an expired access token (within refresh TTL).
     */
    public function refresh()
    {
        try {
            $newToken = JWTAuth::parseToken()->refresh();
            $user = JWTAuth::setToken($newToken)->toUser();
            auth()->setUser($user);

            return $this->respondWithToken($newToken);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return $this->error('Unable to refresh token. Please login again.', 401);
        }
    }

    protected function respondWithToken($token, $code = 200)
    {
        $user = $this->transformUser(auth()->user()?->load('roles'));

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 60,
            'user' => $user,
        ], $code);
    }

    protected function transformUser($user)
    {
        if (!$user) {
            return null;
        }

        $roles = method_exists($user, 'getRoleNames')
            ? $user->getRoleNames()->values()->toArray()
            : [];

        $resolvedRole = null;
        // First try to find a matching role from the predefined priority list
        $priorityRoles = ['super-admin', 'admin', 'teacher', 'employee', 'student', 'guardian'];
        foreach ($priorityRoles as $candidate) {
            if (in_array($candidate, $roles, true)) {
                $resolvedRole = $candidate;
                break;
            }
        }

        // If no predefined role matched, use the first available role (supports custom roles)
        if (!$resolvedRole && !empty($roles)) {
            $resolvedRole = $roles[0];
        }

        // Fallback to user->role attribute
        if (!$resolvedRole && !empty($user->role)) {
            $resolvedRole = $user->role;
        }

        // Get all permissions for the user
        $permissions = method_exists($user, 'getAllPermissions')
            ? $user->getAllPermissions()->pluck('name')->values()->toArray()
            : [];

        $user->setAttribute('roles', $roles);
        $user->setAttribute('role', $resolvedRole);
        $user->setAttribute('permissions', $permissions);

        // Attach student_id for student users (used by frontend fee dashboard)
        if ($resolvedRole === 'student') {
            // -------------------------------------------------------
            // Helper: check if the user's name loosely matches the student's full name
            // -------------------------------------------------------
            $nameMatches = function ($student) use ($user): bool {
                if (empty($user->name) || !$student) {
                    return false;
                }
                $userName = mb_strtolower(trim($user->name));
                $studentName = mb_strtolower(trim($student->first_name . ' ' . ($student->last_name ?? '')));
                if ($studentName === $userName) return true;
                if (str_contains($studentName, $userName)) return true;
                if (str_contains($userName, $studentName)) return true;
                return false;
            };

            // Strategy 1: Find by user_id (most reliable when correctly set)
            // BUT verify the name also matches to prevent cross-mapping bugs
            $student = \Modules\Student\app\Models\Student::where('user_id', $user->id)->first();
            if ($student && !$nameMatches($student)) {
                // Name doesn't match — the user_id mapping is likely incorrect.
                // Clear the bad mapping and fall through to other strategies.
                $student->update(['user_id' => null]);
                $student = null;
            }

            // Strategy 2: Find by matching email (only if unique match AND name matches)
            if (!$student && !empty($user->email)) {
                $studentsByEmail = \Modules\Student\app\Models\Student::where('email', $user->email)->get();
                if ($studentsByEmail->count() === 1) {
                    $candidate = $studentsByEmail->first();
                    if ($nameMatches($candidate)) {
                        $candidate->update(['user_id' => $user->id]);
                        $student = $candidate;
                    }
                }
            }

            // Strategy 3: Find by matching phone (only if unique match AND name matches)
            if (!$student && !empty($user->phone)) {
                $studentsByPhone = \Modules\Student\app\Models\Student::where('phone', $user->phone)->get();
                if ($studentsByPhone->count() === 1) {
                    $candidate = $studentsByPhone->first();
                    if ($nameMatches($candidate)) {
                        $candidate->update(['user_id' => $user->id]);
                        $student = $candidate;
                    }
                }
            }

            // Strategy 4: Find by student_id matching the user's name (username)
            if (!$student && !empty($user->name)) {
                $student = \Modules\Student\app\Models\Student::where('student_id', $user->name)->first();
                if ($student) {
                    $student->update(['user_id' => $user->id]);
                }
            }

            // Strategy 5: Fuzzy name match
            if (!$student && !empty($user->name)) {
                $userNameLower = mb_strtolower(trim($user->name));
                $candidate = \Modules\Student\app\Models\Student::whereRaw('LOWER(first_name) LIKE ?', ['%' . $userNameLower . '%'])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', ['%' . $userNameLower . '%'])
                    ->orWhereRaw("CONCAT(LOWER(first_name), ' ', LOWER(last_name)) LIKE ?", ['%' . $userNameLower . '%'])
                    ->first();
                if ($candidate) {
                    $matchCount = \Modules\Student\app\Models\Student::whereRaw('LOWER(first_name) LIKE ?', ['%' . $userNameLower . '%'])
                        ->orWhereRaw('LOWER(last_name) LIKE ?', ['%' . $userNameLower . '%'])
                        ->orWhereRaw("CONCAT(LOWER(first_name), ' ', LOWER(last_name)) LIKE ?", ['%' . $userNameLower . '%'])
                        ->count();
                    if ($matchCount === 1) {
                        $candidate->update(['user_id' => $user->id]);
                        $student = $candidate;
                    }
                }
            }

            // Strategy 6 (LAST RESORT): Match by email without name verification
            // Only when the email is unique to one student.
            if (!$student && !empty($user->email)) {
                $studentsByEmail = \Modules\Student\app\Models\Student::where('email', $user->email)->get();
                if ($studentsByEmail->count() === 1) {
                    $candidate = $studentsByEmail->first();
                    $candidate->update(['user_id' => $user->id]);
                    $student = $candidate;
                }
            }

            // Strategy 7 (LAST RESORT): Match by phone without name verification
            // Only when the phone is unique to one student.
            if (!$student && !empty($user->phone)) {
                $studentsByPhone = \Modules\Student\app\Models\Student::where('phone', $user->phone)->get();
                if ($studentsByPhone->count() === 1) {
                    $candidate = $studentsByPhone->first();
                    $candidate->update(['user_id' => $user->id]);
                    $student = $candidate;
                }
            }

            $user->setAttribute('student_id', $student?->id);
        }

        return $user;
    }
}
