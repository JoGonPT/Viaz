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
    http_response_code(403);
    exit('A tua conta de parceiro está pendente de aprovação.');
}

$vehicleId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

$vehicleStmt = $pdo->prepare(
    'SELECT id, license_plate, vehicle_type, seats_capacity, luggage_capacity, status
     FROM vehicles
     WHERE id = :id AND partner_id = :partner_id'
);
$vehicleStmt->execute(['id' => $vehicleId, 'partner_id' => $partner['id']]);
$vehicle = $vehicleStmt->fetch();

if (!$vehicle) {
    http_response_code(404);
    exit('Viatura não encontrada.');
}

$vehicleTypes = ['sedan', 'minivan', 'van', 'minibus', 'bus'];
$statuses = ['active', 'maintenance', 'inactive'];
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Sessão inválida, tente novamente.';
    } else {
        $licensePlate = trim($_POST['license_plate'] ?? '');
        $vehicleType = $_POST['vehicle_type'] ?? '';
        $seatsCapacity = (int) ($_POST['seats_capacity'] ?? 0);
        $luggageCapacity = (int) ($_POST['luggage_capacity'] ?? 0);
        $status = $_POST['status'] ?? '';

        if ($licensePlate === '') {
            $errors[] = 'A matrícula é obrigatória.';
        }

        if (!in_array($vehicleType, $vehicleTypes, true)) {
            $errors[] = 'Tipo de viatura inválido.';
        }

        if (!in_array($status, $statuses, true)) {
            $errors[] = 'Estado inválido.';
        }

        if ($seatsCapacity <= 0) {
            $errors[] = 'Número de lugares tem de ser pelo menos 1.';
        }

        if ($luggageCapacity < 0) {
            $errors[] = 'Capacidade de malas não pode ser negativa.';
        }

        if ($errors === []) {
            try {
                $pdo->prepare(
                    "UPDATE vehicles
                     SET license_plate = :license_plate, vehicle_type = :vehicle_type,
                         seats_capacity = :seats, luggage_capacity = :luggage, status = :status
                     WHERE id = :id AND partner_id = :partner_id"
                )->execute([
                    'license_plate' => $licensePlate,
                    'vehicle_type' => $vehicleType,
                    'seats' => $seatsCapacity,
                    'luggage' => $luggageCapacity,
                    'status' => $status,
                    'id' => $vehicleId,
                    'partner_id' => $partner['id'],
                ]);

                $vehicle = [
                    'id' => $vehicleId,
                    'license_plate' => $licensePlate,
                    'vehicle_type' => $vehicleType,
                    'seats_capacity' => $seatsCapacity,
                    'luggage_capacity' => $luggageCapacity,
                    'status' => $status,
                ];
                $success = true;
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $errors[] = 'Já existe uma viatura registada com essa matrícula.';
                } else {
                    throw $e;
                }
            }
        }
    }
}

$pageTitle = 'Editar viatura';
require __DIR__ . '/../views/header.php';
?>
    <h1>Editar viatura</h1>
    <p><a href="/my-vehicles.php">← Voltar às minhas viaturas</a></p>

    <?php if ($success): ?>
        <p class="alert alert-success">Viatura atualizada com sucesso.</p>
    <?php endif; ?>

    <?php foreach ($errors as $error): ?>
        <p class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES) ?></p>
    <?php endforeach; ?>

    <form method="post" action="/vehicle-edit.php?id=<?= (int) $vehicle['id'] ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES) ?>">
        <input type="hidden" name="id" value="<?= (int) $vehicle['id'] ?>">

        <label>Matrícula <input type="text" name="license_plate" value="<?= htmlspecialchars($vehicle['license_plate'], ENT_QUOTES) ?>" required></label>

        <label>Tipo
            <select name="vehicle_type" required>
                <?php foreach ($vehicleTypes as $type): ?>
                    <option value="<?= htmlspecialchars($type, ENT_QUOTES) ?>" <?= $vehicle['vehicle_type'] === $type ? 'selected' : '' ?>><?= htmlspecialchars($type, ENT_QUOTES) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>Lugares <input type="number" name="seats_capacity" min="1" value="<?= (int) $vehicle['seats_capacity'] ?>" required></label>
        <label>Malas <input type="number" name="luggage_capacity" min="0" value="<?= (int) $vehicle['luggage_capacity'] ?>" required></label>

        <label>Estado
            <select name="status" required>
                <?php foreach ($statuses as $statusOption): ?>
                    <option value="<?= htmlspecialchars($statusOption, ENT_QUOTES) ?>" <?= $vehicle['status'] === $statusOption ? 'selected' : '' ?>><?= htmlspecialchars($statusOption, ENT_QUOTES) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <button type="submit">Guardar alterações</button>
    </form>
<?php require __DIR__ . '/../views/footer.php'; ?>
