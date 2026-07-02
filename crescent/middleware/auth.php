<?php

namespace Crescent\Middleware;

use Crescent\Core\Context;
use Crescent\Utils\Env;

/**
 * Middleware de autenticação via JWT — sem dependências externas.
 *
 * Implementa HS256.
 * Armazenamento seguro do token: cookie HttpOnly + Secure + SameSite=Strict.
 * Fallback para header Authorization: Bearer (APIs externas/mobile).
 *
 * Uso:
 *   // Protege uma rota
 *   $app->get('/perfil', [Auth::required()], fn($ctx) => ...);
 *
 *   // Emite o token (cookie + JSON)
 *   Auth::issueToken($ctx, ['id' => 1, 'email' => 'ana@email.com']);
 *
 *   // Revoga o token (logout)
 *   Auth::revokeCurrentToken($ctx);
 *
 * O payload fica disponível em $ctx->state['user'].
 * O JTI fica em $ctx->state['jti'].
 */
class Auth
{
    public const COOKIE_NAME = 'crescent_token';
    public const COOKIE_TTL  = 28_800;   // 8 horas em segundos

    // ─── Middlewares ──────────────────────────────────────────────────────────

    /**
     * Exige token válido. Retorna 401 automaticamente se ausente ou inválido.
     * Redireciona para /auth/login se o cliente aceitar HTML (navegador).
     * Popula $ctx->state['user'] e $ctx->state['jti'].
     */
    public static function required(): callable
    {
        return function (Context $ctx, callable $next): void {
            $payload = static::resolvePayload($ctx);

            if (!$payload) {
                static::denyUnauthorized($ctx);
                return;
            }

            $ctx->state['user'] = $payload;
            $ctx->state['jti']  = $payload['jti'] ?? null;
            $next();
        };
    }

    /**
     * Popula $ctx->state['user'] se houver token válido, sem bloquear.
     */
    public static function optional(): callable
    {
        return function (Context $ctx, callable $next): void {
            $payload = static::resolvePayload($ctx);

            if ($payload) {
                $ctx->state['user'] = $payload;
                $ctx->state['jti']  = $payload['jti'] ?? null;
            }

            $next();
        };
    }

    /**
     * Verifica se o usuário possui determinada role.
     * O campo 'role' deve estar no payload do JWT.
     *
     *   $app->get('/admin', [Auth::role('admin')], fn($ctx) => ...);
     */
    public static function role(string ...$roles): callable
    {
        return function (Context $ctx, callable $next) use ($roles): void {
            $payload = static::resolvePayload($ctx);

            if (!$payload) {
                static::denyUnauthorized($ctx);
                return;
            }

            if (!in_array($payload['role'] ?? null, $roles, true)) {
                $ctx->json(['error' => 'Acesso negado: permissão insuficiente.'], 403);
                return;
            }

            $ctx->state['user'] = $payload;
            $ctx->state['jti']  = $payload['jti'] ?? null;
            $next();
        };
    }

    // ─── Emissão e revogação ──────────────────────────────────────────────────

    /**
     * Gera o JWT, define o cookie seguro e retorna o token gerado.
     *
     * @param array $payload  Dados do usuário (não inclua senha).
     * @param int   $ttl      Validade em segundos (padrão 8h).
     */
    public static function issueToken(Context $ctx, array $payload, int $ttl = self::COOKIE_TTL): string
    {
        $token = static::generateToken($payload, $ttl);
        static::setCookie($token, $ttl);
        return $token;
    }

    /**
     * Revoga o token atual e limpa o cookie (Logout seguro).
     * O JTI é armazenado na tabela revoked_tokens até o JWT expirar.
     */
    public static function revokeCurrentToken(Context $ctx): void
    {
        $token = static::extractRawToken($ctx);

        if ($token) {
            $payload = static::decodeToken($token);

            if ($payload && isset($payload['jti'], $payload['exp'])) {
                try {
                    \App\Auth\Models\AuthModel::revokeToken($payload['jti'], (int) $payload['exp']);
                } catch (\Throwable) {
                    // Ignora se a tabela não existir (ex.: ambiente sem BD)
                }
            }
        }

        static::clearCookie();
    }

