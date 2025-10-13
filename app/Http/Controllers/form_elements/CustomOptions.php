<?php

namespace App\Http\Controllers\form_elements;

use App\Http\Controllers\Controller;

class CustomOptions extends Controller
{
    public function index()
    {
        return view('content.form-elements.forms-custom-options');
    }
}
