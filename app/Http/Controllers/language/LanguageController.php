<?php

namespace App\Http\Controllers\language;

use App\Http\Controllers\Controller;
use App\Support\ApplicationLocales;
use Illuminate\Support\Facades\App;

class LanguageController extends Controller
{
    public function swap(string $locale)
    {
        $locale = ApplicationLocales::normalize($locale);

        if (! ApplicationLocales::isSupported($locale))
        {
            abort(400);
        }

        session()->put('locale', $locale);
        App::setLocale($locale);

        return redirect()->back();
    }
}
