<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

use App\Auth;
use App\Csrf;
use App\Database;

Auth::requireRole('admin');

$pdo = Database::connection();

$generatedPassword = null;
$resolvedEmail = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Pedido inválido.');
    }

    $requestId = (int) ($_POST['request_id'] ?? 0);

    $requestStmt = $pdo->prepare(
        "SELECT id, user_id, email FROM password_reset_requests WHERE id = :id AND status = 'pending'"
    );
    $requestStmt->execute(['id' => $requestId]);
    $request = $requestStmt->fetch();

    if ($request) {
        $generatedPassword = substr(bin2hex(random_bytes(6)), 0, 10);

        $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id')
            ->execute(['hash' => password_hash($generatedPassword, PASSWORD_DEFAULT), 'id' => $request['user_id']]);

        $pdo->prepare(
            "UPDATE password_reset_requests
             SET status = 'resolved', resolved_at = NOW(), resolved_by_user_id = :admin_id
             WHERE id = :id"
        )->execute(['admin_id' => $_SESSION['user_id'], 'id' => $request['id']]);

        $resolvedEmail = $request['email'];
    }
}

$requests = $pdo->query(
    "SELECT id, email, status, created_at
     FROM password_reset_requests
     ORDER BY status = 'pending' DESC, created_at DESC"
)->fetchAll();

$pageTitle = 'Pedidos de recuperação de password';
require __DIR__ . '/../views/header.php';
?>
    <h1>Pedidos de recuperação de password</h1>

    <?php if ($generatedPassword !== null): ?>
        <p class="alert alert-success">
            Nova password gerada para <strong><?= htmlspecialchars($resolvedEmail, ENT_QUOTES) ?></strong>:
            <code><?= htmlspecialchars($generatedPassword, ENT_QUOTES) ?></code><br>
            Comunica-a ao utilizador por fora do sistema (telefone, etc.) — não vai voltar a ser mostrada.
        </p>
    <?php endif; ?>

    <?php if ($requests === []): ?>
        <p class="muted">Não há pedidos de recuperação.</p>
    <?php else: ?>
        <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>Email</th>
                    <th>Pedido em</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($requests as $request): ?>
                    <tr>
                        <td><?= htmlspecialchars($request['email'], ENT_QUOTES) ?></td>
                        <td><?= htmlspecialchars($request['created_at'], ENT_QUOTES) ?></td>
                        <td><span class="badge"><?= htmlspecialchars($request['status'], ENT_QUOTES) ?></span></td>
                        <td>
                            <?php if ($request['status'] === 'pending'): ?>
                                <form class="inline" method="post" action="/password-reset-requests.php">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES) ?>">
                                    <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                                    <button type="submit">Gerar nova password</button>
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
