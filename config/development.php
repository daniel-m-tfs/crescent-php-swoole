<?php

/**
 * Configurações do ambiente de DESENVOLVIMENTO.
 * Este arquivo é carregado automaticamente quando APP_ENV=development (padrão).
 */

// ─── Banco de dados ────────────────────────────────────────────────────────────
// Estas configurações podem ser sobrescritas via .env

\Crescent\Utils\Env::set('DB_DRIVER',  \Crescent\Utils\Env::get('DB_DRIVER',  'mysql'));
\Crescent\Utils\Env::set('DB_HOST',    \Crescent\Utils\Env::get('DB_HOST',    '127.0.0.1'));
\Crescent\Utils\Env::set('DB_PORT',    \Crescent\Utils\Env::get('DB_PORT',    '3306'));
\Crescent\Utils\Env::set('DB_CHARSET', \Crescent\Utils\Env::get('DB_CHARSET', 'utf8mb4'));
