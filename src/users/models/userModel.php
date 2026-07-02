<?php

namespace App\Users\Models;

use Crescent\Core\Model;

/**
 * Model de usuários.
 *
 * Estende o Model base do CrescentPHP — a conexão PDO é singleton
 * e as credenciais vêm do .env automaticamente.
 */
class UserModel extends Model
{
    protected static string $table      = 'users';
    protected static string $primaryKey = 'id';

    // ─── Queries específicas ──────────────────────────────────────────────────

    /** Retorna todos os usuários ativos ordenados por nome. */
    public static function getAll(): array
    {
        return static::where(['active' => 1], 'name ASC');
    }

    /** Busca usuário pelo e-mail (único). */
    public static function findByEmail(string $email): ?array
    {
        return static::findWhere(['email' => $email]);
    }

    /**
     * Cria um usuário já com hash de senha.
     *
     * @param array{ name: string, email: string, password: string } $data
     * @return int|string ID gerado
     */
    public static function create(array $data): int|string
    {
        $data['password'] = \Crescent\Utils\Hash::make($data['password']);
        $data['active']   = $data['active'] ?? 1;
        $data['created_at'] = date('Y-m-d H:i:s');

        return static::insert($data);
    }

    /**
     * Atualiza um usuário; se 'password' for informada, recalcula o hash.
     */
    public static function updateUser(int $id, array $data): int
    {
        if (!empty($data['password'])) {
            $data['password'] = \Crescent\Utils\Hash::make($data['password']);
        }
        $data['updated_at'] = date('Y-m-d H:i:s');

        return static::update($id, $data);
    }

    /** Verifica credenciais de login. Retorna o registro ou null. */
    public static function authenticate(string $email, string $password): ?array
    {
        $user = static::findByEmail($email);

        if (!$user) {
            return null;
        }

        if (!\Crescent\Utils\Hash::verify($password, $user['password'])) {
            return null;
        }

        return $user;
    }
}
