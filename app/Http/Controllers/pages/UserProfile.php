<?php

namespace App\Http\Controllers\pages;

use App\Http\Controllers\Controller;

class UserProfile extends Controller
{
    public function index()
    {
        return view('content.pages.pages-profile-user');
    }
}
