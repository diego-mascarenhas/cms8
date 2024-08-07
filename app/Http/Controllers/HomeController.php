<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Page;

class HomeController extends Controller
{
    public function index()
    {
        $page = Page::find(1);

        if ($page)
        {
            $gjsData = is_string($page->gjs_data) ? json_decode($page->gjs_data, true) : $page->gjs_data;
            
            if (isset($gjsData['components']) && $gjsData['components'] !== "[]")
            {
                return redirect()->route('home');
            }
        }

        return redirect()->route('dashboard');
    }
}