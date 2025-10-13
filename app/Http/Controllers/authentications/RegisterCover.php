<?php

namespace App\Http\Controllers\authentications;

use App\Http\Controllers\Controller;

class RegisterCover extends Controller
{
    public function index()
    {
        $pageConfigs = ['myLayout' => 'blank'];

        return view('content.authentications.auth-register-cover', ['pageConfigs' => $pageConfigs]);
    }
}
