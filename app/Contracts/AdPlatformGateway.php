<?php

namespace App\Contracts;

use App\Enums\AdPlatform;
use App\Models\AdPlatformConnection;
use App\Models\PaidAdAudience;
use App\Models\PaidAdCampaignPlatform;
use App\Models\Team;
use App\Models\User;
use App\Services\Ads\DTO\AdCampaignResult;
use Carbon\CarbonInterface;

interface AdPlatformGateway
{
    /**
     * The platform this gateway handles.
     */
    public function platform(): AdPlatform;

    /**
     * Set the team whose (per-team) credentials should be used.
     */
    public function forTeam(?Team $team): static;

    /**
     * Whether the platform app credentials are configured on the server.
     */
    public function isConfigured(): bool;

    /**
     * Build the OAuth authorization URL to start the connect flow.
     */
    public function buildAuthorizationUrl(User $user): string;

    /**
     * Exchange the OAuth authorization code for tokens and persist a connection.
     */
    public function exchangeCode(User $user, string $authCode): AdPlatformConnection;

    /**
     * Refresh the access token for a connection when expired.
     */
    public function refreshToken(AdPlatformConnection $connection): void;

    /**
     * List the ad accounts available for the connected user.
     *
     * @return array<int, \App\Services\Ads\DTO\AdAccountDTO>
     */
    public function listAdAccounts(AdPlatformConnection $connection): array;

    /**
     * Publish (create) the campaign on the platform.
     */
    public function publish(PaidAdCampaignPlatform $campaignPlatform): AdCampaignResult;

    /**
     * Pause a published campaign on the platform.
     */
    public function pause(PaidAdCampaignPlatform $campaignPlatform): void;

    /**
     * Resume a paused campaign on the platform.
     */
    public function resume(PaidAdCampaignPlatform $campaignPlatform): void;

    /**
     * Fetch daily metrics for a published campaign in the given range.
     *
     * @return array<int, \App\Services\Ads\DTO\AdMetricsDTO>
     */
    public function getMetrics(PaidAdCampaignPlatform $campaignPlatform, CarbonInterface $from, CarbonInterface $to): array;

    /**
     * Create or update an audience on the platform, returning its external id.
     */
    public function syncAudience(AdPlatformConnection $connection, PaidAdAudience $audience): string;
}
