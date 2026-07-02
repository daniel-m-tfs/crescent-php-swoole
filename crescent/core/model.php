<?php

namespace Crescent\Core;

use PDO;
use PDOStatement;

/**
 * Base para todos os Models da aplicação.
 *
 * A conexão PDO é um singleton por classe — garante apenas uma conexão
 * por processo, funcionando bem em hospedagens compartilhadas.
 *
 * Exemplo de uso:
 *
 *   class UserModel extends Model {
 *       protected static string $table = 'users';
 *
 *       public static function findActive(): array {
 *           return static::query('SELECT * FROM users WHERE active = 1');
 *       }
 *   }
 *
 *   $user  = UserModel::find(1);
 *   $users = UserModel::all();
 *   $id    = UserModel::insert(['name' => 'Ana', 'email' => 'ana@email.com']);
 *   UserModel::update(1, ['name' => 'Ana Lima']);
 *   UserModel::delete(1);
 */
abstract class Model
{
    // Conexão compartilhada entre todas as subclasses
    private static ?PDO $pdo = null;

    /** Nome da tabela — deve ser definido em cada Model filho. */
    protected static string $table = '';

    /** Chave primária da tabela. */
    protected static string $primaryKey = 'id';

    // ─── Conexão ─────────────────────────────────────────────────────────────

    /**
     * Retorna (ou cria) a conexão PDO singleton.
     * As credenciais são lidas do .env via Env::get().
     */
    protected static function db(): PDO
    {
        if (static::$pdo !== null) {
            return static::$pdo;
        }

        $driver  = \Crescent\Utils\Env::get('DB_DRIVER',  'mysql');
        $host    = \Crescent\Utils\Env::get('DB_HOST',    'localhost');
        $port    = \Crescent\Utils\Env::get('DB_PORT',    '3306');
        $dbname  = \Crescent\Utils\Env::get('DB_NAME',    '');
        $user    = \Crescent\Utils\Env::get('DB_USER',    'root');
        $pass    = \Crescent\Utils\Env::get('DB_PASS',    '');
        $charset = \Crescent\Utils\Env::get('DB_CHARSET', 'utf8mb4');

        $dsn = "{$driver}:host={$host};port={$port};dbname={$dbname};charset={$charset}";

        static::$pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        return static::$pdo;
    }

    // ─── CRUD básico ──────────────────────────────────────────────────────────

    /** Retorna todos os registros da tabela. */
    public static function all(string $orderBy = ''): array
    {
        $sql = 'SELECT * FROM `' . static::$table . '`';
        if ($orderBy) {
            $sql .= ' ORDER BY ' . $orderBy;
        }
        return static::query($sql);
    }

    /** Retorna um registro pelo ID. */
    public static function find(int|string $id): ?array
    {
        $pk  = static::$primaryKey;
        $sql = 'SELECT * FROM `' . static::$table . "` WHERE `{$pk}` = ? LIMIT 1";
        $rows = static::query($sql, [$id]);
        return $rows[0] ?? null;
    }

    /** Retorna registros que satisfaçam as condições (AND simples). */
    public static function where(array $conditions, string $orderBy = ''): array
    {
        $clauses = [];
        $params  = [];

        foreach ($conditions as $column => $value) {
            $clauses[] = "`{$column}` = ?";
            $params[]  = $value;
        }

        $sql = 'SELECT * FROM `' . static::$table . '` WHERE ' . implode(' AND ', $clauses);
        if ($orderBy) {
            $sql .= ' ORDER BY ' . $orderBy;
        }

        return static::query($sql, $params);
    }

    /** Retorna o primeiro registro que satisfaça as condições. */
    public static function findWhere(array $conditions): ?array
    {
        $rows = static::where($conditions);
        return $rows[0] ?? null;
    }

    /**
     * Insere um registro e retorna o ID gerado.
     *
     * @param array<string, mixed> $data
     */
    public static function insert(array $data): int|string
    {
        $columns      = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');

        $sql = 'INSERT INTO `' . static::$table . '` '
             . '(`' . implode('`, `', $columns) . '`) '
             . 'VALUES (' . implode(', ', $placeholders) . ')';

        static::execute($sql, array_values($data));

        return static::db()->lastInsertId();
    }

    /**
     * Atualiza um registro pelo ID.
     *
     * @param array<string, mixed> $data
     */
    public static function update(int|string $id, array $data): int
    {
        $pk      = static::$primaryKey;
        $clauses = [];
        $params  = [];

        foreach ($data as $column => $value) {
            $clauses[] = "`{$column}` = ?";
            $params[]  = $value;
        }

        $params[] = $id;

        $sql = 'UPDATE `' . static::$table . '` SET ' . implode(', ', $clauses)
             . " WHERE `{$pk}` = ?";

        $stmt = static::execute($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Atualiza registros que satisfaçam as condições.
     *
     * @param array<string, mixed> $conditions
     * @param array<string, mixed> $data
     */
    public static function updateWhere(array $conditions, array $data): int
    {
        $setClauses   = [];
        $whereClauses = [];
        $params       = [];

        foreach ($data as $column => $value) {
            $setClauses[] = "`{$column}` = ?";
            $params[]     = $value;
        }

        foreach ($conditions as $column => $value) {
            $whereClauses[] = "`{$column}` = ?";
            $params[]       = $value;
        }

        $sql = 'UPDATE `' . static::$table . '` SET ' . implode(', ', $setClauses)
             . ' WHERE ' . implode(' AND ', $whereClauses);

        return static::execute($sql, $params)->rowCount();
    }

    /** Apaga um registro pelo ID. */
    public static function delete(int|string $id): int
    {
        $pk  = static::$primaryKey;
        $sql = 'DELETE FROM `' . static::$table . "` WHERE `{$pk}` = ?";
        return static::execute($sql, [$id])->rowCount();
    }

    /** Conta registros opcionalmente filtrados. */
    public static function count(array $conditions = []): int
    {
        $sql    = 'SELECT COUNT(*) FROM `' . static::$table . '`';
        $params = [];

        if ($conditions) {
            $clauses = [];
            foreach ($conditions as $column => $value) {
                $clauses[] = "`{$column}` = ?";
                $params[]  = $value;
            }
            $sql .= ' WHERE ' . implode(' AND ', $clauses);
        }

        $stmt = static::execute($sql, $params);
        return (int) $stmt->fetchColumn();
    }

    // ─── Helpers raw ─────────────────────────────────────────────────────────

    /**
     * Executa uma query e retorna todos os resultados como array associativo.
     *
     * @param  list<mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public static function query(string $sql, array $params = []): array
    {
        return static::execute($sql, $params)->fetchAll();
    }

    /**
     * Executa uma query preparada e retorna o PDOStatement.
     *
     * @param  list<mixed> $params
     */
    public static function execute(string $sql, array $params = []): PDOStatement
    {
        $stmt = static::db()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Executa operações dentro de uma transação.
     * Em caso de exceção, faz rollback e relança.
     */
    public static function transaction(callable $callback): mixed
    {
        $db = static::db();
        $db->beginTransaction();
        try {
            $result = $callback($db);
            $db->commit();
            return $result;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
