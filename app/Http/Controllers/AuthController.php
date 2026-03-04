<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\JwtService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
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

    public function redirectToGoogle(Request $request)
    {
        $mobileRedirect = $request->query('mobile_redirect');

        if (is_string($mobileRedirect) && $mobileRedirect !== '') {
            $validatedRedirect = $this->validateMobileRedirect($mobileRedirect);

            if (!$validatedRedirect) {
                return response()->json(['message' => 'Invalid mobile redirect URI.'], 422);
            }

            $statePayload = base64_encode(json_encode([
                'mobile_redirect' => $validatedRedirect,
            ]));

            return Socialite::driver('google')
                ->stateless()
                ->with(['state' => $statePayload])
                ->redirect();
        }

        return Socialite::driver('google')->stateless()->redirect();
    }

    public function handleGoogleCallback(Request $request, JwtService $jwt)
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
        $mobileRedirect = $this->extractMobileRedirectFromState($request->query('state'));

        if ($mobileRedirect) {
            $separator = str_contains($mobileRedirect, '?') ? '&' : '?';
            $redirectUrl = $mobileRedirect.$separator.http_build_query([
                'token' => $token,
                'cookie_name' => config('jwt.cookie_name'),
            ]);

            return redirect()->away($redirectUrl);
        }

        $cookie = $jwt->makeCookie($token);
        $redirectUrl = rtrim(config('jwt.frontend_url'), '/').'/candidat';

        return redirect($redirectUrl)->withCookies([$cookie]);
    }

    private function validateMobileRedirect(string $redirect): ?string
    {
        $validator = Validator::make(['mobile_redirect' => $redirect], [
            'mobile_redirect' => ['required', 'url'],
        ]);

        if ($validator->fails()) {
            return null;
        }

        $allowedPrefixes = config('jwt.mobile_oauth_allowed_redirect_prefixes', []);
        foreach ($allowedPrefixes as $prefix) {
            if (is_string($prefix) && $prefix !== '' && str_starts_with($redirect, $prefix)) {
                return $redirect;
            }
        }

        return null;
    }

    private function extractMobileRedirectFromState(mixed $state): ?string
    {
        if (!is_string($state) || $state === '') {
            return null;
        }

        $decoded = base64_decode($state, true);
        if ($decoded === false) {
            return null;
        }

        $payload = json_decode($decoded, true);
        if (!is_array($payload) || !isset($payload['mobile_redirect']) || !is_string($payload['mobile_redirect'])) {
            return null;
        }

        return $this->validateMobileRedirect($payload['mobile_redirect']);
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
