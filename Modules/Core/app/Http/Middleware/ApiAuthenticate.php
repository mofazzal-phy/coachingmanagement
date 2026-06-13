<?php
namespace Modules\Core\app\Http\Middleware;

use Closure;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

class ApiAuthenticate
{
    public function handle($request, Closure $next)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) return response()->json(['status'=>'error','message'=>'User not found.'], 404);
        } catch (TokenExpiredException $e) {
            return response()->json(['status'=>'error','message'=>'Token expired.'], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['status'=>'error','message'=>'Token invalid.'], 401);
        } catch (JWTException $e) {
            return response()->json(['status'=>'error','message'=>'Token not provided.'], 401);
        }
        return $next($request);
    }
}
