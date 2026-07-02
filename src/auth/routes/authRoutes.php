<?php

use Crescent\Middleware\Auth;
use App\Auth\Controllers\AuthController;

$auth = new AuthController();

// ── Rotas públicas ────────────────────────────────────────────────────────────

$app->get('/auth/register',  fn($ctx) => $auth->showRegister($ctx));
$app->post('/auth/register', fn($ctx) => $auth->register($ctx));

$app->get('/auth/login',  fn($ctx) => $auth->showLogin($ctx));
$app->post('/auth/login', fn($ctx) => $auth->login($ctx));

$app->get('/auth/forgot-password',  fn($ctx) => $auth->showForgotPassword($ctx));
$app->post('/auth/forgot-password', fn($ctx) => $auth->forgotPassword($ctx));

$app->get('/auth/reset-password',  fn($ctx) => $auth->showResetPassword($ctx));
$app->post('/auth/reset-password', fn($ctx) => $auth->resetPassword($ctx));

// ── Requer autenticação ───────────────────────────────────────────────────────

$app->post('/auth/logout', fn($ctx) => $auth->logout($ctx), [Auth::required()]);
$app->get('/auth/logout',  fn($ctx) => $auth->logout($ctx), [Auth::required()]);
