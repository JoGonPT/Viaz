<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

use App\Auth;
use App\Csrf;
use App\Database;

if (Auth::check()) {
    header('Location: /index.php');
    exit;
}

$submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (Csrf::verify($_POST['csrf_token'] ?? null)) {
        $email = trim($_POST['email'] ?? '');

        if ($email !== '') {
            $pdo = Database::connection();

            $userStmt = $pdo->prepare('SELECT id FROM users WHERE email = :email');
            $userStmt->execute(['email' => $email]);
            $user = $userStmt->fetch();

            if ($user) {
                $pdo->prepare(
                    "INSERT INTO password_reset_requests (user_id, email, status)
                     VALUES (:user_id, :email, 'pending')"
                )->execute(['user_id' => $user['id'], 'email' => $email]);
            }
        }
    }

    $submitted = true;
}

$pageTitle = 'Recuperar password';
require __DIR__ . '/../views/header.php';
?>
    <div class="auth-container">
        <h1>Recuperar password</h1>

        <?php if ($submitted): ?>
            <p class="alert alert-success">Pedido registado. A nossa equipa vai contactar-te em breve com uma nova password.</p>
            <p><a href="/login.php">Voltar ao login</a></p>
        <?php else: ?>
            <p class="muted">Indica o email da tua conta. Um administrador vai contactar-te com uma nova password.</p>
            <form method="post" action="/forgot-password.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES) ?>">
                <label>Email <input type="email" name="email" required></label>
                <button type="submit">Pedir recuperação</button>
            </form>
            <p class="muted"><a href="/login.php">Voltar ao login</a></p>
        <?php endif; ?>
    </div>
<?php require __DIR__ . '/../views/footer.php'; ?>
