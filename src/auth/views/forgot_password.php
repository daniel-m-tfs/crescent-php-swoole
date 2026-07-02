<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Esqueci minha senha</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    body { font-family: sans-serif; background: #f5f5f5; display: flex;
           justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 24px; }
    .card { background: #fff; border-radius: 10px; box-shadow: 0 4px 20px #0001;
            padding: 40px 36px; width: 100%; max-width: 420px; }
    h1 { margin: 0 0 8px; font-size: 1.6rem; color: #1e1e2e; }
    p.subtitle { color: #666; margin: 0 0 28px; font-size: .95rem; }
    label { display: block; margin-bottom: 4px; font-size: .875rem; color: #555; font-weight: 500; }
    input[type=email] {
      width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 6px;
      font-size: 1rem; outline: none; transition: border .2s;
    }
    input:focus { border-color: #4f46e5; box-shadow: 0 0 0 3px #4f46e510; }
    .field { margin-bottom: 24px; }
    .btn {
      width: 100%; padding: 11px; background: #4f46e5; color: #fff; border: none;
      border-radius: 6px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: background .2s;
    }
    .btn:hover { background: #4338ca; }
    .alert { border-radius: 6px; padding: 12px 14px; margin-bottom: 20px; font-size: .9rem; }
    .alert-error { background: #fee2e2; color: #b91c1c; }
    .alert-success { background: #dcfce7; color: #166534; }
    .links { margin-top: 20px; text-align: center; font-size: .875rem; }
    .links a { color: #4f46e5; text-decoration: none; }
    .links a:hover { text-decoration: underline; }
  </style>
</head>
<body>
<div class="card">
  <h1>Esqueci minha senha</h1>
  <p class="subtitle">Digite seu e-mail e enviaremos um link para redefinir sua senha.</p>

  <?php if (!empty($error)): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <?php if (!empty($sent)): ?>
    <div class="alert alert-success">
      Se o e-mail estiver cadastrado, você receberá as instruções em breve.
      Verifique também sua caixa de spam.
    </div>
  <?php else: ?>
    <form method="POST" action="/auth/forgot-password" novalidate>
      <div class="field">
        <label for="email">E-mail</label>
        <input type="email" id="email" name="email" required autocomplete="email">
      </div>

      <button type="submit" class="btn">Enviar link de redefinição</button>
    </form>
  <?php endif; ?>

  <div class="links">
    <a href="/auth/login">← Voltar ao login</a>
  </div>
</div>
</body>
</html>
