<?php

namespace Crescent\Middleware;

use Crescent\Core\Context;

/**
 * Middleware de segurança: cabeçalhos seguros + rate limiting simples.
 *
 * Uso:
 *   $app->use(Security::handle());
 *
 * Com rate limiting:
 *   $app->use(Security::handle([
 *       'rate_limit'    => 60,   // máx requisições por janela
 *       'rate_window'   => 60,   // janela em segundos
 *   ]));
 */
class Security
{
    public static function handle(array $options = []): callable
    {
        $defaults = [
            // Cabeçalhos
            'x_content_type'    => true,
            'x_frame'           => 'DENY',          // false para desabilitar
            'x_xss'             => true,
            'referrer_policy'   => 'strict-origin-when-cross-origin',
            'csp'               => "",               // Content-Security-Policy, string ou false
            'hsts'              => false,            // só em HTTPS productionwith certificate

            // Rate limiting via arquivo (sem Redis/Memcache)
            'rate_limit'        => 0,                // 0 = desabilitado
            'rate_window'       => 60,               // segundos
            'rate_storage'      => sys_get_temp_dir() . '/crescent_rate',
        ];

        $opts = array_merge($defaults, $options);

        return function (Context $ctx, callable $next) use ($opts): void {
            // ── Cabeçalhos de segurança ──────────────────────────────────────
            if ($opts['x_content_type']) {
                $ctx->header('X-Content-Type-Options', 'nosniff');
            }

            if ($opts['x_frame']) {
                $ctx->header('X-Frame-Options', $opts['x_frame']);
            }

            if ($opts['x_xss']) {
                $ctx->header('X-XSS-Protection', '1; mode=block');
            }

            if ($opts['referrer_policy']) {
                $ctx->header('Referrer-Policy', $opts['referrer_policy']);
            }

            if ($opts['csp']) {
                $ctx->header('Content-Security-Policy', $opts['csp']);
            }

            if ($opts['hsts']) {
                $ctx->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
            }

            // Remove header que expõe tecnologia
            header_remove('X-Powered-By');

            // ── Rate limiting ────────────────────────────────────────────────
            if ($opts['rate_limit'] > 0) {
                $blocked = static::checkRateLimit(
                    $ctx->ip(),
                    $opts['rate_limit'],
                    $opts['rate_window'],
                    $opts['rate_storage']
                );

                if ($blocked) {
                    $ctx->json(['error' => 'Muitas requisições. Tente novamente em breve.'], 429);
                    return;
                }
            }

            $next();
        };
    }

    // ─── Rate limiting via arquivo ────────────────────────────────────────────

    private static function checkRateLimit(
        string $ip,
        int    $limit,
        int    $window,
        string $storageDir
    ): bool {
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0755, true);
        }

        $file = $storageDir . '/' . md5($ip) . '.json';
        $now  = time();

        $data = ['count' => 0, 'reset' => $now + $window];

        // Abre (ou cria) o arquivo com lock exclusivo para evitar TOCTOU.
        // LOCK_EX garante que leitura e escrita sejam atômicas sob concorrência.
        $fp = fopen($file, 'c+');
        if (!$fp) {
            return false; // Falha silenciosa: não bloqueia a requisição
        }

        flock($fp, LOCK_EX);

        $content = stream_get_contents($fp);
        if ($content) {
            $parsed = json_decode($content, true);
            if (is_array($parsed)) {
                $data = $parsed;
            }
        }

        if ($now >= $data['reset']) {
            $data = ['count' => 0, 'reset' => $now + $window];
        }

        $data['count']++;

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        return $data['count'] > $limit;
    }
}
