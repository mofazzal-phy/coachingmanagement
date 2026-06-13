<?php
namespace Modules\Core\app\Http\Middleware;

use Closure;

class RoleMiddleware
{
    public function handle($request, Closure $next, ...$roles)
    {
        if (!$request->user()) return response()->json(['status'=>'error','message'=>'Unauthenticated.'], 401);
        if (!$request->user()->hasAnyRole($roles)) return response()->json(['status'=>'error','message'=>'Forbidden.'], 403);
        return $next($request);
    }
}