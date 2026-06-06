<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccessTokenNotExpired
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainToken = (string) $request->bearerToken();
        if ($plainToken !== '') {
            $token = PersonalAccessToken::findToken($plainToken);
            if ($token !== null && $token->expires_at !== null && $token->expires_at->isPast()) {
                $token->delete();

                return response()->json([
                    'message' => 'Unauthenticated.',
                    'request_id' => $request->attributes->get('request_id'),
                ], 401);
            }
        }

        return $next($request);
    }
}
