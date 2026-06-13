<?php
namespace Modules\Core\app\Http\Middleware;

use Closure;

class PermissionMiddleware
{
    public function handle($request, Closure $next, $permission)
    {
        if (!$request->user()) return response()->json(['status'=>'error','message'=>'Unauthenticated.'], 401);
        if (!$request->user()->can($permission)) return response()->json(['status'=>'error','message'=>'Forbidden.'], 403);
        return $next($request);
    }
}