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

function transition_trip(PDO $pdo, array $trip, string $toStatus, int $userId): string
{
    $stmt = $pdo->prepare(
        'UPDATE trips SET status = :to_status, version = version + 1
         WHERE id = :id AND status = :from_status AND version = :version'
    );
    $stmt->execute([
        'to_status' => $toStatus,
        'id' => $trip['id'],
        'from_status' => $trip['status'],
        'version' => $trip['version'],
    ]);

    if ($stmt->rowCount() !== 1) {
        return 'conflict';
    }

    $pdo->prepare(
        'INSERT INTO trip_status_history (trip_id, from_status, to_status, changed_by_user_id)
         VALUES (:trip_id, :from_status, :to_status, :user_id)'
    )->execute([
        'trip_id' => $trip['id'],
        'from_status' => $trip['status'],
        'to_status' => $toStatus,
        'user_id' => $userId,
    ]);

    return 'updated';
}

function cancel_trip(PDO $pdo, array $trip, int $userId, string $reason): string
{
    $stmt = $pdo->prepare(
        "UPDATE trips
         SET status = 'open', assigned_partner_id = NULL, assigned_vehicle_id = NULL, assigned_driver_id = NULL,
             cancellation_reason = :reason, version = version + 1
         WHERE id = :id AND status = :from_status AND version = :version"
    );
    $stmt->execute([
        'reason' => $reason !== '' ? $reason : null,
        'id' => $trip['id'],
        'from_status' => $trip['status'],
        'version' => $trip['version'],
    ]);

    if ($stmt->rowCount() !== 1) {
        return 'conflict';
    }

    $pdo->prepare(
        "INSERT INTO trip_status_history (trip_id, from_status, to_status, changed_by_user_id, reason)
         VALUES (:trip_id, :from_status, 'open', :user_id, :reason)"
    )->execute([
        'trip_id' => $trip['id'],
        'from_status' => $trip['status'],
        'user_id' => $userId,
        'reason' => $reason !== '' ? $reason : null,
    ]);

    return 'cancelled';
}

$tripId = (int) ($_POST['trip_id'] ?? 0);
$action = $_POST['action'] ?? '';
$reason = trim($_POST['reason'] ?? '');

$pdo = Database::connection();

$partnerStmt = $pdo->prepare('SELECT id, status FROM partners WHERE user_id = :user_id');
$partnerStmt->execute(['user_id' => $_SESSION['user_id']]);
$partner = $partnerStmt->fetch();

if (!$partner) {
    http_response_code(403);
    exit('Conta de parceiro não encontrada.');
}

if ($partner['status'] !== 'active') {
    http_response_code(403);
    exit('A tua conta de parceiro está pendente de aprovação.');
}

$tripStmt = $pdo->prepare(
    'SELECT id, status, version
     FROM trips
     WHERE id = :trip_id AND assigned_partner_id = :partner_id'
);
$tripStmt->execute(['trip_id' => $tripId, 'partner_id' => $partner['id']]);
$trip = $tripStmt->fetch();

$outcome = 'not_found';

if ($trip) {
    if ($action === 'start' && $trip['status'] === 'assigned') {
        $outcome = transition_trip($pdo, $trip, 'in_progress', (int) $_SESSION['user_id']);
    } elseif ($action === 'complete' && $trip['status'] === 'in_progress') {
        $outcome = transition_trip($pdo, $trip, 'completed', (int) $_SESSION['user_id']);
    } elseif ($action === 'cancel' && in_array($trip['status'], ['assigned', 'in_progress'], true)) {
        $outcome = cancel_trip($pdo, $trip, (int) $_SESSION['user_id'], $reason);
    } else {
        $outcome = 'invalid_transition';
    }
}

header('Location: /my-trips.php?outcome=' . $outcome);
exit;
