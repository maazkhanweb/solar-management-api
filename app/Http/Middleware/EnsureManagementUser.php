<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureManagementUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (!in_array(strtolower((string) $user->role), ['administrator', 'manager'], true)) {
            return response()->json([
               'success' => false,
                'message' => 'You are not authorized to access this resource.',
            ], 403);
        }

        return $next($request);
    }
}