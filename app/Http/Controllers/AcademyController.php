<?php

namespace App\Http\Controllers;

class AcademyController extends Controller
{
    public function index()
    {
        return view('academy.index');
    }

    public function show($id)
    {
        return view('academy.show');
    }
}
