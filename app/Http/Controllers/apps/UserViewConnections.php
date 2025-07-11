<?php

namespace App\Http\Controllers\apps;

use App\Http\Controllers\Controller;

class UserViewConnections extends Controller
{
    public function index()
    {
        return view('content.apps.app-user-view-connections');
    }
}
