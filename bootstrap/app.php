<?php

use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetApiCompanyContext;
use App\Http\Middleware\SetCompanyContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['company' => SetCompanyContext::class, 'api-company' => SetApiCompanyContext::class]);
        $middleware->prependToPriorityList(SubstituteBindings::class, SetCompanyContext::class);
        $middleware->prependToPriorityList(SubstituteBindings::class, SetApiCompanyContext::class);
        $middleware->append(SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
