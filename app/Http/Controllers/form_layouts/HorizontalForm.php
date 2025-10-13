<?php

namespace App\Http\Controllers\form_layouts;

use App\Http\Controllers\Controller;

class HorizontalForm extends Controller
{
    public function index()
    {
        return view('content.form-layout.form-layouts-horizontal');
    }
}
