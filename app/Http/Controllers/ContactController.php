<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactRequest;
use App\Mail\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function show(): View
    {
        return view('shop.contact', [
            'formStartedAt' => Crypt::encryptString((string) now()->timestamp),
        ]);
    }

    public function send(ContactRequest $request): RedirectResponse
    {
        $successMessage = 'Merci pour votre message, nous vous répondrons très vite.';

        if ($request->isLikelyBot()) {
            return redirect()->route('shop.contact')->with('success', $successMessage);
        }

        Mail::to(config('contact.recipients'))->send(new ContactMessage(
            senderName: $request->validated('name'),
            senderEmail: $request->validated('email'),
            body: $request->validated('message'),
        ));

        return redirect()->route('shop.contact')->with('success', $successMessage);
    }
}
