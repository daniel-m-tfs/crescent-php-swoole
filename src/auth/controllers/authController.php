<?php

namespace App\Auth\Controllers;

use Crescent\Core\Context;
use Crescent\Middleware\Auth;
use Crescent\Utils\Hash;
use Crescent\Utils\Mailer;
use App\Auth\Models\AuthModel;
use App\Users\Models\UserModel;

class AuthController
{
    // ─── Register ─────────────────────────────────────────────────────────────

    public function showRegister(Context $ctx): void
    {
        $ctx->view('auth/views/register.php', ['error' => null]);
    }

    public function register(Context $ctx): void
    {
        $name     = trim($ctx->body['name']     ?? '');
        $email    = trim($ctx->body['email']    ?? '');
        $password = $ctx->body['password']      ?? '';
        $confirm  = $ctx->body['password_confirm'] ?? '';

        // ── Validação básica ──────────────────────────────────────────────────
        $errors = [];

        if ($name === '') {
            $errors[] = 'Nome é obrigatório.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'E-mail inválido.';
        }

        if (strlen($password) < 8) {
            $errors[] = 'A senha deve ter pelo menos 8 caracteres.';
        }

        if ($password !== $confirm) {
            $errors[] = 'As senhas não coincidem.';
        }

        if ($errors) {
            if ($this->wantsJson($ctx)) {
                $ctx->json(['errors' => $errors], 422);
            } else {
                $ctx->view('auth/views/register.php', ['errors' => $errors, 'old' => compact('name', 'email')]);
            }
            return;
        }

        // ── Verifica duplicata ────────────────────────────────────────────────
        if (UserModel::findWhere(['email' => $email])) {
            $msg = 'Este e-mail já está cadastrado.';
            if ($this->wantsJson($ctx)) {
                $ctx->json(['errors' => [$msg]], 409);
            } else {
                $ctx->view('auth/views/register.php', ['errors' => [$msg], 'old' => compact('name', 'email')]);
            }
            return;
        }

        // ── Cria o usuário ────────────────────────────────────────────────────
        // UserModel::create() já aplica Hash::make() internamente
        $userId = UserModel::create([
            'name'     => $name,
            'email'    => $email,
            'password' => $password,
        ]);

        $user = UserModel::find($userId);

        // ── Emite token e redireciona ─────────────────────────────────────────
        Auth::issueToken($ctx, [
            'id'    => $user['id'],
            'name'  => $user['name'],
            'email' => $user['email'],
            'role'  => $user['role'] ?? 'user',
        ]);

        if ($this->wantsJson($ctx)) {
            $ctx->json(['message' => 'Cadastro realizado com sucesso.', 'user' => [
                'id'    => $user['id'],
                'name'  => $user['name'],
                'email' => $user['email'],
            ]], 201);
        } else {
            $ctx->redirect('/dashboard');
        }
    }

    // ─── Login ────────────────────────────────────────────────────────────────

    public function showLogin(Context $ctx): void
    {
        $ctx->view('auth/views/login.php', ['error' => null]);
    }

    public function login(Context $ctx): void
    {
        $email    = trim($ctx->body['email']    ?? '');
        $password = $ctx->body['password']      ?? '';

        // Adiciona pequeno delay para dificultar timing attacks
        usleep(random_int(100_000, 250_000));

        $user = UserModel::findWhere(['email' => $email]);

        if (!$user || !Hash::verify($password, $user['password'] ?? '')) {
            $msg = 'Credenciais inválidas.';
            if ($this->wantsJson($ctx)) {
                $ctx->json(['error' => $msg], 401);
            } else {
                $ctx->view('auth/views/login.php', ['error' => $msg, 'old' => ['email' => $email]]);
            }
            return;
        }

        Auth::issueToken($ctx, [
            'id'    => $user['id'],
            'name'  => $user['name'],
            'email' => $user['email'],
            'role'  => $user['role'] ?? 'user',
        ]);

        if ($this->wantsJson($ctx)) {
            $ctx->json(['message' => 'Login realizado com sucesso.', 'user' => [
                'id'    => $user['id'],
                'name'  => $user['name'],
                'email' => $user['email'],
            ]]);
        } else {
            $redirect = $ctx->query['redirect'] ?? '/dashboard';
            // Garante que o redirect seja relativo (evita open redirect)
            if (!str_starts_with($redirect, '/')) {
                $redirect = '/dashboard';
            }
            $ctx->redirect($redirect);
        }
    }

    // ─── Logout ───────────────────────────────────────────────────────────────

    public function logout(Context $ctx): void
    {
        Auth::revokeCurrentToken($ctx);

        if ($this->wantsJson($ctx)) {
            $ctx->json(['message' => 'Logout realizado com sucesso.']);
        } else {
            $ctx->redirect('/auth/login');
        }
    }

    // ─── Forgot Password ──────────────────────────────────────────────────────

    public function showForgotPassword(Context $ctx): void
    {
        $ctx->view('auth/views/forgot_password.php', ['sent' => false, 'error' => null]);
    }

