<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

use App\Auth;
use App\Csrf;
use App\Database;

Auth::requireRole('partner');

$pdo = Database::connection();

$partnerStmt = $pdo->prepare('SELECT id, status FROM partners WHERE user_id = :user_id');
$partnerStmt->execute(['user_id' => $_SESSION['user_id']]);
$partner = $partnerStmt->fetch();

if (!$partner) {
    http_response_code(403);
    exit('Conta de parceiro não encontrada.');
}

if ($partner['status'] !== 'active') {
    $pageTitle = 'As minhas viagens';
    require __DIR__ . '/../views/header.php';
    echo '<p class="alert alert-warning">A tua conta de parceiro está pendente de aprovação por um administrador.</p>';
    require __DIR__ . '/../views/footer.php';
    exit;
}

$tripsStmt = $pdo->prepare(
    "SELECT t.id, t.service_group_id, t.passengers_count, t.luggage_count, t.scheduled_at, t.listed_price, t.status,
            sg.origin, sg.destination, sg.total_passengers, sg.total_luggage, v.license_plate
     FROM trips t
     INNER JOIN service_groups sg ON sg.id = t.service_group_id
     LEFT JOIN vehicles v ON v.id = t.assigned_vehicle_id
     WHERE t.assigned_partner_id = :partner_id
     ORDER BY t.scheduled_at DESC"
);
$tripsStmt->execute(['partner_id' => $partner['id']]);
$trips = $tripsStmt->fetchAll();

$outcomeMessages = [
    'updated' => ['class' => 'alert-success', 'text' => 'Estado da viagem atualizado.'],
    'cancelled' => ['class' => 'alert-success', 'text' => 'Viagem cancelada e reaberta no mural.'],
    'conflict' => ['class' => 'alert-error', 'text' => 'Não foi possível atualizar — o estado já tinha mudado entretanto.'],
    'not_found' => ['class' => 'alert-error', 'text' => 'Viagem não encontrada.'],
    'invalid_transition' => ['class' => 'alert-error', 'text' => 'Essa ação não é válida para o estado atual da viagem.'],
];
$outcome = $outcomeMessages[$_GET['outcome'] ?? ''] ?? null;

$pageTitle = 'As minhas viagens';
require __DIR__ . '/../views/header.php';
?>
    <h1>As minhas viagens</h1>

    <?php if ($outcome !== null): ?>
        <p class="alert <?= $outcome['class'] ?>"><?= htmlspecialchars($outcome['text'], ENT_QUOTES) ?></p>
    <?php endif; ?>

    <?php if ($trips === []): ?>
        <p class="muted">Ainda não aceitaste nenhuma viagem.</p>
    <?php else: ?>
        <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>Origem</th>
                    <th>Destino</th>
                    <th>Data/Hora</th>
                    <th>Passageiros</th>
                    <th>Malas</th>
                    <th>Viatura</th>
                    <th>Preço</th>
                    <th>Estado</th>
                    <th></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($trips as $trip): ?>
                    <tr>
                        <td><?= htmlspecialchars($trip['origin'], ENT_QUOTES) ?></td>
                        <td><?= htmlspecialchars($trip['destination'], ENT_QUOTES) ?></td>
                        <td><?= htmlspecialchars($trip['scheduled_at'], ENT_QUOTES) ?></td>
                        <td><?= (int) $trip['passengers_count'] ?></td>
                        <td><?= (int) $trip['luggage_count'] ?></td>
                        <td><?= htmlspecialchars($trip['license_plate'] ?? '-', ENT_QUOTES) ?></td>
                        <td><?= $trip['listed_price'] !== null ? htmlspecialchars((string) $trip['listed_price'], ENT_QUOTES) . ' €' : '-' ?></td>
                        <td><span class="badge"><?= htmlspecialchars($trip['status'], ENT_QUOTES) ?></span></td>
                        <td class="actions-cell">
                            <?php if ($trip['status'] === 'assigned'): ?>
                                <form class="inline" method="post" action="/update-trip-status.php">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES) ?>">
                                    <input type="hidden" name="trip_id" value="<?= (int) $trip['id'] ?>">
                                    <input type="hidden" name="action" value="start">
                                    <button type="submit">Iniciar</button>
                                </form>
                            <?php endif; ?>

                            <?php if ($trip['status'] === 'in_progress'): ?>
                                <form class="inline" method="post" action="/update-trip-status.php">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES) ?>">
                                    <input type="hidden" name="trip_id" value="<?= (int) $trip['id'] ?>">
                                    <input type="hidden" name="action" value="complete">
                                    <button type="submit">Concluir</button>
                                </form>
                            <?php endif; ?>

                            <?php if (in_array($trip['status'], ['assigned', 'in_progress'], true)): ?>
                                <form class="inline" method="post" action="/update-trip-status.php">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES) ?>">
                                    <input type="hidden" name="trip_id" value="<?= (int) $trip['id'] ?>">
                                    <input type="hidden" name="action" value="cancel">
                                    <input type="text" name="reason" placeholder="Motivo (opcional)">
                                    <button type="submit" class="btn-danger">Cancelar</button>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ((int) $trip['total_passengers'] > (int) $trip['passengers_count']): ?>
                                <a href="/group-trips.php?group_id=<?= (int) $trip['service_group_id'] ?>">Repassar parte do grupo</a>
                            <?php else: ?>
                                <a href="/group-trips.php?group_id=<?= (int) $trip['service_group_id'] ?>">Ver grupo</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
<?php require __DIR__ . '/../views/footer.php'; ?>
