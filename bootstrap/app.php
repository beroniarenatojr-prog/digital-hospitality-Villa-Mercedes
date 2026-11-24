<?php

use App\Http\Middleware\EnsureAdministrator;
use App\Http\Middleware\PersistRememberedSession;
use App\Http\Middleware\ProtectPreviewEnvironment;
use App\Http\Middleware\SanitizeInput;
use App\Services\Admin\StaffSessionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'administrator' => EnsureAdministrator::class,
        ]);

        $middleware->web(prepend: [
            ProtectPreviewEnvironment::class,
            PersistRememberedSession::class,
            SanitizeInput::class,
        ]);

        // cloudflared reaches the preview through loopback. Trust forwarded
        // client/scheme headers only from that local connector, never from an
        // arbitrary network peer.
        $middleware->trustProxies(
            at: ['127.0.0.1', '::1'],
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('admin') || $request->is('admin/*')) {
                return '/admin/login';
            }

            return '/login';
        });
        $middleware->redirectUsersTo('/dashboard');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            return $request->expectsJson();
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }
        });

        $exceptions->render(function (TooManyRequestsHttpException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Too many requests. Please try again later.'], 429);
            }
        });

        // The handler rewrites a CSRF mismatch into a 419 HttpException before
        // these callbacks run, so the status — not the original exception class —
        // is what identifies it here.
        $exceptions->render(function (HttpException $e, Request $request) {
            // Everywhere else a 419 is a real refusal and keeps its error page.
            // Logout is the exception, because the request is its own answer: a
            // token that no longer matches means the session behind it is gone
            // already — the tab sat past its lifetime, another tab signed in,
            // the database was reseeded — and "page expired" strands someone who
            // asked to leave on the inside, with no way out but a new tab.
            if ($e->getStatusCode() !== 419 || ! $request->is('logout', 'admin/logout')) {
                return null;
            }

            // A live authenticated session still needs CSRF protection. The
            // friendly redirect below is only for a tab whose session is gone.
            if ($request->user() !== null) {
                return null;
            }

            // The destination is read from the route, never inferred from a
            // header: a staff member pressing Log out has to land on the staff
            // door even when the session is gone and nothing else can say so.
            if ($request->is('admin/logout')) {
                // The audit line is written by the same service the ordinary
                // logout uses, so a shift that ended in an expired page is in
                // the record exactly like one that ended on time.
                app(StaffSessionService::class)->end(
                    $request,
                    $request->user(),
                    StaffSessionService::REASON_SESSION_EXPIRED,
                );

                return redirect()->route('admin.login')
                    ->with('status', 'Your session had already ended, so you are signed out. Sign in again to continue.');
            }

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/');
        });
    })->create();
