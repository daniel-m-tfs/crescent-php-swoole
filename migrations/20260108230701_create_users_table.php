<?php

/**
 * Migration: create_users_table
 * Gerado em: 2026-01-08 23:07:01
 *
 * Rodado pelo CLI:  php crecli.php migrate
 * Revertido:        php crecli.php migrate:rollback
 */

return new class {

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS `users` (
                `id`         INT          UNSIGNED NOT NULL AUTO_INCREMENT,
                `name`       VARCHAR(120) NOT NULL,
                `email`      VARCHAR(180) NOT NULL,
                `password`   VARCHAR(255) NOT NULL,
                `active`     TINYINT(1)   NOT NULL DEFAULT 1,
                `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME              DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY  `users_email_unique` (`email`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS `users`');
    }
};
