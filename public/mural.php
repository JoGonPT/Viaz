<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

use App\Auth;
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

$vehiclesStmt = $pdo->prepare(
    "SELECT id, license_plate, vehicle_type, seats_capacity, luggage_capacity
     FROM vehicles
     WHERE partner_id = :partner_id AND status = 'active'
     ORDER BY license_plate"
);
$vehiclesStmt->execute(['partner_id' => $partner['id']]);
$vehicles = $vehiclesStmt->fetchAll();

$selectedVehicle = null;
$requestedVehicleId = $_GET['vehicle_id'] ?? null;

foreach ($vehicles as $vehicle) {
    if ($requestedVehicleId !== null && (int) $vehicle['id'] === (int) $requestedVehicleId) {
        $selectedVehicle = $vehicle;
        break;
    }
}

if ($selectedVehicle === null && $vehicles !== []) {
    $selectedVehicle = $vehicles[0];
}

$trips = [];

if ($selectedVehicle !== null) {
    $tripsStmt = $pdo->prepare(
        "SELECT t.id, t.passengers_count, t.luggage_count, t.scheduled_at, t.listed_price,
                sg.origin, sg.destination
         FROM trips t
         INNER JOIN service_groups sg ON sg.id = t.service_group_id
         WHERE t.visibility = 'public'
           AND t.status = 'open'
           AND t.passengers_count <= :seats_capacity
           AND t.luggage_count <= :luggage_capacity
         ORDER BY t.scheduled_at ASC"
    );
    $tripsStmt->execute([
        'seats_capacity' => $selectedVehicle['seats_capacity'],
        'luggage_capacity' => $selectedVehicle['luggage_capacity'],
    ]);
    $trips = $tripsStmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Mural</title>
</head>
<body>
    <h1>Mural de viagens</h1>
    <p><a href="/index.php">Voltar</a></p>

    <?php if ($vehicles === []): ?>
        <p>Não tens viaturas ativas registadas.</p>
    <?php else: ?>
        <form method="get" action="/mural.php">
            <label>Viatura:
                <select name="vehicle_id" onchange="this.form.submit()">
                    <?php foreach ($vehicles as $vehicle): ?>
                        <option value="<?= (int) $vehicle['id'] ?>" <?= $selectedVehicle && (int) $selectedVehicle['id'] === (int) $vehicle['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($vehicle['license_plate'], ENT_QUOTES) ?>
                            (<?= (int) $vehicle['seats_capacity'] ?> lugares, <?= (int) $vehicle['luggage_capacity'] ?> malas)
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </form>

        <?php if ($trips === []): ?>
            <p>Não há viagens elegíveis para esta viatura neste momento.</p>
        <?php else: ?>
            <table border="1" cellpadding="6">
                <thead>
                    <tr>
                        <th>Origem</th>
                        <th>Destino</th>
                        <th>Data/Hora</th>
                        <th>Passageiros</th>
                        <th>Malas</th>
                        <th>Preço</th>
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
                            <td><?= $trip['listed_price'] !== null ? htmlspecialchars((string) $trip['listed_price'], ENT_QUOTES) . ' €' : '-' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>
</body>
</html>
