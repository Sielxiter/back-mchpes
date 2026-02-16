<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\JwtService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class AuthController extends Controller
{
    public function login(Request $request, JwtService $jwt)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        $token = $jwt->issueToken($user);

        return response()
            ->json(['user' => $this->userPayload($user)])
            ->cookie($jwt->makeCookie($token));
    }

    public function logout(JwtService $jwt)
    {
        return response()
            ->json(['success' => true])
            ->cookie($jwt->forgetCookie());
    }

    public function me(Request $request)
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    public function redirectToGoogle()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    public function handleGoogleCallback(JwtService $jwt)
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (Throwable $exception) {
            Log::warning('Google OAuth failed', [
                'error' => $exception->getMessage(),
                'type' => $exception::class,
            ]);
            return response()->json(['message' => 'Google authentication failed.'], 401);
        }

        $email = strtolower((string) $googleUser->getEmail());

        if ($email === '' || !str_ends_with($email, '@uca.ac.ma')) {
            return response()->json(['message' => 'Only @uca.ac.ma emails are allowed.'], 403);
        }

        $user = User::query()->where('email', $email)->first();

        if ($user && $user->role !== User::ROLE_CANDIDAT) {
            return response()->json(['message' => 'Google login is only available for role Candidat.'], 403);
        }

        if (!$user) {
            $user = User::query()->create([
                'name' => $googleUser->getName() ?: 'Candidat',
                'email' => $email,
                'password' => Str::random(32),
                'role' => User::ROLE_CANDIDAT,
                'email_verified_at' => now(),
            ]);
        }

        $token = $jwt->issueToken($user);
        $cookie = $jwt->makeCookie($token);
        $redirectUrl = rtrim(config('jwt.frontend_url'), '/').'/candidat';

        return redirect($redirectUrl)->withCookies([$cookie]);
    }

    private function userPayload(?User $user): array
    {
        if (!$user) {
            return [];
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ];
    }
}
