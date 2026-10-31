<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class LegalController extends Controller
{
    /**
     * Display the Privacy Policy view.
     */
    public function privacy(): View
    {
        return view('legal.privacy');
    }

    /**
     * Display the Terms of Service view.
     */
    public function terms(): View
    {
        return view('legal.terms');
    }
}
