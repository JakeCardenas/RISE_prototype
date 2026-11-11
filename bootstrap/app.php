<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The browser writes only its time zone name here (see resources/js/rise/time-zone.js), so follow-up dates use the resident's own day
        $middleware->encryptCookies(except: ['rise_tz']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Where the browser doesn't confirm that a form came from RISE itself (older browsers, or RISE served over plain
        // HTTP on a local network), the form's security token decides, and it expires with the session. Such a form is
        // still refused, but the resident goes back to its page with what they typed and a short note, rather than to a
        // dead-end "Page expired" screen. Passwords are never put back. Requests from scripts keep the plain 419, which
        // the photo upload explains itself.
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419 || $request->expectsJson()) {
                return null;
            }

            return back()
                ->withInput($request->except(['_token', '_method', 'password', 'password_confirmation']))
                ->with('info', 'This page had been open too long, so nothing was saved or sent. It’s been refreshed; please try again.');
        });
    })->create();
