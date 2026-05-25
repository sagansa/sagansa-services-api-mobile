<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantScope
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (!$user->tenant_id) {
            return response()->json([
                'message' => 'User does not belong to any tenant.',
            ], 403);
        }

        // Set tenant context for the request
        $request->merge(['tenant_id' => $user->tenant_id]);
        
        // You can also set it in a service container or use a singleton
        app()->instance('tenant_id', $user->tenant_id);

        return $next($request);
    }
}