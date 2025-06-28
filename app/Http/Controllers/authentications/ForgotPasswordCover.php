<?php

namespace App\Http\Controllers\authentications;

use App\Http\Controllers\Controller;

class ForgotPasswordCover extends Controller
{
    public function index()
    {
        $pageConfigs = ['myLayout' => 'blank'];

        return view('content.authentications.auth-forgot-password-cover', ['pageConfigs' => $pageConfigs]);
    }
}
