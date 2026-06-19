<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

use App\Auth;
use App\Csrf;
use App\Database;

Auth::requireRole('partner');

$pdo = Database::connection();

$partnerStmt = $pdo->prepare('SELECT id FROM partners WHERE user_id = :user_id');
$partnerStmt->execute(['user_id' => $_SESSION['user_id']]);
$partner = $partnerStmt->fetch();

if (!$partner) {
    http_response_code(403);
    exit('Conta de parceiro não encontrada.');
}

$tripsStmt = $pdo->prepare(
    "SELECT t.id, t.passengers_count, t.luggage_count, t.scheduled_at, t.listed_price, t.status,
            sg.origin, sg.destination, v.license_plate
     FROM trips t
     INNER JOIN service_groups sg ON sg.id = t.service_group_id
     LEFT JOIN vehicles v ON v.id = t.assigned_vehicle_id
     WHERE t.assigned_partner_id = :partner_id
     ORDER BY t.scheduled_at DESC"
);
$tripsStmt->execute(['partner_id' => $partner['id']]);
$trips = $tripsStmt->fetchAll();

$outcomeMessages = [
    'updated' => ['color' => 'green', 'text' => 'Estado da viagem atualizado.'],
    'cancelled' => ['color' => 'green', 'text' => 'Viagem cancelada e reaberta no mural.'],
    'conflict' => ['color' => 'red', 'text' => 'Não foi possível atualizar — o estado já tinha mudado entretanto.'],
    'not_found' => ['color' => 'red', 'text' => 'Viagem não encontrada.'],
    'invalid_transition' => ['color' => 'red', 'text' => 'Essa ação não é válida para o estado atual da viagem.'],
];
$outcome = $outcomeMessages[$_GET['outcome'] ?? ''] ?? null;
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>As minhas viagens</title>
</head>
<body>
    <h1>As minhas viagens</h1>
    <p><a href="/index.php">Voltar</a></p>

    <?php if ($outcome !== null): ?>
        <p style="color:<?= $outcome['color'] ?>;"><?= htmlspecialchars($outcome['text'], ENT_QUOTES) ?></p>
    <?php endif; ?>

    <?php if ($trips === []): ?>
        <p>Ainda não aceitaste nenhuma viagem.</p>
    <?php else: ?>
        <table border="1" cellpadding="6">
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
                        <td><?= htmlspecialchars($trip['status'], ENT_QUOTES) ?></td>
                        <td>
                            <?php if ($trip['status'] === 'assigned'): ?>
                                <form method="post" action="/update-trip-status.php" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES) ?>">
                                    <input type="hidden" name="trip_id" value="<?= (int) $trip['id'] ?>">
                                    <input type="hidden" name="action" value="start">
                                    <button type="submit">Iniciar</button>
                                </form>
                            <?php endif; ?>

                            <?php if ($trip['status'] === 'in_progress'): ?>
                                <form method="post" action="/update-trip-status.php" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES) ?>">
                                    <input type="hidden" name="trip_id" value="<?= (int) $trip['id'] ?>">
                                    <input type="hidden" name="action" value="complete">
                                    <button type="submit">Concluir</button>
                                </form>
                            <?php endif; ?>

                            <?php if (in_array($trip['status'], ['assigned', 'in_progress'], true)): ?>
                                <form method="post" action="/update-trip-status.php" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES) ?>">
                                    <input type="hidden" name="trip_id" value="<?= (int) $trip['id'] ?>">
                                    <input type="hidden" name="action" value="cancel">
                                    <input type="text" name="reason" placeholder="Motivo (opcional)">
                                    <button type="submit">Cancelar</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>
