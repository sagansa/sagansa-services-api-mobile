<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateWithApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $authorization = $request->header('Authorization', '');

        if (! str_starts_with($authorization, 'Bearer ')) {
            return $this->unauthorized();
        }

        $plainToken = trim(substr($authorization, 7));

        if ($plainToken === '') {
            return $this->unauthorized();
        }

        // Debug: Log the token format
        if (str_contains($plainToken, '|')) {
            // This is a Sanctum token format: "1|actual-token"
            $parts = explode('|', $plainToken);
            if (count($parts) === 2) {
                $plainToken = $parts[1]; // Get the actual token part
            }
        }

        $hash = hash('sha256', $plainToken);

        $record = PersonalAccessToken::where('token', $hash)->first();

        if (! $record) {
            return $this->unauthorized();
        }

        $user = User::find($record->tokenable_id);

        if (! $user) {
            return $this->unauthorized();
        }

        $request->setUserResolver(fn () => $user);
        auth()->setUser($user);
        $request->attributes->set('personal_access_token_id', $record->getKey());

        return $next($request);
    }

    private function unauthorized(): JsonResponse
    {
        return response()->json([
            'message' => 'Unauthenticated.',
        ], Response::HTTP_UNAUTHORIZED);
    }
}
