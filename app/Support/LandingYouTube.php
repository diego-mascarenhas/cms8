<?php

namespace App\Support;

class LandingYouTube
{
    public static function url(): ?string
    {
        $playlistUrl = trim((string) config('slash_landing.youtube_onboarding_playlist_url'));

        if ($playlistUrl !== '')
        {
            return self::normalizePlaylistUrl($playlistUrl);
        }

        $playlistId = trim((string) config('slash_landing.youtube_onboarding_playlist_id'));

        if ($playlistId !== '')
        {
            return 'https://www.youtube.com/playlist?list='.$playlistId;
        }

        $channelUrl = trim((string) config('slash_landing.youtube_channel_url'));

        return $channelUrl !== '' ? $channelUrl : null;
    }

    public static function normalizePlaylistUrl(string $value): string
    {
        $value = trim($value);

        if (str_starts_with($value, 'PL'))
        {
            return 'https://www.youtube.com/playlist?list='.$value;
        }

        if (preg_match('/[?&]list=(PL[^&]+)/', $value, $matches) === 1)
        {
            return 'https://www.youtube.com/playlist?list='.$matches[1];
        }

        return $value;
    }

    public static function thumbnailUrl(string $videoId): string
    {
        return 'https://i.ytimg.com/vi/'.trim($videoId).'/hqdefault.jpg';
    }

    public static function playlistId(): ?string
    {
        $playlistUrl = self::url();

        if ($playlistUrl === null)
        {
            return null;
        }

        if (preg_match('/[?&]list=(PL[^&]+)/', $playlistUrl, $matches) === 1)
        {
            return $matches[1];
        }

        $playlistId = trim((string) config('slash_landing.youtube_onboarding_playlist_id'));

        return $playlistId !== '' ? $playlistId : null;
    }

    public static function watchUrl(string $videoId): string
    {
        $videoId = trim($videoId);
        $playlistId = self::playlistId();

        if ($playlistId === null)
        {
            return 'https://www.youtube.com/watch?v='.$videoId;
        }

        return 'https://www.youtube.com/watch?v='.$videoId.'&list='.$playlistId;
    }

    /**
     * @return list<array{youtube_id: string, title: string, subtitle: string, poster: string}>
     */
    public static function featuredVideos(): array
    {
        $videos = config('slash_landing.onboarding_featured_videos', []);

        if (! is_array($videos))
        {
            return [];
        }

        $featured = [];

        foreach ($videos as $video)
        {
            if (! is_array($video))
            {
                continue;
            }

            $youtubeId = trim((string) ($video['youtube_id'] ?? ''));

            if ($youtubeId === '')
            {
                continue;
            }

            $featured[] = [
                'youtube_id' => $youtubeId,
                'title' => trim((string) ($video['title'] ?? '')),
                'subtitle' => trim((string) ($video['subtitle'] ?? '')),
                'poster' => trim((string) ($video['poster'] ?? 'plans/assistant.png')),
            ];
        }

        return $featured;
    }

    public static function showsFeaturedVideosSection(): bool
    {
        return (bool) config('slash_landing.show_plan_stories_section')
            && self::featuredVideos() !== [];
    }
}
