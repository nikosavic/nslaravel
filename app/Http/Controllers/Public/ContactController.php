<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function show()
    {
        return view('public.contact');
    }

    public function submit(Request $request)
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:100'],
            'email'    => ['required', 'email', 'max:150'],
            'message'  => ['required', 'string', 'max:3000'],

            // Honeypot (should be hidden in the form)
            'website'  => ['nullable', 'string', 'max:100'],

            // Timing anti-bot (hidden input)
            'ts'       => ['nullable', 'integer'],

            // Turnstile token (optional)
            'cf_token' => ['nullable', 'string', 'max:5000'],
        ]);

        // 1) Honeypot: if filled, pretend success (spam bots)
        if (!empty($data['website'])) {
            return back()->with('success', 'Message sent!')->withInput();
        }

        // 2) Timing check: if submitted too fast, treat as spam
        $ts = (int) ($data['ts'] ?? 0);
        if ($ts > 0 && (time() - $ts) < 3) {
            return back()->with('success', 'Message sent!')->withInput();
        }

        // 3) Turnstile (recommended) - only enforced if secret is set
        if (config('services.turnstile.secret')) {
            $ok = $this->verifyTurnstile($data['cf_token'] ?? null, $request->ip());
            if (!$ok) {
                return back()
                    ->withErrors(['message' => 'Spam protection failed. Please try again.'])
                    ->withInput();
            }
        }

        // 4) Send email
        // Set CONTACT_TO_ADDRESS in .env (recommended), otherwise fallback to mail.from.address
        $to = config('mail.contact_to') ?: config('mail.from.address');


        try {
            Mail::raw(
                "Name: {$data['name']}\nEmail: {$data['email']}\nIP: {$request->ip()}\n\nMessage:\n{$data['message']}\n",
                function ($m) use ($data, $to) {
                    $m->to($to)
                      ->subject('Portfolio Contact: ' . $data['name'])
                      ->replyTo($data['email'], $data['name']);
                }
            );
        } catch (\Throwable $e) {
            Log::error('Contact form mail failed', [
                'error' => $e->getMessage(),
                'email' => $data['email'] ?? null,
            ]);

            return back()
                ->withErrors(['message' => 'Could not send right now. Please try again in a minute.'])
                ->withInput();
        }

        return back()->with('success', 'Message sent! I’ll get back to you soon.');
    }

    private function verifyTurnstile(?string $token, ?string $ip): bool
    {
        if (!$token) return false;

        $secret = config('services.turnstile.secret');
        if (!$secret) return false;

        $resp = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'secret'   => $secret,
            'response' => $token,
            'remoteip' => $ip,
        ]);

        return (bool) data_get($resp->json(), 'success', false);
    }
}