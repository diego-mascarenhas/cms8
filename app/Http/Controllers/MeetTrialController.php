<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MeetTrialController extends Controller
{
    public function show(Request $request, ?string $room = null): RedirectResponse
    {
        $base = rtrim((string) config('services.revisionalpha.meet_url'), '/');
        $query = [];
        $name = $this->displayName($request->query('nombre'));

        if ($name !== '')
        {
            $query['nombre'] = $name;
        }

        if ($room === null)
        {
            return redirect()->away($this->withQuery($base, $query));
        }

        $normalized = $this->normalizeRoom($room);

        if ($normalized === null)
        {
            return redirect()->away($this->withQuery($base, $query));
        }

        return redirect()->away($this->withQuery($base.'/'.$normalized, $query));
    }

    /**
     * @param  array<string, string>  $query
     */
    private function withQuery(string $url, array $query): string
    {
        if ($query === [])
        {
            return $url;
        }

        return $url.'?'.http_build_query($query);
    }

    private function normalizeRoom(string $room): ?string
    {
        $room = strtolower(trim($room));

        if (! preg_match('/^[a-z0-9-]{3,80}$/', $room))
        {
            return null;
        }

        return $room;
    }

    private function displayName(mixed $name): string
    {
        $name = trim((string) $name);
        $name = preg_replace('/\s+/', ' ', $name) ?? '';

        if ($name === '')
        {
            return '';
        }

        return mb_substr($name, 0, 60);
    }
}
