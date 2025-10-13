<?php

namespace App\Http\Controllers\form_validation;

use App\Http\Controllers\Controller;

class Validation extends Controller
{
    public function index()
    {
        return view('content.form-validation.form-validation');
    }
}
