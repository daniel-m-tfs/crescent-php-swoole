<?php

namespace Crescent\Utils;

/**
 * Helpers para manipular e enviar HTTP headers.
 */
class Headers
{
    /**
     * Define um header de cache (no-cache, max-age, etc.).
     *
     * @param int $maxAge Segundos. 0 = sem cache.
     */
    public static function cache(int $maxAge = 0, bool $private = false): void
    {
        if ($maxAge === 0) {
            header('Cache-Control: no-store, no-cache, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
        } else {
            $scope = $private ? 'private' : 'public';
            header("Cache-Control: {$scope}, max-age={$maxAge}");
            header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + $maxAge));
        }
    }

    /**
     * Define o Content-Type com charset UTF-8.
     */
    public static function contentType(string $type): void
    {
        header("Content-Type: {$type}; charset=utf-8");
    }

    /**
     * Força o download de um arquivo.
     */
    public static function download(string $filename, string $mimeType = 'application/octet-stream'): void
    {
        header("Content-Type: {$mimeType}");
        header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
        header('Content-Transfer-Encoding: binary');
        static::cache(0);
    }

    /**
     * Cabeçalhos básicos de segurança (pode ser usado como middleware simples).
     */
    public static function secure(array $options = []): void
    {
        $defaults = [
            'x_content_type'   => true,
            'x_frame'          => 'DENY',
            'x_xss_protection' => true,
            'referrer_policy'  => 'strict-origin-when-cross-origin',
            'hsts'             => false,         // só em HTTPS
            'hsts_max_age'     => 31_536_000,
        ];

        $opts = array_merge($defaults, $options);

        if ($opts['x_content_type']) {
            header('X-Content-Type-Options: nosniff');
        }

        if ($opts['x_frame']) {
            header('X-Frame-Options: ' . $opts['x_frame']);
        }

        if ($opts['x_xss_protection']) {
            header('X-XSS-Protection: 1; mode=block');
        }

        if ($opts['referrer_policy']) {
            header('Referrer-Policy: ' . $opts['referrer_policy']);
        }

        if ($opts['hsts']) {
            header('Strict-Transport-Security: max-age=' . $opts['hsts_max_age'] . '; includeSubDomains');
        }
    }

    /**
     * Retorna todos os headers da requisição atual.
     */
    public static function all(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name           = str_replace('_', '-', substr($key, 5));
                $headers[$name] = $value;
            }
        }
        return $headers;
    }

    /**
     * Retorna o valor de um header de requisição.
     */
    public static function get(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $_SERVER[$key] ?? null;
    }
}
