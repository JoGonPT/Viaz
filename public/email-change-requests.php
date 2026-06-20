<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

use App\Auth;
use App\Csrf;
use App\Database;

Auth::requireRole('admin');

$pdo = Database::connection();

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Pedido inválido.');
    }

    $requestId = (int) ($_POST['request_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    $requestStmt = $pdo->prepare(
        "SELECT id, user_id, new_email FROM email_change_requests WHERE id = :id AND status = 'pending'"
    );
    $requestStmt->execute(['id' => $requestId]);
    $request = $requestStmt->fetch();

    if ($request && $action === 'approve') {
        $emailExistsStmt = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id != :id');
        $emailExistsStmt->execute(['email' => $request['new_email'], 'id' => $request['user_id']]);

        if ($emailExistsStmt->fetch()) {
            $errors[] = 'Não foi possível aprovar: esse email já está a ser usado por outra conta entretanto.';
        } else {
            $pdo->prepare('UPDATE users SET email = :email WHERE id = :id')
                ->execute(['email' => $request['new_email'], 'id' => $request['user_id']]);

            $pdo->prepare(
                "UPDATE email_change_requests SET status = 'approved', resolved_at = NOW(), resolved_by_user_id = :admin_id WHERE id = :id"
            )->execute(['admin_id' => $_SESSION['user_id'], 'id' => $request['id']]);

            $success = 'Email atualizado com sucesso.';
        }
    } elseif ($request && $action === 'reject') {
        $pdo->prepare(
            "UPDATE email_change_requests SET status = 'rejected', resolved_at = NOW(), resolved_by_user_id = :admin_id WHERE id = :id"
        )->execute(['admin_id' => $_SESSION['user_id'], 'id' => $request['id']]);

        $success = 'Pedido rejeitado.';
    }
}

$requests = $pdo->query(
    "SELECT ecr.id, ecr.current_email, ecr.new_email, ecr.status, ecr.created_at, u.full_name
     FROM email_change_requests ecr
     INNER JOIN users u ON u.id = ecr.user_id
     ORDER BY ecr.status = 'pending' DESC, ecr.created_at DESC"
)->fetchAll();

$pageTitle = 'Pedidos de alteração de email';
require __DIR__ . '/../views/header.php';
?>
    <h1>Pedidos de alteração de email</h1>

    <?php if ($success !== null): ?>
        <p class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES) ?></p>
    <?php endif; ?>

    <?php foreach ($errors as $error): ?>
        <p class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES) ?></p>
    <?php endforeach; ?>

    <?php if ($requests === []): ?>
        <p class="muted">Não há pedidos de alteração de email.</p>
    <?php else: ?>
        <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>Utilizador</th>
                    <th>Email atual</th>
                    <th>Novo email</th>
                    <th>Pedido em</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($requests as $request): ?>
                    <tr>
                        <td><?= htmlspecialchars($request['full_name'], ENT_QUOTES) ?></td>
                        <td><?= htmlspecialchars($request['current_email'], ENT_QUOTES) ?></td>
                        <td><?= htmlspecialchars($request['new_email'], ENT_QUOTES) ?></td>
                        <td><?= htmlspecialchars($request['created_at'], ENT_QUOTES) ?></td>
                        <td><span class="badge"><?= htmlspecialchars($request['status'], ENT_QUOTES) ?></span></td>
                        <td class="actions-cell">
                            <?php if ($request['status'] === 'pending'): ?>
                                <form class="inline" method="post" action="/email-change-requests.php">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES) ?>">
                                    <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit">Aprovar</button>
                                </form>
                                <form class="inline" method="post" action="/email-change-requests.php">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES) ?>">
                                    <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="btn-danger">Rejeitar</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
<?php require __DIR__ . '/../views/footer.php'; ?>
