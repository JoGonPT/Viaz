<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

use App\Auth;
use App\Database;

Auth::requireRole('admin');

$pdo = Database::connection();

$groups = $pdo->query(
    "SELECT sg.id, sg.origin, sg.destination, sg.scheduled_at, sg.total_passengers, sg.total_luggage,
            sg.split_status, c.company_name
     FROM service_groups sg
     INNER JOIN clients c ON c.id = sg.client_id
     ORDER BY sg.scheduled_at DESC"
)->fetchAll();
$pageTitle = 'Grupos de serviço';
require __DIR__ . '/../views/header.php';
?>
    <h1>Grupos de serviço</h1>
    <p><a href="/group-new.php">+ Novo grupo</a></p>

    <?php if ($groups === []): ?>
        <p class="muted">Ainda não há grupos criados.</p>
    <?php else: ?>
        <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Origem</th>
                    <th>Destino</th>
                    <th>Data/Hora</th>
                    <th>Passageiros</th>
                    <th>Malas</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($groups as $group): ?>
                    <tr>
                        <td><?= htmlspecialchars($group['company_name'], ENT_QUOTES) ?></td>
                        <td><?= htmlspecialchars($group['origin'], ENT_QUOTES) ?></td>
                        <td><?= htmlspecialchars($group['destination'], ENT_QUOTES) ?></td>
                        <td><?= htmlspecialchars($group['scheduled_at'], ENT_QUOTES) ?></td>
                        <td><?= (int) $group['total_passengers'] ?></td>
                        <td><?= (int) $group['total_luggage'] ?></td>
                        <td><span class="badge"><?= htmlspecialchars($group['split_status'], ENT_QUOTES) ?></span></td>
                        <td><a href="/group-trips.php?group_id=<?= (int) $group['id'] ?>">Gerir</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
<?php require __DIR__ . '/../views/footer.php'; ?>
