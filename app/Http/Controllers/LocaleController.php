<?php

namespace App\Http\Controllers;

class LocaleController extends Controller
{
    public function switch(string $locale)
    {
        abort_unless(in_array($locale, ['en', 'tr']), 404);

        session(['locale' => $locale]);

        return redirect()->back();
    }
}