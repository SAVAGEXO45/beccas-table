<?php

use App\Http\Middleware\ActiveAccount;
use App\Http\Middleware\ResponseHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(ResponseHeaders::class);
        $middleware->redirectGuestsTo(fn (Request $request) =>
            route($request->routeIs('admin.*') ? 'admin.login' : 'login'));
        $middleware->web(append: [ActiveAccount::class]);
        $middleware->validateCsrfTokens(except: ['payments/paystack/webhook']);
    })->withExceptions(function (Exceptions $exceptions) {
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            if ($response->getStatusCode() !== 419 || $request->expectsJson()) {
                return $response;
            }

            if ($request->routeIs('logout')) {
                $redirect = to_route('home', status: 303);

                return $request->user() ? $redirect->with('status', 'Please select Sign out again to end your session.') : $redirect;
            }

            $previous = $request->headers->get('referer', '');
            $fallback = match (true) {
                $request->is('admin/login') => 'admin.login',
                $request->is('admin/*') => 'admin.dashboard',
                $request->is('login', 'register', 'forgot-password', 'reset-password') => 'login',
                default => 'home',
            };
            $destination = str_starts_with($previous, $request->getSchemeAndHttpHost().'/') ? $previous : route($fallback);

            return redirect()->to($destination, 303)
                ->with('status', 'The form was refreshed for your security. Please enter your changes again.');
        });
    })->create();

