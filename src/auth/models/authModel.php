<?php

namespace App\Auth\Models;

use Crescent\Core\Model;
use Crescent\Utils\Hash;

/**
 * Modelo para operações de autenticação:
 *  - Tokens de redefinição de senha (password_resets)
 *  - Revogação de JWT (revoked_tokens)
 *
 * O modelo de usuário em si continua em App\Users\Models\UserModel.
 */
class AuthModel extends Model
{
    protected static string $table = 'password_resets';

    // ─── Password Resets ──────────────────────────────────────────────────────

    /**
     * Cria um token de redefinição de senha.
     * Invalida todos os tokens anteriores do mesmo e-mail antes de gerar.
     *
     * Retorna o token bruto (para enviar por e-mail).
     * Apenas o hash SHA-256 é armazenado no banco.
     *
     * @param  int $ttlMinutes Validade em minutos (padrão 60)
     */
    public static function createResetToken(string $email, int $ttlMinutes = 60): string
    {
        // Invalida tokens anteriores para o mesmo e-mail
        static::execute(
            'DELETE FROM `password_resets` WHERE `email` = ?',
            [$email]
        );

        // Gera token criptograficamente seguro
        $token      = Hash::token(32); // 64 chars hex
        $tokenHash  = hash('sha256', $token);
        $expiresAt  = date('Y-m-d H:i:s', time() + $ttlMinutes * 60);

        static::insert([
            'email'      => $email,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
        ]);

        return $token;
    }

    /**
     * Valida um token de redefinição.
     * Retorna o registro ou null se inválido/expirado/já usado.
     */
    public static function findValidResetToken(string $token): ?array
    {
        $tokenHash = hash('sha256', $token);

        $rows = static::query(
            'SELECT * FROM `password_resets`
             WHERE `token_hash` = ?
               AND `expires_at` > NOW()
               AND `used_at` IS NULL
             LIMIT 1',
            [$tokenHash]
        );

        return $rows[0] ?? null;
    }

    /**
     * Marca um token como usado (não pode ser reutilizado).
     */
    public static function markTokenUsed(string $token): void
    {
        $tokenHash = hash('sha256', $token);

        static::execute(
            'UPDATE `password_resets` SET `used_at` = NOW() WHERE `token_hash` = ?',
            [$tokenHash]
        );
    }

    /**
     * Remove tokens expirados (limpeza, rode periodicamente ou via CLI).
     */
    public static function pruneExpired(): int
    {
        return static::execute(
            'DELETE FROM `password_resets` WHERE `expires_at` < NOW()'
        )->rowCount();
    }

    // ─── Token Revocation (Logout seguro) ────────────────────────────────────

    /**
     * Revoga um JWT pelo seu JTI.
     * O JTI deve estar no payload do token.
     *
     * @param string $jti       JWT ID (campo `jti` do payload)
     * @param int    $expiresAt Timestamp de expiração do JWT (campo `exp`)
     */
    public static function revokeToken(string $jti, int $expiresAt): void
    {
        static::execute(
            'INSERT IGNORE INTO `revoked_tokens` (`jti`, `expires_at`)
             VALUES (?, FROM_UNIXTIME(?))',
            [$jti, $expiresAt]
        );
    }

    /**
     * Verifica se um JTI está revogado.
     */
    public static function isTokenRevoked(string $jti): bool
    {
        $rows = static::query(
            'SELECT 1 FROM `revoked_tokens` WHERE `jti` = ? LIMIT 1',
            [$jti]
        );

        return !empty($rows);
    }

    /**
     * Remove tokens revogados que já expiraram (reduz crescimento da tabela).
     */
    public static function pruneRevokedTokens(): int
    {
        return static::execute(
            'DELETE FROM `revoked_tokens` WHERE `expires_at` < NOW()'
        )->rowCount();
    }
}
