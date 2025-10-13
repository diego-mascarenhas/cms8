<?php

namespace App\Http\Controllers\pages;

use App\Http\Controllers\Controller;

class AccountSettingsSecurity extends Controller
{
    public function index()
    {
        return view('content.pages.pages-account-settings-security');
    }
}
