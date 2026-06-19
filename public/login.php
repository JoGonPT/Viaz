<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

use App\Auth;
use App\Csrf;

if (Auth::check()) {
    header('Location: /index.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        $error = 'Sessão inválida, tente novamente.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (Auth::attempt($email, $password)) {
            header('Location: /index.php');
            exit;
        }

        $error = 'Credenciais inválidas.';
    }
}

$pageTitle = 'Entrar';
require __DIR__ . '/../views/header.php';
?>
    <div class="auth-container">
        <h1>Entrar</h1>
        <?php if ($error): ?>
            <p class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES) ?></p>
        <?php endif; ?>
        <form method="post" action="/login.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES) ?>">
            <label>Email <input type="email" name="email" required></label>
            <label>Password <input type="password" name="password" required></label>
            <button type="submit">Entrar</button>
        </form>
        <p class="muted">
            <a href="/forgot-password.php">Esqueceste-te da password?</a><br>
            Ainda não tens conta? <a href="/register.php">Regista-te como parceiro</a>
        </p>
    </div>
<?php require __DIR__ . '/../views/footer.php'; ?>