    // ─── JWT ──────────────────────────────────────────────────────────────────

    /**
     * Gera um JWT HS256 com JTI único (necessário para revogação).
     */
    public static function generateToken(array $payload, int $ttl = self::COOKIE_TTL): string
    {
        $now = time();

        $payload['iat'] = $now;
        $payload['exp'] = $now + $ttl;
        $payload['jti'] = bin2hex(random_bytes(16)); // único por token

        $header = static::base64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $body   = static::base64url(json_encode($payload));
        $sig    = static::base64url(hash_hmac('sha256', "{$header}.{$body}", static::secret(), true));

        return "{$header}.{$body}.{$sig}";
    }

    /**
     * Valida assinatura e expiração; retorna payload ou null.
     * Não verifica revogação — use resolvePayload() para isso.
     */
    public static function verifyToken(string $token): ?array
    {
        return static::decodeToken($token);
    }

    // ─── Cookie ───────────────────────────────────────────────────────────────

    /**
     * Define o cookie de autenticação com flags de segurança máximas:
     *  - HttpOnly  → JavaScript não consegue ler → protege contra XSS
     *  - Secure    → só enviado em HTTPS em produção
     *  - SameSite=Strict → protege contra CSRF
     */
    public static function setCookie(string $token, int $ttl = self::COOKIE_TTL): void
    {
        setcookie(static::COOKIE_NAME, $token, [
            'expires'  => time() + $ttl,
            'path'     => '/',
            'domain'   => '',
            'secure'   => static::isHttps(),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    /** Limpa o cookie (logout). */
    public static function clearCookie(): void
    {
        setcookie(static::COOKIE_NAME, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'domain'   => '',
            'secure'   => static::isHttps(),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);

        unset($_COOKIE[static::COOKIE_NAME]);
    }

    // ─── Internals ────────────────────────────────────────────────────────────

    /**
     * Extrai e valida o payload da requisição.
     * Ordem: cookie HttpOnly → Authorization Bearer.
     * Verifica também revogação via JTI.
     */
    private static function resolvePayload(Context $ctx): ?array
    {
        $token = static::extractRawToken($ctx);

        if (!$token) {
            return null;
        }

        $payload = static::decodeToken($token);

        if (!$payload) {
            return null;
        }

        // Verifica revogação (logout seguro)
        if (!empty($payload['jti'])) {
            try {
                if (\App\Auth\Models\AuthModel::isTokenRevoked($payload['jti'])) {
                    return null;
                }
            } catch (\Throwable) {
                // Tabela pode não existir; prossegue sem verificação
            }
        }

        return $payload;
    }

    /** Extrai o token bruto: primeiro do cookie, depois do header. */
    private static function extractRawToken(Context $ctx): ?string
    {
        if (!empty($_COOKIE[static::COOKIE_NAME])) {
            return $_COOKIE[static::COOKIE_NAME];
        }

        return $ctx->bearerToken();
    }

    /** Valida assinatura HMAC-SHA256 e expiração. */
    private static function decodeToken(string $token): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        [$header, $body, $sig] = $parts;

        $expected = static::base64url(
            hash_hmac('sha256', "{$header}.{$body}", static::secret(), true)
        );

        if (!hash_equals($expected, $sig)) {
            return null;
        }

        $payload = json_decode(static::base64urlDecode($body), true);

        if (!is_array($payload)) {
            return null;
        }

        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    private static function denyUnauthorized(Context $ctx): void
    {
        $accept = $ctx->requestHeader('accept') ?? '';

        if (str_contains($accept, 'text/html')) {
            $ctx->redirect('/auth/login');
        } else {
            $ctx->json(['error' => 'Não autenticado. Token ausente ou inválido.'], 401);
        }
    }

    private static function secret(): string
    {
        $s = Env::get('APP_SECRET');

        if (!$s) {
            throw new \RuntimeException('APP_SECRET não definido no .env');
        }

        return $s;
    }

    private static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? 80) == 443)
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    }

    private static function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64urlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
    }
}
