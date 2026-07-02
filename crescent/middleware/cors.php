<?php

namespace Crescent\Middleware;

use Crescent\Core\Context;

/**
 * Middleware de CORS (Cross-Origin Resource Sharing).
 *
 * Registro (global, em app.php):
 *
 *   $app->use(Cors::handle());
 *
 * Com opções customizadas:
 *
 *   $app->use(Cors::handle([
 *       'origins'  => ['https://meusite.com.br'],
 *       'methods'  => ['GET', 'POST', 'PUT', 'DELETE'],
 *       'headers'  => ['Content-Type', 'Authorization'],
 *       'max_age'  => 86400,
 *   ]));
 */
class Cors
{
    public static function handle(array $options = []): callable
    {
        $defaults = [
            'origins'     => ['*'],
            'methods'     => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
            'headers'     => ['Content-Type', 'Authorization', 'X-Requested-With'],
            'credentials' => false,
            'max_age'     => 3600,
        ];

        $opts = array_merge($defaults, $options);

        return function (Context $ctx, callable $next) use ($opts) {
            $origin = $ctx->requestHeader('origin') ?? '*';

            // Verifica se a origem é permitida
            if (in_array('*', $opts['origins'], true)) {
                $allowOrigin = '*';
            } elseif (in_array($origin, $opts['origins'], true)) {
                $allowOrigin = $origin;
            } else {
                $allowOrigin = $opts['origins'][0];
            }

            $ctx->header('Access-Control-Allow-Origin', $allowOrigin);
            $ctx->header('Access-Control-Allow-Methods', implode(', ', $opts['methods']));
            $ctx->header('Access-Control-Allow-Headers', implode(', ', $opts['headers']));
            $ctx->header('Access-Control-Max-Age', (string) $opts['max_age']);

            if ($opts['credentials']) {
                $ctx->header('Access-Control-Allow-Credentials', 'true');
            }

            // Preflight
            if ($ctx->method() === 'OPTIONS') {
                $ctx->noContent();
                return;
            }

            $next();
        };
    }
}
