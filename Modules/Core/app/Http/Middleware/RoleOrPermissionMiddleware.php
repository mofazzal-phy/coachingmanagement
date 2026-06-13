<?php

namespace Modules\Core\app\Http\Middleware;

use Closure;

class RoleOrPermissionMiddleware
{
    public function handle($request, Closure $next, string $rolesOrPermissions)
    {
        if (!$request->user()) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated.'], 401);
        }

        $user = $request->user();
        $items = array_filter(explode('|', $rolesOrPermissions));

        foreach ($items as $item) {
            if ($user->hasRole($item) || $user->can($item)) {
                return $next($request);
            }
        }

        return response()->json(['status' => 'error', 'message' => 'Forbidden.'], 403);
    }
}
