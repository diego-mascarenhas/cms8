<?php

namespace App\Http\Controllers\extended_ui;

use App\Http\Controllers\Controller;

class MediaPlayer extends Controller
{
    public function index()
    {
        return view('content.extended-ui.extended-ui-media-player');
    }
}
