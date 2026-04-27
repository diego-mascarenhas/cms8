<?php

namespace App\Http\Controllers;

class LegalDocumentsController extends Controller
{
    /**
     * Index of all public legal / policy documents.
     */
    public function index()
    {
        $configData = config('variables');

        return view('legal.index', compact('configData'));
    }

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

    public function cookies()
    {
        $configData = config('variables');

        return view('legal.cookies', compact('configData'));
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

    /**
     * Public application overview (OAuth verification “Application home page”).
     */
    public function application()
    {
        $configData = config('variables');

        return view('legal.application', compact('configData'));
    }

    /**
     * Open-source license summary (GNU AGPL-3) with link to the official license text.
     */
    public function license()
    {
        $configData = config('variables');

        return view('legal.license', compact('configData'));
    }

    /**
     * Optional Google OAuth integration (high-level overview for reviewers).
     */
    public function googleConnection()
    {
        $configData = config('variables');

        return view('legal.google-connection', compact('configData'));
    }

    /**
     * How Google user data is used (People + Calendar read-only) and Limited Use compliance.
     */
    public function googleUserData()
    {
        $configData = config('variables');

        return view('legal.google-user-data', compact('configData'));
    }

    /**
     * How users disconnect Google integration and request deletion of related data.
     */
    public function dataDeletion()
    {
        $configData = config('variables');

        return view('legal.data-deletion', compact('configData'));
    }
}
