<?php

namespace Crescent\Utils;

/**
 * Carrega e acessa variáveis de ambiente do arquivo .env.
 *
 * Formato suportado:
 *   APP_ENV=production
 *   DB_PASS="senha com espaços"
 *   # comentário
 */
class Env
{
    private static array $vars = [];
    private static bool  $loaded = false;

    /**
     * Carrega o arquivo .env para o array interno e para $_ENV.
     * Não sobrescreve variáveis já definidas no ambiente real do servidor.
     */
    public static function load(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            // Ignora comentários
            if (str_starts_with($line, '#') || $line === '') {
                continue;
            }

            // Separa chave e valor
            $eqPos = strpos($line, '=');
            if ($eqPos === false) {
                continue;
            }

            $key   = trim(substr($line, 0, $eqPos));
            $value = trim(substr($line, $eqPos + 1));

            // Remove aspas
            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            // Remove comentário inline  (VAR=value # comentário)
            if (preg_match('/\s+#.*$/', $value, $m)) {
                $value = substr($value, 0, -strlen($m[0]));
            }

            // Não sobrescreve variáveis do sistema
            if (getenv($key) === false) {
                putenv("{$key}={$value}");
                $_ENV[$key]    = $value;
                $_SERVER[$key] = $value;
            }

            static::$vars[$key] = $value;
        }

        static::$loaded = true;
    }

    /**
     * Retorna o valor de uma variável de ambiente.
     *
     * @param  mixed $default Valor padrão se a variável não existir.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        // Prioridade: array interno → $_ENV → getenv → default
        if (array_key_exists($key, static::$vars)) {
            return static::$vars[$key];
        }

        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }

        $val = getenv($key);
        if ($val !== false) {
            return $val;
        }

        return $default;
    }

    /**
     * Define uma variável em tempo de execução.
     */
    public static function set(string $key, string $value): void
    {
        static::$vars[$key] = $value;
        $_ENV[$key]         = $value;
        putenv("{$key}={$value}");
    }

    /**
     * Verifica se uma variável existe.
     */
    public static function has(string $key): bool
    {
        return static::get($key) !== null;
    }

    /**
     * Retorna true se APP_ENV for "production" ou "prod".
     */
    public static function isProduction(): bool
    {
        $env = strtolower((string) static::get('APP_ENV', 'development'));
        return in_array($env, ['production', 'prod'], true);
    }

    /**
     * Retorna true se APP_ENV for "development", "dev" ou não estiver definido.
     */
    public static function isDevelopment(): bool
    {
        return !static::isProduction();
    }

    /** Retorna todas as variáveis carregadas pelo .env (não as do sistema). */
    public static function all(): array
    {
        return static::$vars;
    }
}
