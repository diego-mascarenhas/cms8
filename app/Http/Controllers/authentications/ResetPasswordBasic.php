<?php

namespace App\Http\Controllers\authentications;

use App\Http\Controllers\Controller;

class ResetPasswordBasic extends Controller
{
    public function index()
    {
        $pageConfigs = ['myLayout' => 'blank'];

        return view('content.authentications.auth-reset-password-basic', ['pageConfigs' => $pageConfigs]);
    }
}
