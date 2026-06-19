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
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Grupos de serviço</title>
</head>
<body>
    <h1>Grupos de serviço</h1>
    <p><a href="/index.php">Voltar</a> | <a href="/group-new.php">Novo grupo</a></p>

    <?php if ($groups === []): ?>
        <p>Ainda não há grupos criados.</p>
    <?php else: ?>
        <table border="1" cellpadding="6">
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
                        <td><?= htmlspecialchars($group['split_status'], ENT_QUOTES) ?></td>
                        <td><a href="/group-trips.php?group_id=<?= (int) $group['id'] ?>">Gerir</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>
