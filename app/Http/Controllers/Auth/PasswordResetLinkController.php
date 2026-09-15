<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Support\OutboundMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;
use Throwable;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(ForgotPasswordRequest $request): RedirectResponse
    {
        if (! OutboundMail::isConfigured()) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'Email sending is not configured yet. Add SMTP credentials in the .env file.']);
        }

        try {
            $status = Password::sendResetLink(
                $request->only('email')
            );
        } catch (Throwable $e) {
            report($e);

            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'We could not send the reset email. Please try again or call the resort.']);
        }

        if ($status !== Password::RESET_LINK_SENT) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => __($status)]);
        }

        return back()
            ->withInput($request->only('email'))
            ->with('status', 'We emailed a password reset link. Check your inbox and spam folder.');
    }
}
