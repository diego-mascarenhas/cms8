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
}
