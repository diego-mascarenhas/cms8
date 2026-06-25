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

    public function test_thumbnail_url_uses_youtube_cdn(): void
    {
        $this->assertSame(
            'https://i.ytimg.com/vi/QXVZJUaBYh4/hqdefault.jpg',
            LandingYouTube::thumbnailUrl('QXVZJUaBYh4'),
        );
    }

    public function test_watch_url_includes_playlist_when_configured(): void
    {
        config([
            'slash_landing.youtube_onboarding_playlist_url' => 'https://www.youtube.com/playlist?list=PLebHHjcT7KEc',
        ]);

        $this->assertSame(
            'https://www.youtube.com/watch?v=QXVZJUaBYh4&list=PLebHHjcT7KEc',
            LandingYouTube::watchUrl('QXVZJUaBYh4'),
        );
    }

    public function test_featured_videos_returns_configured_onboarding_tutorials(): void
    {
        $videos = LandingYouTube::featuredVideos();

        $this->assertCount(3, $videos);
        $this->assertSame('QXVZJUaBYh4', $videos[0]['youtube_id']);
        $this->assertSame('Configuración del negocio', $videos[0]['title']);
        $this->assertSame('uju-eMnSiO0', $videos[1]['youtube_id']);
        $this->assertSame('luwXe0wu37E', $videos[2]['youtube_id']);
    }

    public function test_shows_featured_videos_section_when_enabled_and_videos_exist(): void
    {
        $this->assertTrue(LandingYouTube::showsFeaturedVideosSection());
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
