<?php

/**
 * Configurações do ambiente de PRODUÇÃO.
 * Carregado quando APP_ENV=production no .env do servidor.
 *
 * Em produção, prefira definir as variáveis diretamente no painel
 * da hospedagem (cPanel → Environment Variables) em vez de comitar o .env.
 */

// Garante que erros não sejam exibidos ao usuário
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Caminho do log de erros PHP na hospedagem compartilhada
$logPath = defined('APP_ROOT') ? APP_ROOT . '/logs/php_errors.log' : ini_get('error_log');
if ($logPath) {
    ini_set('error_log', $logPath);
}
