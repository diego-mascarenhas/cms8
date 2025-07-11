<?php

namespace App\Http\Controllers\authentications;

use App\Http\Controllers\Controller;

class VerifyEmailBasic extends Controller
{
    public function index()
    {
        $pageConfigs = ['myLayout' => 'blank'];

        return view('content.authentications.auth-verify-email-basic', ['pageConfigs' => $pageConfigs]);
    }
}
