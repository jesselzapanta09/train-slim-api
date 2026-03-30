<?php

declare(strict_types=1);

use Slim\App;
use App\Controllers\AuthController;
use App\Controllers\TrainController;
use App\Middleware\AuthMiddleware;

return function (App $app): void {

    // ── Auth Routes ───────────────────────────────────────────────
    $app->post('/api/register', [AuthController::class, 'register']);
    $app->post('/api/login',    [AuthController::class, 'login']);
    $app->post('/api/logout',   [AuthController::class, 'logout'])
        ->add(new AuthMiddleware());

    // ── Train Routes ──────────────────────────────────────────────
    $app->get('/api/trains',         [TrainController::class, 'index'])
        ->add(new AuthMiddleware());
    $app->get('/api/trains/{id}',    [TrainController::class, 'show'])
        ->add(new AuthMiddleware());
    $app->post('/api/trains',        [TrainController::class, 'store'])
        ->add(new AuthMiddleware());
    $app->put('/api/trains/{id}',    [TrainController::class, 'update'])
        ->add(new AuthMiddleware());
    $app->delete('/api/trains/{id}', [TrainController::class, 'destroy'])
        ->add(new AuthMiddleware());

    // ── 404 fallback ──────────────────────────────────────────────
    $app->map(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], '/{routes:.+}', function ($request, $response) {
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => 'Route not found',
        ]));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    });
};
