<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LegalDocumentsController extends Controller
{
    public function terms()
    {
        $configData = config('variables');
        return view('legal.terms', compact('configData'));
    }

    public function privacy()
    {
        $configData = config('variables');
        return view('legal.privacy', compact('configData'));
    }

    public function security()
    {
        $configData = config('variables');
        return view('legal.security', compact('configData'));
    }

    public function sla()
    {
        $configData = config('variables');
        return view('legal.sla', compact('configData'));
    }

    public function show($document)
    {
        $configData = config('variables');
        return view('legal.' . $document, compact('configData'));
    }
}
