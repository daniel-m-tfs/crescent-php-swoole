<?php

namespace Crescent\Utils;

/**
 * Hashing seguro de senhas usando Argon2id (PHP 7.3+).
 *
 * Argon2id é o algoritmo recomendado pelo OWASP por ter resistência
 * contra ataques de GPU (memória intensiva) e side-channel.
 *
 * Hashes PBKDF2 legados são verificados via fallback e re-hasheados
 * na próxima autenticação (handled em UserModel::updateUser).
 *
 * Uso:
 *   $hash = Hash::make('senha123');
 *   Hash::verify('senha123', $hash);  // true
 *   Hash::needsRehash($hash);          // false (mesmo custo)
 */
class Hash
{
    // Argon2id — parâmetros mínimos OWASP (memória, iterações, threads)
    private const ARGON_OPTIONS = [
        'memory_cost' => 65536,  // 64 MB
        'time_cost'   => 4,
        'threads'     => 2,
    ];

    // Mantido apenas para verificar hashes PBKDF2 legados
    private const PBKDF2_ALGO       = 'sha256';
    private const PBKDF2_ITERATIONS = 100_000;
    private const PBKDF2_KEY_LEN    = 32;

    /**
     * Gera o hash de uma senha com Argon2id.
     */
    public static function make(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID, self::ARGON_OPTIONS);
    }

    /**
     * Verifica se a senha corresponde ao hash armazenado.
     * Suporta Argon2id (novo) e PBKDF2 (legado).
     * Usa comparação em tempo constante para evitar timing attacks.
     */
    public static function verify(string $password, string $hash): bool
    {
        // Argon2id / bcrypt / qualquer formato reconhecido pelo PHP
        if (str_starts_with($hash, '$argon') || str_starts_with($hash, '$2y')) {
            return password_verify($password, $hash);
        }

        // Fallback: PBKDF2 legado ($pbkdf2$v=1$...)
        $parts = explode('$', $hash);
        if (count($parts) === 7 && $parts[1] === 'pbkdf2') {
            $algo       = explode('=', $parts[3])[1];
            $iterations = (int) explode('=', $parts[4])[1];
            $salt       = hex2bin($parts[5]);
            $storedHash = $parts[6];
            $computed   = bin2hex(hash_pbkdf2($algo, $password, $salt, $iterations, self::PBKDF2_KEY_LEN, true));
            return hash_equals($storedHash, $computed);
        }

        return false;
    }

    /**
     * Indica se o hash precisa ser regenerado (algoritmo ou custo desatualizado).
     */
    public static function needsRehash(string $hash): bool
    {
        // Hash PBKDF2 legado → precisa migrar para Argon2id
        if (str_starts_with($hash, '$pbkdf2') || str_contains($hash, 'pbkdf2')) {
            return true;
        }

        return password_needs_rehash($hash, PASSWORD_ARGON2ID, self::ARGON_OPTIONS);
    }

    /**
     * Gera um token aleatório URL-safe (hexadecimal).
     *
     * @param int $bytes Número de bytes de entropia (tamanho hex = bytes * 2).
     */
    public static function token(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    /**
     * Gera um UUID v4 aleatório.
     */
    public static function uuid(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
