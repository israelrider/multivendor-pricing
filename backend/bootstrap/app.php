<?php

use App\Domain\Orders\IdempotencyKeyReusedException;
use App\Domain\Orders\QuoteChangedException;
use App\Domain\Pricing\UnpriceableProductException;
use App\Http\Resources\QuoteResource;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Expected business outcomes, answered with a 4xx. They are not failures, so they stay out of laravel.log.
        $exceptions->dontReport([
            UnpriceableProductException::class,
            QuoteChangedException::class,
            IdempotencyKeyReusedException::class,
        ]);

        // A cart product that can't be priced is the client's problem, not a server error.
        $exceptions->render(fn (UnpriceableProductException $e) => response()->json([
            'message' => $e->getMessage(),
            'product_id' => $e->productId,
        ], 422));

        // The reviewed total is stale: send the fresh quote so the client can show it right away.
        $exceptions->render(fn (QuoteChangedException $e, Request $request) => response()->json([
            'message' => $e->getMessage(),
            'expected_total_cents' => $e->expectedTotalCents,
            'quote' => (new QuoteResource($e->quote))->resolve($request),
        ], 409));

        // The key already placed a different order: neither replaying it nor placing a new one is right.
        $exceptions->render(fn (IdempotencyKeyReusedException $e) => response()->json([
            'message' => $e->getMessage(),
        ], 422));
    })->create();
