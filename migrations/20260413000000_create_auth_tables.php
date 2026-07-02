<?php

/**
 * Migration: create_password_resets_table
 * Gerada em: 2026-04-13 00:00:00
 */

return new class {

    public function up(\PDO $pdo): void
    {
        // Tabela de tokens de redefinição de senha
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS `password_resets` (
                `id`         INT          UNSIGNED NOT NULL AUTO_INCREMENT,
                `email`      VARCHAR(180) NOT NULL,
                `token_hash` VARCHAR(255) NOT NULL COMMENT 'SHA-256 do token — nunca armazene o token bruto',
                `expires_at` DATETIME     NOT NULL,
                `used_at`    DATETIME              DEFAULT NULL,
                `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `pr_email` (`email`),
                KEY `pr_token` (`token_hash`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);

        // Tabela de sessões JWT revogadas (logout seguro)
        // Apenas os JTI (JWT ID) são armazenados — pequeno footprint
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS `revoked_tokens` (
                `jti`        VARCHAR(64)  NOT NULL,
                `revoked_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `expires_at` DATETIME     NOT NULL COMMENT 'Para limpeza automática',
                PRIMARY KEY (`jti`),
                KEY `rt_expires` (`expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS `revoked_tokens`');
        $pdo->exec('DROP TABLE IF EXISTS `password_resets`');
    }
};
