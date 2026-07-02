<?php

namespace Crescent\Middleware;

use Crescent\Core\Context;

/**
 * Middleware de logging de requisições.
 *
 * Uso:
 *   $app->use(Logger::handle());                     // escreve em /logs/app.log
 *   $app->use(Logger::handle('/var/log/minha.log')); // caminho customizado
 */
class Logger
{
    public static function handle(?string $logFile = null, array $options = []): callable
    {
        $defaults = [
            'format'    => '[{date}] {method} {path} {status} {time}ms',
            'echo'      => false,   // true → imprime no console além de gravar
            'max_size'  => 5_242_880, // 5 MB — faz log rotation se ultrapassar
        ];

        $opts    = array_merge($defaults, $options);
        $logFile = $logFile ?? (defined('APP_ROOT') ? APP_ROOT . '/logs/app.log' : sys_get_temp_dir() . '/crescent.log');

        // Garante que o diretório exista
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        return function (Context $ctx, callable $next) use ($logFile, $opts): void {
            $start  = microtime(true);

            $next();

            $elapsed = round((microtime(true) - $start) * 1000, 2);

            $line = strtr($opts['format'], [
                '{date}'   => date('Y-m-d H:i:s'),
                '{method}' => str_pad($ctx->method(), 6),
                '{path}'   => $ctx->path(),
                '{status}' => '', // sem acesso fácil ao código de saída após envio
                '{time}'   => $elapsed,
                '{ip}'     => $ctx->ip(),
            ]) . PHP_EOL;

            // Log rotation simples
            if (file_exists($logFile) && filesize($logFile) > $opts['max_size']) {
                rename($logFile, $logFile . '.' . date('YmdHis') . '.bak');
            }

            file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);

            if ($opts['echo'] && PHP_SAPI === 'cli') {
                echo $line;
            }
        };
    }
}
