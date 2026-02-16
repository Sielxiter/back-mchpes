<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class JwtAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) $request->cookie(config('jwt.cookie_name'));

        if ($token === '') {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        try {
            $payload = app(JwtService::class)->decode($token);
            $user = User::query()->find($payload->sub ?? null);

            if (!$user) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            $request->setUserResolver(fn () => $user);
        } catch (Throwable $exception) {
            return response()->json(['message' => 'Invalid token.'], 401);
        }

        return $next($request);
    }
}
