<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: '',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api([
            \App\Http\Middleware\IdempotencyMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Register renderable for SlotCapacityException
        $exceptions->renderable(function (\App\Exceptions\SlotCapacityException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'slot_id' => $e->getSlotId() ?? null,
                'remaining' => 0,
            ], 409);
        });

        // You can add more exception handlers here if needed
        // $exceptions->renderable(function (\InvalidArgumentException $e) {
        //     return response()->json([
        //         'error' => $e->getMessage(),
        //     ], 400);
        // });
    })->create();
