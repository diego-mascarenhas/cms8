<?php

namespace App\Http\Controllers\authentications;

use App\Http\Controllers\Controller;

class TwoStepsCover extends Controller
{
    public function index()
    {
        $pageConfigs = ['myLayout' => 'blank'];

        return view('content.authentications.auth-two-steps-cover', ['pageConfigs' => $pageConfigs]);
    }
}
