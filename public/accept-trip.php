<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

use App\Auth;
use App\Csrf;
use App\Database;

Auth::requireRole('partner');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::verify($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Pedido inválido.');
}

$tripId = (int) ($_POST['trip_id'] ?? 0);
$vehicleId = (int) ($_POST['vehicle_id'] ?? 0);

$pdo = Database::connection();

$partnerStmt = $pdo->prepare('SELECT id FROM partners WHERE user_id = :user_id');
$partnerStmt->execute(['user_id' => $_SESSION['user_id']]);
$partner = $partnerStmt->fetch();

if (!$partner) {
    http_response_code(403);
    exit('Conta de parceiro não encontrada.');
}

$vehicleStmt = $pdo->prepare(
    "SELECT id, seats_capacity, luggage_capacity
     FROM vehicles
     WHERE id = :vehicle_id AND partner_id = :partner_id AND status = 'active'"
);
$vehicleStmt->execute(['vehicle_id' => $vehicleId, 'partner_id' => $partner['id']]);
$vehicle = $vehicleStmt->fetch();

if (!$vehicle) {
    http_response_code(403);
    exit('Viatura inválida.');
}

$tripStmt = $pdo->prepare(
    "SELECT id, passengers_count, luggage_count, status, version
     FROM trips
     WHERE id = :trip_id
       AND (visibility = 'public' OR (visibility = 'private' AND invited_partner_id = :partner_id))"
);
$tripStmt->execute(['trip_id' => $tripId, 'partner_id' => $partner['id']]);
$trip = $tripStmt->fetch();

$outcome = 'not_found';

if ($trip
    && $trip['status'] === 'open'
    && (int) $trip['passengers_count'] <= (int) $vehicle['seats_capacity']
    && (int) $trip['luggage_count'] <= (int) $vehicle['luggage_capacity']
) {
    $updateStmt = $pdo->prepare(
        "UPDATE trips
         SET status = 'assigned', assigned_partner_id = :partner_id, assigned_vehicle_id = :vehicle_id, version = version + 1
         WHERE id = :trip_id AND status = 'open' AND version = :version"
    );
    $updateStmt->execute([
        'partner_id' => $partner['id'],
        'vehicle_id' => $vehicle['id'],
        'trip_id' => $trip['id'],
        'version' => $trip['version'],
    ]);

    if ($updateStmt->rowCount() === 1) {
        $pdo->prepare(
            "INSERT INTO trip_status_history (trip_id, from_status, to_status, changed_by_user_id)
             VALUES (:trip_id, 'open', 'assigned', :user_id)"
        )->execute(['trip_id' => $trip['id'], 'user_id' => $_SESSION['user_id']]);

        $outcome = 'accepted';
    } else {
        $outcome = 'conflict';
    }
}

header('Location: /mural.php?vehicle_id=' . $vehicleId . '&outcome=' . $outcome);
exit;
