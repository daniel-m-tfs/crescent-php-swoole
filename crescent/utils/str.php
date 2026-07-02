<?php

namespace Crescent\Utils;

/**
 * Utilitários para manipulação de strings.
 */
class Str
{
    // ─── Transformações ───────────────────────────────────────────────────────

    /** camelCase → snake_case */
    public static function toSnake(string $str): string
    {
        $str = preg_replace('/([a-z])([A-Z])/', '$1_$2', $str);
        return strtolower($str);
    }

    /** snake_case → camelCase */
    public static function toCamel(string $str): string
    {
        return lcfirst(str_replace('_', '', ucwords($str, '_')));
    }

    /** qualquer string → PascalCase */
    public static function toPascal(string $str): string
    {
        return str_replace([' ', '_', '-'], '', ucwords($str, ' _-'));
    }

    /** qualquer string → kebab-case */
    public static function toKebab(string $str): string
    {
        $str = preg_replace('/([a-z])([A-Z])/', '$1-$2', $str);
        return strtolower(str_replace(['_', ' '], '-', $str));
    }

    /** Slug URL-friendly. */
    public static function slug(string $str, string $separator = '-'): string
    {
        $str = mb_strtolower(trim($str));

        // Remove acentos
        $str = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $str)
            ?? iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str);

        // Remove caracteres não-alfanuméricos
        $str = preg_replace('/[^a-z0-9\s-]/', '', $str);
        $str = preg_replace('/[\s-]+/', $separator, $str);

        return trim($str, $separator);
    }

    /** Trunca uma string adicionando reticências se necessário. */
    public static function truncate(string $str, int $length, string $append = '…'): string
    {
        if (mb_strlen($str) <= $length) {
            return $str;
        }
        return mb_substr($str, 0, $length) . $append;
    }

    // ─── Verificações ─────────────────────────────────────────────────────────

    public static function startsWith(string $haystack, string $needle): bool
    {
        return str_starts_with($haystack, $needle);
    }

    public static function endsWith(string $haystack, string $needle): bool
    {
        return str_ends_with($haystack, $needle);
    }

    public static function contains(string $haystack, string $needle): bool
    {
        return str_contains($haystack, $needle);
    }

    /** Verifica se a string é um e-mail válido. */
    public static function isEmail(string $str): bool
    {
        return filter_var($str, FILTER_VALIDATE_EMAIL) !== false;
    }

    /** Verifica se a string é uma URL válida. */
    public static function isUrl(string $str): bool
    {
        return filter_var($str, FILTER_VALIDATE_URL) !== false;
    }

    /** Verifica se a string é um UUID v4. */
    public static function isUuid(string $str): bool
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $str
        );
    }

    // ─── Misc ─────────────────────────────────────────────────────────────────

    /** Preenche a string à esquerda ou à direita. */
    public static function pad(string $str, int $length, string $pad = ' ', int $type = STR_PAD_RIGHT): string
    {
        return str_pad($str, $length, $pad, $type);
    }

    /** Repete a string n vezes. */
    public static function repeat(string $str, int $times): string
    {
        return str_repeat($str, $times);
    }

    /** Escapa HTML para evitar XSS. */
    public static function escape(string $str): string
    {
        return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /** Plural simples (inglês). Para pt-BR use manualmente. */
    public static function plural(string $word, int $count): string
    {
        return $count === 1 ? $word : $word . 's';
    }

    /** Gera um string de 'n' caracteres aleatórios. */
    public static function random(int $length = 16, string $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'): string
    {
        $output = '';
        $max    = strlen($chars) - 1;
        for ($i = 0; $i < $length; $i++) {
            $output .= $chars[random_int(0, $max)];
        }
        return $output;
    }
}
