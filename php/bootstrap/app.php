<?php

use Apiato\Foundation\Apiato;
use App\Providers\NovaServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$basePath = dirname(__DIR__);
$apiato = Apiato::configure(basePath: $basePath)->create();

return Application::configure(basePath: $basePath)
    ->withProviders([...$apiato->providers(), NovaServiceProvider::class])
    ->withEvents($apiato->events())
    ->withRouting(
        web: $apiato->webRoutes(),
        health: '/up',
    )
    ->withMiddleware(static function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(static fn (): string => url(config('nova.path').'/login'));
    })
    ->withCommands($apiato->commands())
    ->withExceptions(static function (Exceptions $exceptions): void {})
    ->create();
