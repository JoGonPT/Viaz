<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

use App\Auth;
use App\Csrf;
use App\Database;

Auth::requireLogin();

$pdo = Database::connection();

$userStmt = $pdo->prepare('SELECT id, full_name, email, phone FROM users WHERE id = :id');
$userStmt->execute(['id' => $_SESSION['user_id']]);
$user = $userStmt->fetch();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Sessão inválida, tente novamente.';
    } else {
        $fullName = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if ($fullName === '') {
            $errors[] = 'O nome não pode estar vazio.';
        }

        if ($errors === []) {
            $pdo->prepare('UPDATE users SET full_name = :full_name, phone = :phone WHERE id = :id')
                ->execute([
                    'full_name' => $fullName,
                    'phone' => $phone !== '' ? $phone : null,
                    'id' => $user['id'],
                ]);

            $user['full_name'] = $fullName;
            $user['phone'] = $phone !== '' ? $phone : null;
            $_SESSION['full_name'] = $fullName;
            $success = true;
        }
    }
}

$pageTitle = 'O meu perfil';
require __DIR__ . '/../views/header.php';
?>
    <h1>O meu perfil</h1>

    <?php if ($success): ?>
        <p class="alert alert-success">Perfil atualizado com sucesso.</p>
    <?php endif; ?>

    <?php foreach ($errors as $error): ?>
        <p class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES) ?></p>
    <?php endforeach; ?>

    <?php if (empty($user['phone'])): ?>
        <p class="alert alert-warning">Sem telefone definido, o botão de WhatsApp não aparece nas viagens que crias. Define um número abaixo.</p>
    <?php endif; ?>

    <form method="post" action="/profile.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES) ?>">

        <label>Email <input type="email" value="<?= htmlspecialchars($user['email'], ENT_QUOTES) ?>" disabled></label>
        <label>Nome completo <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name'], ENT_QUOTES) ?>" required></label>
        <label>Telefone (usado no botão de WhatsApp) <input type="tel" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '', ENT_QUOTES) ?>" placeholder="ex: 351912345678"></label>

        <button type="submit">Guardar</button>
    </form>
<?php require __DIR__ . '/../views/footer.php'; ?>
