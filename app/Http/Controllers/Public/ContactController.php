<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function show()
    {
        return view('public.contact');
    }

    public function submit(Request $request)
    {
        $data = $request->validate([
            'name' => ['required','string','max:100'],
            'email' => ['required','email','max:150'],
            'message' => ['required','string','max:3000'],
            'company' => ['nullable','string','max:100'], // honeypot
        ]);

        // Honeypot: if filled, pretend success (spam)
        if (!empty($data['company'])) {
            return back()->with('success', 'Message sent!');
        }

        // For now: just success flash. Next step we’ll store to DB + email.
        return back()->with('success', 'Message sent! I’ll get back to you soon.');
    }
}
