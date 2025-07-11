<?php

namespace App\Http\Controllers\authentications;

use App\Http\Controllers\Controller;

class VerifyEmailCover extends Controller
{
    public function index()
    {
        $pageConfigs = ['myLayout' => 'blank'];

        return view('content.authentications.auth-verify-email-cover', ['pageConfigs' => $pageConfigs]);
    }
}
