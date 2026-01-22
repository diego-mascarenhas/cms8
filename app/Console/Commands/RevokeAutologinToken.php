<?php

namespace App\Console\Commands;

use App\Helpers\TokenHelper;
use Illuminate\Console\Command;

class RevokeAutologinToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'token:revoke
                            {token : The autologin token to revoke (can be full URL or just the token)}
                            {--info : Show token information before revoking}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Revoke an autologin token to prevent access';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tokenInput = $this->argument('token');

        // Extract token from URL if full URL is provided
        $token = $this->extractTokenFromUrl($tokenInput);

        if (! $token)
        {
            $this->error('Invalid token format. Please provide a valid token or URL.');

            return Command::FAILURE;
        }

        // Show token info if requested
        if ($this->option('info'))
        {
            $this->showTokenInfo($token);
        }

        // Revoke the token
        if (TokenHelper::revokeToken($token))
        {
            $this->info('✅ Token revoked successfully!');
            $this->line('The token can no longer be used for autologin.');

            return Command::SUCCESS;
        } else
        {
            $this->error('❌ Failed to revoke token. It may be invalid or already expired.');

            return Command::FAILURE;
        }
    }

    /**
     * Extract token from URL if full URL is provided
     */
    private function extractTokenFromUrl(string $input): ?string
    {
        // If it's a URL, extract the token part
        if (strpos($input, '/login/token/') !== false)
        {
            $parts = explode('/login/token/', $input);
            if (isset($parts[1]))
            {
                // Remove query parameters if any
                $token = explode('?', $parts[1])[0];

                return trim($token);
            }
        }

        // If it looks like a base64 token, return as is
        if (base64_decode($input, true) !== false)
        {
            return trim($input);
        }

        return null;
    }

    /**
     * Show token information
     */
    private function showTokenInfo(string $token): void
    {
        $payload = TokenHelper::getTokenPayload($token);

        if (! $payload)
        {
            $this->warn('Could not decode token information.');

            return;
        }

        $this->line('');
        $this->info('Token Information:');
        $this->line('───────────────────');
        $this->line('User ID: '.($payload['user_id'] ?? 'N/A'));
        $this->line('Email: '.($payload['email'] ?? 'N/A'));
        $this->line('Purpose: '.($payload['purpose'] ?? 'N/A'));
        $this->line('Token ID (jti): '.($payload['jti'] ?? 'N/A (old token format)'));
        if (isset($payload['exp']))
        {
            $expirationDate = date('Y-m-d H:i:s', $payload['exp']);
            $this->line('Expires: '.$expirationDate);
        }
        if (isset($payload['iat']))
        {
            $issuedDate = date('Y-m-d H:i:s', $payload['iat']);
            $this->line('Issued: '.$issuedDate);
        }
        $this->line('');
    }
}
