<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MeetTrialController extends Controller
{
    public const DOMAIN = 'meet.jit.si';

    public const DEFAULT_ROOM = 'idoneo-cms8-prueba';

    public function show(Request $request, ?string $room = null): View|RedirectResponse
    {
        if ($room !== null)
        {
            $normalized = $this->normalizeRoom($room);

            if ($normalized === null)
            {
                return redirect()->route('meet.trial', $request->query());
            }

            if ($normalized !== $room)
            {
                return redirect()->route('meet.trial', array_merge(
                    ['room' => $normalized],
                    $request->query(),
                ));
            }
        }

        $roomName = $room ?? self::DEFAULT_ROOM;

        return view('meet.trial', [
            'domain' => self::DOMAIN,
            'roomName' => $roomName,
            'displayName' => $this->displayName($request->query('nombre')),
            'shareUrl' => route('meet.trial', ['room' => $roomName]),
            'directUrl' => 'https://'.self::DOMAIN.'/'.$roomName,
        ]);
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
