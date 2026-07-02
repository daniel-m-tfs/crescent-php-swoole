<?php

/**
 * Testes de exemplo do CrescentPHP.
 * Execute com:  php crecli.php test
 */

use Crescent\Utils\Tests;
use Crescent\Utils\Hash;
use Crescent\Utils\Str;
use Crescent\Utils\Path;

// Bootstrap
if (!defined('APP_ROOT')) {
    define('APP_ROOT',      dirname(__DIR__));
    define('CRESCENT_ROOT', APP_ROOT . '/crescent');
    require CRESCENT_ROOT . '/utils/env.php';
    \Crescent\Utils\Env::load(APP_ROOT . '/.env');
}

// Autoloader
require_once CRESCENT_ROOT . '/init.php';

// ─── Hash ─────────────────────────────────────────────────────────────────────

Tests::describe('Hash', function () {

    Tests::it('deve gerar e verificar senha com PBKDF2', function () {
        $hash = Hash::make('minha_senha_123');
        Tests::expect(Hash::verify('minha_senha_123', $hash))->toBeTrue();
        Tests::expect(Hash::verify('senha_errada',    $hash))->toBeFalse();
    });

    Tests::it('hashes distintos para a mesma senha (salt aleatório)', function () {
        $h1 = Hash::make('abc');
        $h2 = Hash::make('abc');
        Tests::expect($h1)->not()->toBe($h2);
    });

    Tests::it('não precisa de rehash quando parâmetros são atuais', function () {
        $hash = Hash::make('x');
        Tests::expect(Hash::needsRehash($hash))->toBeFalse();
    });

    Tests::it('gera token aleatório com comprimento correto', function () {
        $token = Hash::token(16);
        Tests::expect(strlen($token))->toBe(32); // hex = bytes * 2
    });

    Tests::it('gera UUID v4 válido', function () {
        $uuid = Hash::uuid();
        Tests::expect(Str::isUuid($uuid))->toBeTrue();
    });
});

// ─── Str ──────────────────────────────────────────────────────────────────────

Tests::describe('Str', function () {

    Tests::it('converte para snake_case', function () {
        Tests::expect(Str::toSnake('UserController'))->toBe('user_controller');
        Tests::expect(Str::toSnake('myVarName'))->toBe('my_var_name');
    });

    Tests::it('converte para camelCase', function () {
        Tests::expect(Str::toCamel('user_name'))->toBe('userName');
    });

    Tests::it('converte para PascalCase', function () {
        Tests::expect(Str::toPascal('user_model'))->toBe('UserModel');
    });

    Tests::it('converte para kebab-case', function () {
        Tests::expect(Str::toKebab('UserController'))->toBe('user-controller');
    });

    Tests::it('valida e-mail', function () {
        Tests::expect(Str::isEmail('ana@email.com'))->toBeTrue();
        Tests::expect(Str::isEmail('nao_e_email'))->toBeFalse();
    });

    Tests::it('trunca string longa', function () {
        $s = Str::truncate('Olá, mundo CrescentPHP!', 10);
        Tests::expect(mb_strlen($s))->toBeLessThan(mb_strlen('Olá, mundo CrescentPHP!'));
        Tests::expect(Str::endsWith($s, '…'))->toBeTrue();
    });

    Tests::it('escapa HTML', function () {
        $escaped = Str::escape('<script>alert(1)</script>');
        Tests::expect(Str::contains($escaped, '<script>'))->toBeFalse();
    });
});

// ─── Path ─────────────────────────────────────────────────────────────────────

Tests::describe('Path', function () {

    Tests::it('une segmentos de caminho', function () {
        $result = Path::join('/base', 'src', 'users');
        Tests::expect($result)->toBe('/base/src/users');
    });

    Tests::it('retorna extensão correta', function () {
        Tests::expect(Path::ext('arquivo.PHP'))->toBe('php');
    });

    Tests::it('retorna basename sem extensão', function () {
        Tests::expect(Path::basename('/caminho/para/arquivo.php'))->toBe('arquivo');
    });

    Tests::it('normaliza caminhos com ..', function () {
        $r = Path::normalize('/base/src/../config');
        Tests::expect(Str::contains($r, '..'))->toBeFalse();
    });
});

// ─── Env ──────────────────────────────────────────────────────────────────────

Tests::describe('Env', function () {

    Tests::it('define e lê variável em tempo de execução', function () {
        \Crescent\Utils\Env::set('TEST_VAR', 'crescent');
        Tests::expect(\Crescent\Utils\Env::get('TEST_VAR'))->toBe('crescent');
    });

    Tests::it('retorna valor padrão para variável inexistente', function () {
        Tests::expect(\Crescent\Utils\Env::get('NAO_EXISTE', 'default'))->toBe('default');
    });
});
