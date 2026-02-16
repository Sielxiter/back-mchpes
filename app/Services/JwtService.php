<?php

namespace App\Services;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use RuntimeException;
use Symfony\Component\HttpFoundation\Cookie;

class JwtService
{
    public function issueToken(User $user): string
    {
        $now = time();
        $ttlMinutes = (int) config('jwt.ttl_minutes');

        $payload = [
            'iss' => config('app.url'),
            'sub' => $user->id,
            'role' => $user->role,
            'iat' => $now,
            'exp' => $now + ($ttlMinutes * 60),
        ];

        return JWT::encode($payload, $this->secret(), 'HS256');
    }

    public function decode(string $token): object
    {
        return JWT::decode($token, new Key($this->secret(), 'HS256'));
    }

    public function makeCookie(string $token): Cookie
    {
        return cookie(
            config('jwt.cookie_name'),
            $token,
            (int) config('jwt.ttl_minutes'),
            config('jwt.cookie_path', '/'),
            config('jwt.cookie_domain'),
            (bool) config('jwt.cookie_secure', false),
            true,
            false,
            config('jwt.cookie_same_site', 'lax')
        );
    }

    public function forgetCookie(): Cookie
    {
        return cookie(
            config('jwt.cookie_name'),
            '',
            -1,
            config('jwt.cookie_path', '/'),
            config('jwt.cookie_domain'),
            (bool) config('jwt.cookie_secure', false),
            true,
            false,
            config('jwt.cookie_same_site', 'lax')
        );
    }

    private function secret(): string
    {
        $secret = (string) config('jwt.secret');

        if ($secret === '') {
            throw new RuntimeException('JWT_SECRET is not set.');
        }

        return $secret;
    }
}
