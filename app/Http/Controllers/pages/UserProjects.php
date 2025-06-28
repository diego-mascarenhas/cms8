<?php

namespace App\Http\Controllers\pages;

use App\Http\Controllers\Controller;

class UserProjects extends Controller
{
    public function index()
    {
        return view('content.pages.pages-profile-projects');
    }
}
