<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class GoogleAuthController extends Controller
{
    public function signIn(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'credential' => ['required', 'string', 'max:10000'],
        ]);

        $clientId = (string) config('services.google.client_id');
        if ($clientId === '') {
            return response()->json([
                'success' => false,
                'message' => 'Google sign-in is not configured on the server.',
            ], 503);
        }

        try {
            // Verify the ID token with Google's tokeninfo endpoint. Never trust
            // a name/email sent separately by the browser.
            $googleResponse = Http::timeout(10)->get(
                'https://oauth2.googleapis.com/tokeninfo',
                ['id_token' => $validated['credential']]
            );

            if (!$googleResponse->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Google could not verify this sign-in. Please try again.',
                ], 401);
            }

            $claims = $googleResponse->json();
            $googleId = (string) ($claims['sub'] ?? '');
            $email = strtolower(trim((string) ($claims['email'] ?? '')));
            $name = trim((string) ($claims['name'] ?? ''));
            $audience = (string) ($claims['aud'] ?? '');
            $emailVerified = filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if ($googleId === '' || $email === '' || $audience !== $clientId || !$emailVerified) {
                return response()->json([
                    'success' => false,
                    'message' => 'Google account verification failed.',
                ], 401);
            }

            $user = User::where('google_id', $googleId)->first();

            if (!$user) {
                // Never silently convert an existing staff/admin account into a
                // public account merely because the same email used Google.
                $existingEmail = User::whereRaw('LOWER(email) = ?', [$email])->first();
                if ($existingEmail && strcasecmp((string) $existingEmail->role, 'Public User') !== 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This email is already registered as a management account. Please use the regular login.',
                    ], 409);
                }

                if ($existingEmail) {
                    $user = $existingEmail;
                    $user->google_id = $googleId;
                    $user->email_verified_at = now();
                    $user->save();
                } else {
                    $user = User::create([
                        'name' => $name !== '' ? $name : Str::before($email, '@'),
                        'email' => $email,
                        'google_id' => $googleId,
                        'password' => Str::random(64),
                        'role' => 'Public User',
                        'status' => 'Active',
                        'email_verified_at' => now(),
                    ]);
                }
            }

            if (strcasecmp((string) $user->role, 'Public User') !== 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'This Google account is not a public-user account.',
                ], 403);
            }

            if (isset($user->status) && strcasecmp((string) $user->status, 'Active') !== 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'This account is inactive. Please contact the administrator.',
                ], 403);
            }

            $user->forceFill(['last_login' => now()])->save();
            $token = $user->createToken('google-public-session')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Google sign-in successful.',
                'user' => $user->fresh(),
                'token' => $token,
            ]);
        } catch (Throwable $exception) {
            report($exception);
            return response()->json([
                'success' => false,
                'message' => 'Google sign-in is temporarily unavailable. Please try again.',
            ], 502);
        }
    }
}
