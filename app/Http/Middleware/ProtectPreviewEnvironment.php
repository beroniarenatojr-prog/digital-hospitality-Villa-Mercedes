<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProtectPreviewEnvironment
{
    /**
     * @var list<string>
     */
    private const DISABLED_ROUTES = [
        'register',
        'register.otp.send',
        'register.otp.verify',
        'register.otp.reset',
        'password.request',
        'password.email',
        'password.reset',
        'password.store',
        'verification.send',
        'customer.booking.resume.send',
        'customer.booking.magic-link.send',
        'customer.payments.store',
        'customer.payments.checkout',
        'customer.payments.return',
        'customer.payments.cancel',
        'customer.payments.view',
        'customer.payments.download',
        'payment-destinations.image',
        'admin.payment-destinations.index',
        'admin.payment-destinations.store',
        'admin.payment-destinations.toggle',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('preview.enabled')) {
            return $next($request);
        }

        if (config('preview.public_interface') && $this->isBackOfficeRequest($request)) {
            abort(404);
        }

        if ($request->routeIs(...self::DISABLED_ROUTES)) {
            return $this->disabledResponse($request);
        }

        $response = $next($request);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');

        return $response;
    }

    private function isBackOfficeRequest(Request $request): bool
    {
        return $request->is('admin', 'admin/*', 'payment-destinations/*')
            || $request->routeIs('admin.*', 'payment-destinations.*');
    }

    private function disabledResponse(Request $request): Response
    {
        $message = 'This action is disabled in the demonstration. Use the seeded guest account and synthetic records instead.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403)
                ->header('X-Robots-Tag', 'noindex, nofollow, noarchive');
        }

        return response()->view('errors.preview-disabled', compact('message'), 403)
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }
}
