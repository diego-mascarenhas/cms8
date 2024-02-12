<?php

namespace App\Http\Controllers\sys;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class Webhooks extends Controller
{
  public function index()
  {
    return view('cms.webhooks');
  }
}