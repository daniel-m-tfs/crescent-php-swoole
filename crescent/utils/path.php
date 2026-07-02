<?php

namespace Crescent\Utils;

/**
 * Utilitários para manipulação de caminhos de arquivo.
 */
class Path
{
    /**
     * Junta segmentos de caminho de forma segura.
     *
     *   Path::join(APP_ROOT, 'src', 'users', 'views') → /caminho/src/users/views
     */
    public static function join(string ...$parts): string
    {
        $segments = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $segments[] = rtrim($part, '/\\');
        }
        return implode(DIRECTORY_SEPARATOR, $segments);
    }

    /**
     * Retorna a extensão do arquivo (sem ponto, em minúsculas).
     */
    public static function ext(string $path): string
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION));
    }

    /**
     * Retorna o nome do arquivo sem extensão.
     */
    public static function basename(string $path): string
    {
        return pathinfo($path, PATHINFO_FILENAME);
    }

    /**
     * Retorna o diretório pai de um caminho.
     */
    public static function dir(string $path): string
    {
        return dirname($path);
    }

    /**
     * Verifica se um caminho é absoluto.
     */
    public static function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/') || (strlen($path) > 2 && $path[1] === ':');
    }

    /**
     * Normaliza separadores para o padrão do SO e remove '../'.
     */
    public static function normalize(string $path): string
    {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $parts = explode(DIRECTORY_SEPARATOR, $path);
        $stack = [];

        foreach ($parts as $part) {
            if ($part === '..') {
                array_pop($stack);
            } elseif ($part !== '.') {
                $stack[] = $part;
            }
        }

        return implode(DIRECTORY_SEPARATOR, $stack);
    }

    /**
     * Garante que um caminho não escapa de um diretório base (path traversal).
     * Retorna null se o caminho estiver fora da base.
     */
    public static function safe(string $base, string $path): ?string
    {
        $realBase = realpath($base);
        $realPath = realpath($base . DIRECTORY_SEPARATOR . $path);

        if ($realBase === false || $realPath === false) {
            return null;
        }

        if (!str_starts_with($realPath, $realBase)) {
            return null;
        }

        return $realPath;
    }

    /**
     * Retorna o caminho relativo de $path em relação a $base.
     */
    public static function relative(string $base, string $path): string
    {
        $base = rtrim(str_replace('\\', '/', $base), '/');
        $path = str_replace('\\', '/', $path);

        if (str_starts_with($path, $base)) {
            return ltrim(substr($path, strlen($base)), '/');
        }

        return $path;
    }

    /**
     * Cria um diretório recursivamente se não existir.
     */
    public static function mkdir(string $path, int $mode = 0755): bool
    {
        if (is_dir($path)) {
            return true;
        }
        return mkdir($path, $mode, true);
    }

    /**
     * Retorna o caminho para a raiz da aplicação.
     */
    public static function root(string ...$more): string
    {
        $root = defined('APP_ROOT') ? APP_ROOT : dirname(dirname(__DIR__));
        return count($more) ? static::join($root, ...$more) : $root;
    }
}
