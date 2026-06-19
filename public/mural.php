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
$privateInvites = [];

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

    $privateStmt = $pdo->prepare(
        "SELECT t.id, t.passengers_count, t.luggage_count, t.scheduled_at, t.listed_price,
                sg.origin, sg.destination
         FROM trips t
         INNER JOIN service_groups sg ON sg.id = t.service_group_id
         WHERE t.visibility = 'private'
           AND t.invited_partner_id = :partner_id
           AND t.status = 'open'
           AND t.passengers_count <= :seats_capacity
           AND t.luggage_count <= :luggage_capacity
         ORDER BY t.scheduled_at ASC"
    );
    $privateStmt->execute([
        'partner_id' => $partner['id'],
        'seats_capacity' => $selectedVehicle['seats_capacity'],
        'luggage_capacity' => $selectedVehicle['luggage_capacity'],
    ]);
    $privateInvites = $privateStmt->fetchAll();
}

function render_trips_table(array $trips, int $vehicleId): void
{
    ?>
    <div class="table-scroll">
    <table>
        <thead>
            <tr>
                <th>Origem</th>
                <th>Destino</th>
                <th>Data/Hora</th>
                <th>Passageiros</th>
                <th>Malas</th>
                <th>Preço</th>
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
                    <td><?= $trip['listed_price'] !== null ? htmlspecialchars((string) $trip['listed_price'], ENT_QUOTES) . ' €' : '-' ?></td>
                    <td>
                        <form class="inline" method="post" action="/accept-trip.php">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES) ?>">
                            <input type="hidden" name="trip_id" value="<?= (int) $trip['id'] ?>">
                            <input type="hidden" name="vehicle_id" value="<?= $vehicleId ?>">
                            <button type="submit">Aceitar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php
}

$pageTitle = 'Mural';
require __DIR__ . '/../views/header.php';
?>
    <h1>Mural de viagens</h1>

    <?php if (($_GET['outcome'] ?? null) === 'accepted'): ?>
        <p class="alert alert-success">Viagem aceite com sucesso.</p>
    <?php elseif (($_GET['outcome'] ?? null) === 'accepted_warning'): ?>
        <p class="alert alert-warning">Viagem aceite, mas atenção: esta viatura tem outra viagem a menos de 2 horas de distância. Confirma que consegues cumprir as duas, ou usa outra viatura/motorista.</p>
    <?php elseif (($_GET['outcome'] ?? null) === 'schedule_conflict'): ?>
        <p class="alert alert-error">Não foi possível aceitar: esta viatura já tem outra viagem exatamente à mesma hora. Escolhe outra viatura ou liberta a viagem em conflito primeiro.</p>
    <?php elseif (($_GET['outcome'] ?? null) === 'conflict'): ?>
        <p class="alert alert-error">Essa viagem já tinha sido aceite por outro parceiro entretanto.</p>
    <?php elseif (($_GET['outcome'] ?? null) === 'not_found'): ?>
        <p class="alert alert-error">Essa viagem já não está disponível.</p>
    <?php endif; ?>

    <?php if ($vehicles === []): ?>
        <p class="muted">Não tens viaturas ativas registadas.</p>
    <?php else: ?>
        <form method="get" action="/mural.php">
            <label>Viatura
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

        <h2>Convites privados</h2>
        <?php if ($privateInvites === []): ?>
            <p class="muted">Não tens convites privados em aberto para esta viatura.</p>
        <?php else: ?>
            <?php render_trips_table($privateInvites, (int) $selectedVehicle['id']); ?>
        <?php endif; ?>

        <h2>Viagens públicas</h2>
        <?php if ($trips === []): ?>
            <p class="muted">Não há viagens elegíveis para esta viatura neste momento.</p>
        <?php else: ?>
            <?php render_trips_table($trips, (int) $selectedVehicle['id']); ?>
        <?php endif; ?>
    <?php endif; ?>
<?php require __DIR__ . '/../views/footer.php'; ?>
