<?php

namespace App\Http\Controllers\apps;

use App\Http\Controllers\Controller;

class UserViewNotifications extends Controller
{
    public function index()
    {
        return view('content.apps.app-user-view-notifications');
    }
}
