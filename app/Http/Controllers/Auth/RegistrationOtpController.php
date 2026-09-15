<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendRegistrationOtpRequest;
use App\Http\Requests\Auth\VerifyRegistrationOtpRequest;
use App\Services\RegistrationOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RegistrationOtpController extends Controller
{
    public function __construct(
        private readonly RegistrationOtpService $registrationOtpService,
    ) {}

    public function send(SendRegistrationOtpRequest $request): RedirectResponse
    {
        $this->registrationOtpService->send(
            $request->validated('email'),
            (string) $request->ip(),
        );

        return back()->with('status', RegistrationOtpService::ELIGIBLE_STATUS);
    }

    public function verify(VerifyRegistrationOtpRequest $request): RedirectResponse
    {
        $this->registrationOtpService->verify(
            $request->validated('email'),
            $request->validated('otp'),
            (string) $request->ip(),
        );

        return back()->with('status', 'Email verified.');
    }

    public function reset(Request $request): RedirectResponse
    {
        $this->registrationOtpService->reset();

        return redirect()->route('register');
    }
}
