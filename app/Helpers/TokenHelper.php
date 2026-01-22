<?php

namespace App\Helpers;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TokenHelper
{
    /**
     * Generate a signed token that works across different databases
     */
    public static function generateSignedToken(User $user, string $purpose = 'autologin', int $hoursValid = 24): string
    {
        // Generate unique token ID (jti - JWT ID)
        $jti = bin2hex(random_bytes(16));

        $payload = [
            'user_id' => $user->id,
            'email' => $user->email,
            'exp' => now()->addHours($hoursValid)->timestamp,
            'iat' => now()->timestamp,
            'purpose' => $purpose,
            'jti' => $jti, // Unique token identifier for revocation
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

            // Check if token is revoked by jti (individual token)
            if (isset($payload['jti']))
            {
                $revokedKey = 'revoked_token_'.$payload['jti'];
                if (Cache::has($revokedKey))
                {
                    Log::warning('Token has been revoked', ['jti' => $payload['jti']]);

                    return null;
                }
            }

            // Check if user tokens have been revoked (timestamp-based)
            $userId = $payload['user_id'] ?? null;
            $purpose = $payload['purpose'] ?? 'autologin';
            if ($userId)
            {
                $revocationKey = "user_token_revocation_{$userId}_{$purpose}";
                $revocationTimestamp = Cache::get($revocationKey);
                if ($revocationTimestamp && isset($payload['iat']) && $payload['iat'] < $revocationTimestamp)
                {
                    Log::warning('Token invalidated by user revocation', [
                        'user_id' => $userId,
                        'purpose' => $purpose,
                        'token_issued' => $payload['iat'],
                        'revocation_time' => $revocationTimestamp,
                    ]);

                    return null;
                }
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

    /**
     * Revoke a token by its ID (jti)
     */
    public static function revokeToken(string $token): bool
    {
        try
        {
            $payload = self::getTokenPayload($token);
            if (! $payload || ! isset($payload['jti']))
            {
                Log::warning('Cannot revoke token: invalid token or missing jti');

                return false;
            }

            // Calculate remaining time until expiration
            $expirationTime = $payload['exp'] ?? null;
            if ($expirationTime)
            {
                $remainingSeconds = max(0, $expirationTime - time());
                // Store in cache until token expires
                Cache::put('revoked_token_'.$payload['jti'], true, now()->addSeconds($remainingSeconds));
                Log::info('Token revoked successfully', [
                    'jti' => $payload['jti'],
                    'user_id' => $payload['user_id'] ?? null,
                    'email' => $payload['email'] ?? null,
                ]);

                return true;
            }

            return false;
        } catch (\Exception $e)
        {
            Log::error('Error revoking token: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Revoke all tokens for a specific user and purpose
     * Uses a timestamp-based approach: tokens issued before the revocation timestamp are invalid
     */
    public static function revokeUserTokens(int $userId, string $purpose = 'account_owner_autologin'): bool
    {
        try
        {
            $revocationKey = "user_token_revocation_{$userId}_{$purpose}";
            // Store revocation timestamp - tokens issued before this will be invalid
            Cache::put($revocationKey, now()->timestamp, now()->addDays(31)); // Store for 31 days (longer than token expiration)

            Log::info('User tokens revoked', [
                'user_id' => $userId,
                'purpose' => $purpose,
                'revocation_timestamp' => now()->timestamp,
            ]);

            return true;
        } catch (\Exception $e)
        {
            Log::error('Error revoking user tokens: '.$e->getMessage());

            return false;
        }
    }
}
