<?php

namespace App\Helpers;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class TokenHelper
{
    /**
     * Generate a signed token that works across different databases
     */
    public static function generateSignedToken(User $user, string $purpose = 'autologin', int $hoursValid = 24): string
    {
        $payload = [
            'user_id' => $user->id,
            'email' => $user->email,
            'exp' => now()->addHours($hoursValid)->timestamp,
            'iat' => now()->timestamp,
            'purpose' => $purpose,
        ];

        $jsonPayload = json_encode($payload);
        $signature = hash_hmac('sha256', $jsonPayload, config('app.key'));

        return base64_encode($jsonPayload.'|'.$signature);
    }

    /**
     * Validate a signed token and return the user if valid
     */
    public static function validateSignedToken(string $token): ?User
    {
        try
        {
            $decoded = base64_decode($token);
            if (! $decoded)
            {
                return null;
            }

            $parts = explode('|', $decoded, 2);
            if (count($parts) !== 2)
            {
                return null;
            }

            $payload = json_decode($parts[0], true);
            $signature = $parts[1];

            if (! $payload)
            {
                return null;
            }

            // Verify signature
            $expectedSignature = hash_hmac('sha256', $parts[0], config('app.key'));
            if (! hash_equals($expectedSignature, $signature))
            {
                Log::warning('Invalid token signature');

                return null;
            }

            // Check expiration
            if (! isset($payload['exp']) || $payload['exp'] < time())
            {
                Log::info('Token expired');

                return null;
            }

            // Find user by email (works across databases)
            $user = User::where('email', $payload['email'])->first();
            if (! $user || $user->id != $payload['user_id'])
            {
                Log::warning('User not found or ID mismatch', ['email' => $payload['email']]);

                return null;
            }

            Log::info('Signed token validated successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'purpose' => $payload['purpose'] ?? 'unknown',
            ]);

            return $user;
        } catch (\Exception $e)
        {
            Log::error('Error validating signed token: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Get token payload without validation (for debugging)
     */
    public static function getTokenPayload(string $token): ?array
    {
        try
        {
            $decoded = base64_decode($token);
            if (! $decoded)
            {
                return null;
            }

            $parts = explode('|', $decoded, 2);
            if (count($parts) !== 2)
            {
                return null;
            }

            return json_decode($parts[0], true);
        } catch (\Exception $e)
        {
            return null;
        }
    }
}
