<?php

namespace App\Http\Controllers\modal;

use App\Http\Controllers\Controller;

class ModalExample extends Controller
{
    public function index()
    {
        return view('content.modal.modal-examples');
    }
}
