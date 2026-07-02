<?php

namespace App\Users\Controllers;

use App\Users\Models\UserModel;
use Crescent\Core\Context;
use Crescent\Middleware\Auth;

/**
 * Controller de usuários.
 *
 * Camada entre as rotas e o modelo:
 *  - Valida os dados de entrada
 *  - Chama o Model
 *  - Monta a resposta (JSON para API ou View para web)
 *
 * Todos os métodos recebem $ctx (Context) e retornam
 * um array (→ JSON automático) ou chamam $ctx->view() / $ctx->json().
 */
class UserController
{
    // ─── API ──────────────────────────────────────────────────────────────────

    /** GET /api/users */
    public static function index(Context $ctx): void
    {
        $users = UserModel::getAll();

        // Remove senhas antes de devolver
        $users = array_map(fn ($u) => static::sanitize($u), $users);

        $ctx->json(['data' => $users, 'total' => count($users)]);
    }

    /** GET /api/users/:id */
    public static function show(Context $ctx): void
    {
        $user = UserModel::find((int) $ctx->params['id']);

        if (!$user) {
            $ctx->json(['error' => 'Usuário não encontrado'], 404);
            return;
        }

        $ctx->json(['data' => static::sanitize($user)]);
    }

    /** POST /api/users */
    public static function store(Context $ctx): void
    {
        $body = (array) $ctx->body;

        // Validação simples
        $errors = static::validate($body, ['name', 'email', 'password']);
        if ($errors) {
            $ctx->json(['errors' => $errors], 422);
            return;
        }

        // E-mail duplicado?
        if (UserModel::findByEmail($body['email'])) {
            $ctx->json(['error' => 'E-mail já cadastrado'], 409);
            return;
        }

        $id   = UserModel::create($body);
        $user = UserModel::find((int) $id);

        $ctx->status(201)->json(['data' => static::sanitize($user)]);
    }

    /** PUT /api/users/:id */
    public static function update(Context $ctx): void
    {
        $id   = (int) $ctx->params['id'];
        $body = (array) $ctx->body;

        if (!UserModel::find($id)) {
            $ctx->json(['error' => 'Usuário não encontrado'], 404);
            return;
        }

        UserModel::updateUser($id, $body);
        $user = UserModel::find($id);

        $ctx->json(['data' => static::sanitize($user)]);
    }

    /** DELETE /api/users/:id */
    public static function destroy(Context $ctx): void
    {
        $id = (int) $ctx->params['id'];

        if (!UserModel::find($id)) {
            $ctx->json(['error' => 'Usuário não encontrado'], 404);
            return;
        }

        UserModel::delete($id);
        $ctx->noContent();
    }

    // ─── Login / Auth ──────────────────────────────────────────────────────────

    /** POST /api/auth/login */
    public static function login(Context $ctx): void
    {
        $body  = (array) $ctx->body;
        $email = trim($body['email'] ?? '');
        $pass  = $body['password'] ?? '';

        $user = UserModel::authenticate($email, $pass);

        if (!$user) {
            $ctx->json(['error' => 'Credenciais inválidas'], 401);
            return;
        }

        $token = Auth::generateToken([
            'id'    => $user['id'],
            'email' => $user['email'],
            'name'  => $user['name'],
        ]);

        $ctx->json([
            'token' => $token,
            'user'  => static::sanitize($user),
        ]);
    }

    // ─── Views (Web) ───────────────────────────────────────────────────────────

    /** GET /users */
    public static function listView(Context $ctx): void
    {
        $users = UserModel::getAll();
        $ctx->view('users/views/users_all.php', compact('users'));
    }

    /** GET /users/form | GET /users/:id/form */
    public static function formView(Context $ctx): void
    {
        $user = isset($ctx->params['id'])
            ? UserModel::find((int) $ctx->params['id'])
            : null;

        $ctx->view('users/views/users_crud.php', compact('user'));
    }

    // ─── Helpers privados ─────────────────────────────────────────────────────

    /** Remove campos sensíveis do array de usuário. */
    private static function sanitize(array $user): array
    {
        unset($user['password']);
        return $user;
    }

    /** Valida campos obrigatórios. Retorna array de erros ou []. */
    private static function validate(array $data, array $required): array
    {
        $errors = [];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $errors[] = "O campo '{$field}' é obrigatório.";
            }
        }
        return $errors;
    }
}
