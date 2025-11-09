<?php

use App\Http\Middleware\Customer\ValidateCustomerToken;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'customer.token' => ValidateCustomerToken::class,
        ]);
    })

    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['prefix' => 'api', 'middleware' => ['api', 'auth:sanctum']],
    )

    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return jsonResponse('Unauthenticated or invalid token', false, null, 401);
            }
        });

        // if needed later you can also handle api not found here
    })

    ->withSchedule(function (Schedule $schedule) {
        $schedule->call(function () {
            // app(\App\Services\Platforms\EmailService::class)->fetchUnreadEmails();
            Log::info('CRON START');

            $service = app(\App\Services\Platforms\EmailService::class);

            $service->fetchUnreadEmails();

            Log::info('CRON END');
        })->everyMinute();
    })

    ->create();
