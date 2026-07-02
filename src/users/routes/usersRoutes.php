<?php

/**
 * Rotas do módulo Users.
 *
 * Este arquivo é incluído por app.php e tem acesso à variável $app.
 * Registra tanto rotas API (JSON) quanto rotas web (Views).
 */

use App\Users\Controllers\UserController;
use Crescent\Middleware\Auth;

// ─── API REST ─────────────────────────────────────────────────────────────────

$app->group('/api', function ($app) {

    // Auth (login via API — delegar ao AuthController do módulo auth)
    $app->post('/auth/login', fn ($ctx) => (new \App\Auth\Controllers\AuthController())->login($ctx));

    // Users (protegidas com JWT)
    $app->get('/users',        fn ($ctx) => UserController::index($ctx),  [Auth::required()]);
    $app->get('/users/:id',    fn ($ctx) => UserController::show($ctx),   [Auth::required()]);
    $app->post('/users',       fn ($ctx) => UserController::store($ctx),  [Auth::required()]);
    $app->put('/users/:id',    fn ($ctx) => UserController::update($ctx), [Auth::required()]);
    $app->delete('/users/:id', fn ($ctx) => UserController::destroy($ctx),[Auth::required()]);
});

// ─── Web (Views HTML) ─────────────────────────────────────────────────────────

$app->group('/users', function ($app) {
    $app->get('/',              fn ($ctx) => UserController::listView($ctx));
    $app->get('/form',          fn ($ctx) => UserController::formView($ctx));
    $app->get('/:id/form',      fn ($ctx) => UserController::formView($ctx));
    $app->post('/',             fn ($ctx) => UserController::store($ctx));
    $app->put('/:id',           fn ($ctx) => UserController::update($ctx));
    $app->post('/:id/update',   fn ($ctx) => UserController::update($ctx));   // fallback form
    $app->delete('/:id',        fn ($ctx) => UserController::destroy($ctx));
    $app->post('/:id/delete',   fn ($ctx) => UserController::destroy($ctx));  // fallback form
});