    public function forgotPassword(Context $ctx): void
    {
        $email = trim($ctx->body['email'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $msg = 'Informe um e-mail válido.';
            if ($this->wantsJson($ctx)) {
                $ctx->json(['error' => $msg], 422);
            } else {
                $ctx->view('auth/views/forgot_password.php', ['error' => $msg, 'sent' => false]);
            }
            return;
        }

        // Responde sempre com sucesso para não vazar se o e-mail existe
        $user = UserModel::findWhere(['email' => $email]);

        if ($user) {
            $rawToken = AuthModel::createResetToken($email);

            $resetUrl = $this->baseUrl() . '/auth/reset-password?token=' . urlencode($rawToken);

            try {
                Mailer::to($email, $user['name'] ?? '')
                    ->subject('Redefinição de senha')
                    ->html($this->forgotPasswordEmailHtml($user['name'] ?? 'usuário', $resetUrl))
                    ->text("Para redefinir sua senha, acesse: {$resetUrl}\n\nEste link expira em 60 minutos.")
                    ->send();
            } catch (\Throwable $e) {
                // Não expõe o erro ao usuário; loga internamente
                error_log('[Auth] Falha ao enviar e-mail de recuperação: ' . $e->getMessage());
            }
        }

        if ($this->wantsJson($ctx)) {
            $ctx->json(['message' => 'Se o e-mail estiver cadastrado, você receberá as instruções em breve.']);
        } else {
            $ctx->view('auth/views/forgot_password.php', ['sent' => true, 'error' => null]);
        }
    }

    // ─── Reset Password ───────────────────────────────────────────────────────

    public function showResetPassword(Context $ctx): void
    {
        $token = $ctx->query['token'] ?? '';

        if (!$token || !AuthModel::findValidResetToken($token)) {
            $ctx->view('auth/views/reset_password.php', [
                'token'   => '',
                'error'   => 'Link inválido ou expirado. Solicite um novo.',
                'expired' => true,
            ]);
            return;
        }

        $ctx->view('auth/views/reset_password.php', [
            'token'   => $token,
            'error'   => null,
            'expired' => false,
        ]);
    }

    public function resetPassword(Context $ctx): void
    {
        $token    = $ctx->body['token']            ?? '';
        $password = $ctx->body['password']         ?? '';
        $confirm  = $ctx->body['password_confirm'] ?? '';

        // ── Validações ────────────────────────────────────────────────────────
        $errors = [];

        if (strlen($password) < 8) {
            $errors[] = 'A senha deve ter pelo menos 8 caracteres.';
        }

        if ($password !== $confirm) {
            $errors[] = 'As senhas não coincidem.';
        }

        if ($errors) {
            if ($this->wantsJson($ctx)) {
                $ctx->json(['errors' => $errors], 422);
            } else {
                $ctx->view('auth/views/reset_password.php', ['token' => $token, 'errors' => $errors, 'expired' => false]);
            }
            return;
        }

        // ── Verifica token ────────────────────────────────────────────────────
        $record = AuthModel::findValidResetToken($token);

        if (!$record) {
            $msg = 'Link inválido ou expirado. Solicite um novo.';
            if ($this->wantsJson($ctx)) {
                $ctx->json(['error' => $msg], 400);
            } else {
                $ctx->view('auth/views/reset_password.php', ['token' => '', 'error' => $msg, 'expired' => true]);
            }
            return;
        }

        // ── Atualiza senha ────────────────────────────────────────────────────
        $user = UserModel::findWhere(['email' => $record['email']]);

        if (!$user) {
            $ctx->json(['error' => 'Usuário não encontrado.'], 404);
            return;
        }

        UserModel::update($user['id'], ['password' => Hash::make($password)]);
        AuthModel::markTokenUsed($token);

        // ── Auto-login ────────────────────────────────────────────────────────
        Auth::issueToken($ctx, [
            'id'    => $user['id'],
            'name'  => $user['name'],
            'email' => $user['email'],
            'role'  => $user['role'] ?? 'user',
        ]);

        if ($this->wantsJson($ctx)) {
            $ctx->json(['message' => 'Senha redefinida com sucesso.']);
        } else {
            $ctx->redirect('/dashboard');
        }
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function wantsJson(Context $ctx): bool
    {
        $accept      = $ctx->requestHeader('accept')       ?? '';
        $contentType = $ctx->requestHeader('content-type') ?? '';

        return str_contains($accept, 'application/json')
            || str_contains($contentType, 'application/json');
    }

    private function baseUrl(): string
    {
        $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return "{$proto}://{$host}";
    }

    private function forgotPasswordEmailHtml(string $name, string $url): string
    {
        $safeUrl  = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8"><title>Redefinição de senha</title></head>
<body style="font-family:sans-serif;color:#333;max-width:560px;margin:0 auto;padding:32px 16px">
  <h2 style="color:#4f46e5">Redefinição de senha</h2>
  <p>Olá, <strong>{$safeName}</strong>!</p>
  <p>Recebemos uma solicitação para redefinir a senha da sua conta.
     Clique no botão abaixo para criar uma nova senha:</p>
  <p style="text-align:center;margin:32px 0">
    <a href="{$safeUrl}"
       style="background:#4f46e5;color:#fff;padding:12px 28px;border-radius:6px;
              text-decoration:none;font-weight:bold;display:inline-block">
      Redefinir senha
    </a>
  </p>
  <p style="font-size:.85em;color:#666">
    Este link expira em <strong>60 minutos</strong>.<br>
    Se você não solicitou a redefinição, ignore este e-mail.
  </p>
  <hr style="border:none;border-top:1px solid #eee;margin:32px 0">
  <p style="font-size:.8em;color:#999">
    Por segurança, nunca compartilhe este link com ninguém.
  </p>
</body>
</html>
HTML;
    }
}
