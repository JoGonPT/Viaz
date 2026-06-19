<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

use App\Auth;
use App\Csrf;
use App\Database;

Auth::requireRole('admin');

$pdo = Database::connection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Pedido inválido.');
    }

    $partnerId = (int) ($_POST['partner_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    $newStatus = match ($action) {
        'approve', 'reactivate' => 'active',
        'block' => 'blocked',
        default => null,
    };

    if ($newStatus !== null) {
        $pdo->prepare('UPDATE partners SET status = :status WHERE id = :id')
            ->execute(['status' => $newStatus, 'id' => $partnerId]);
    }

    header('Location: /partners.php');
    exit;
}

$partners = $pdo->query(
    "SELECT p.id, p.company_name, p.partner_type, p.status, u.email, u.full_name
     FROM partners p
     INNER JOIN users u ON u.id = p.user_id
     ORDER BY p.status = 'pending_approval' DESC, p.company_name"
)->fetchAll();

$pageTitle = 'Parceiros';
require __DIR__ . '/../views/header.php';
?>
    <h1>Parceiros</h1>

    <?php if ($partners === []): ?>
        <p class="muted">Ainda não há parceiros registados.</p>
    <?php else: ?>
        <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>Empresa</th>
                    <th>Tipo</th>
                    <th>Contacto</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($partners as $partner): ?>
                    <tr>
                        <td><?= htmlspecialchars($partner['company_name'], ENT_QUOTES) ?></td>
                        <td><?= htmlspecialchars($partner['partner_type'], ENT_QUOTES) ?></td>
                        <td><?= htmlspecialchars($partner['full_name'], ENT_QUOTES) ?> (<?= htmlspecialchars($partner['email'], ENT_QUOTES) ?>)</td>
                        <td><span class="badge"><?= htmlspecialchars($partner['status'], ENT_QUOTES) ?></span></td>
                        <td class="actions-cell">
                            <?php if ($partner['status'] === 'pending_approval'): ?>
                                <form class="inline" method="post" action="/partners.php">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES) ?>">
                                    <input type="hidden" name="partner_id" value="<?= (int) $partner['id'] ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit">Aprovar</button>
                                </form>
                            <?php endif; ?>

                            <?php if ($partner['status'] === 'active'): ?>
                                <form class="inline" method="post" action="/partners.php">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES) ?>">
                                    <input type="hidden" name="partner_id" value="<?= (int) $partner['id'] ?>">
                                    <input type="hidden" name="action" value="block">
                                    <button type="submit" class="btn-danger">Bloquear</button>
                                </form>
                            <?php endif; ?>

                            <?php if (in_array($partner['status'], ['blocked', 'suspended'], true)): ?>
                                <form class="inline" method="post" action="/partners.php">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES) ?>">
                                    <input type="hidden" name="partner_id" value="<?= (int) $partner['id'] ?>">
                                    <input type="hidden" name="action" value="reactivate">
                                    <button type="submit">Reativar</button>
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
