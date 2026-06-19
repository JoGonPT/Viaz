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

$vehicleTypes = ['sedan', 'minivan', 'van', 'minibus', 'bus'];
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

        if ($licensePlate === '') {
            $errors[] = 'A matrícula é obrigatória.';
        }

        if (!in_array($vehicleType, $vehicleTypes, true)) {
            $errors[] = 'Tipo de viatura inválido.';
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
                    "INSERT INTO vehicles (partner_id, license_plate, vehicle_type, seats_capacity, luggage_capacity, status)
                     VALUES (:partner_id, :license_plate, :vehicle_type, :seats, :luggage, 'active')"
                )->execute([
                    'partner_id' => $partner['id'],
                    'license_plate' => $licensePlate,
                    'vehicle_type' => $vehicleType,
                    'seats' => $seatsCapacity,
                    'luggage' => $luggageCapacity,
                ]);
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

$vehiclesStmt = $pdo->prepare(
    'SELECT id, license_plate, vehicle_type, seats_capacity, luggage_capacity, status
     FROM vehicles
     WHERE partner_id = :partner_id
     ORDER BY license_plate'
);
$vehiclesStmt->execute(['partner_id' => $partner['id']]);
$vehicles = $vehiclesStmt->fetchAll();

$pageTitle = 'As minhas viaturas';
require __DIR__ . '/../views/header.php';
?>
    <h1>As minhas viaturas</h1>

    <?php if ($success): ?>
        <p class="alert alert-success">Viatura adicionada com sucesso.</p>
    <?php endif; ?>

    <?php foreach ($errors as $error): ?>
        <p class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES) ?></p>
    <?php endforeach; ?>

    <?php if ($vehicles === []): ?>
        <p class="muted">Ainda não tens viaturas registadas.</p>
    <?php else: ?>
        <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>Matrícula</th>
                    <th>Tipo</th>
                    <th>Lugares</th>
                    <th>Malas</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vehicles as $vehicle): ?>
                    <tr>
                        <td><?= htmlspecialchars($vehicle['license_plate'], ENT_QUOTES) ?></td>
                        <td><?= htmlspecialchars($vehicle['vehicle_type'], ENT_QUOTES) ?></td>
                        <td><?= (int) $vehicle['seats_capacity'] ?></td>
                        <td><?= (int) $vehicle['luggage_capacity'] ?></td>
                        <td><span class="badge"><?= htmlspecialchars($vehicle['status'], ENT_QUOTES) ?></span></td>
                        <td><a href="/vehicle-edit.php?id=<?= (int) $vehicle['id'] ?>">Editar</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>

    <h2>Adicionar viatura</h2>
    <form method="post" action="/my-vehicles.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES) ?>">

        <label>Matrícula <input type="text" name="license_plate" required></label>

        <label>Tipo
            <select name="vehicle_type" required>
                <?php foreach ($vehicleTypes as $type): ?>
                    <option value="<?= htmlspecialchars($type, ENT_QUOTES) ?>"><?= htmlspecialchars($type, ENT_QUOTES) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>Lugares <input type="number" name="seats_capacity" min="1" required></label>
        <label>Malas <input type="number" name="luggage_capacity" min="0" value="0" required></label>

        <button type="submit">Adicionar viatura</button>
    </form>
<?php require __DIR__ . '/../views/footer.php'; ?>
