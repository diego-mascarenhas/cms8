<?php

namespace App\Http\Controllers;

use App\Models\Communication;
use Illuminate\Http\Response;

class CommunicationTrackingController extends Controller
{
    public function open(string $token): Response
    {
        $communication = Communication::findByTrackingToken($token);
        if ($communication)
        {
            $communication->markOpened();
        }

        $pixel = base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==');

        return response($pixel, 200, [
            'Content-Type' => 'image/gif',
            'Content-Length' => (string) strlen($pixel),
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
