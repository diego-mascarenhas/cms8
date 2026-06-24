<?php

namespace Tests\Unit;

use App\Support\LandingYouTube;
use Tests\TestCase;

class LandingYouTubeTest extends TestCase
{
    public function test_url_uses_default_onboarding_playlist(): void
    {
        config([
            'slash_landing.youtube_onboarding_playlist_url' => 'https://www.youtube.com/playlist?list=PLebHHjcT7KEc',
            'slash_landing.youtube_onboarding_playlist_id' => '',
            'slash_landing.youtube_channel_url' => 'https://www.youtube.com/@revisionalpha',
        ]);

        $this->assertSame('https://www.youtube.com/playlist?list=PLebHHjcT7KEc', LandingYouTube::url());
    }

    public function test_url_returns_channel_when_playlist_is_not_configured(): void
    {
        config([
            'slash_landing.youtube_onboarding_playlist_url' => '',
            'slash_landing.youtube_channel_url' => 'https://www.youtube.com/@revisionalpha',
        ]);

        $this->assertSame('https://www.youtube.com/@revisionalpha', LandingYouTube::url());
    }

    public function test_url_prefers_playlist_over_channel(): void
    {
        config([
            'slash_landing.youtube_onboarding_playlist_url' => 'https://www.youtube.com/playlist?list=PL123',
            'slash_landing.youtube_onboarding_playlist_id' => '',
            'slash_landing.youtube_channel_url' => 'https://www.youtube.com/@revisionalpha',
        ]);

        $this->assertSame('https://www.youtube.com/playlist?list=PL123', LandingYouTube::url());
    }

    public function test_url_builds_playlist_from_id_when_url_is_not_set(): void
    {
        config([
            'slash_landing.youtube_onboarding_playlist_url' => '',
            'slash_landing.youtube_onboarding_playlist_id' => 'PLabc123',
            'slash_landing.youtube_channel_url' => 'https://www.youtube.com/@revisionalpha',
        ]);

        $this->assertSame('https://www.youtube.com/playlist?list=PLabc123', LandingYouTube::url());
    }

    public function test_normalize_playlist_url_accepts_raw_playlist_id(): void
    {
        $this->assertSame(
            'https://www.youtube.com/playlist?list=PLabc123',
            LandingYouTube::normalizePlaylistUrl('PLabc123'),
        );
    }

    public function test_url_returns_null_when_both_urls_are_empty(): void
    {
        config([
            'slash_landing.youtube_onboarding_playlist_url' => '',
            'slash_landing.youtube_channel_url' => '',
        ]);

        $this->assertNull(LandingYouTube::url());
    }
}
