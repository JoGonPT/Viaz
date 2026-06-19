<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

use App\Auth;
use App\Csrf;
use App\Database;

Auth::requireRole('client');

$pdo = Database::connection();

$clientStmt = $pdo->prepare('SELECT id FROM clients WHERE user_id = :user_id');
$clientStmt->execute(['user_id' => $_SESSION['user_id']]);
$client = $clientStmt->fetch();

if (!$client) {
    http_response_code(403);
    exit('Conta de cliente não encontrada.');
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Sessão inválida, tente novamente.';
    } else {
        $origin = trim($_POST['origin'] ?? '');
        $destination = trim($_POST['destination'] ?? '');
        $scheduledAt = trim($_POST['scheduled_at'] ?? '');
        $totalPassengers = (int) ($_POST['total_passengers'] ?? 0);
        $totalLuggage = (int) ($_POST['total_luggage'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');

        if ($origin === '' || $destination === '' || $scheduledAt === '' || $totalPassengers <= 0) {
            $errors[] = 'Preenche todos os campos obrigatórios.';
        }

        if ($totalLuggage < 0) {
            $errors[] = 'O número de malas não pode ser negativo.';
        }

        if ($errors === []) {
            $pdo->prepare(
                "INSERT INTO service_groups (client_id, origin, destination, scheduled_at, total_passengers, total_luggage, status, notes)
                 VALUES (:client_id, :origin, :destination, :scheduled_at, :passengers, :luggage, 'draft', :notes)"
            )->execute([
                'client_id' => $client['id'],
                'origin' => $origin,
                'destination' => $destination,
                'scheduled_at' => $scheduledAt,
                'passengers' => $totalPassengers,
                'luggage' => $totalLuggage,
                'notes' => $notes !== '' ? $notes : null,
            ]);

            $success = true;
        }
    }
}

$requestsStmt = $pdo->prepare(
    'SELECT id, origin, destination, scheduled_at, total_passengers, total_luggage, status, split_status
     FROM service_groups
     WHERE client_id = :client_id
     ORDER BY scheduled_at DESC'
);
$requestsStmt->execute(['client_id' => $client['id']]);
$requests = $requestsStmt->fetchAll();

$pageTitle = 'Os meus pedidos';
require __DIR__ . '/../views/header.php';
?>
    <h1>Os meus pedidos de transporte</h1>

    <?php if ($success): ?>
        <p class="alert alert-success">Pedido enviado com sucesso. A nossa equipa vai confirmá-lo e atribuir os parceiros necessários.</p>
    <?php endif; ?>

    <?php foreach ($errors as $error): ?>
        <p class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES) ?></p>
    <?php endforeach; ?>

    <?php if ($requests === []): ?>
        <p class="muted">Ainda não fizeste nenhum pedido.</p>
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
                    <th>Estado do pedido</th>
                    <th>Atribuição</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($requests as $request): ?>
                    <tr>
                        <td><?= htmlspecialchars($request['origin'], ENT_QUOTES) ?></td>
                        <td><?= htmlspecialchars($request['destination'], ENT_QUOTES) ?></td>
                        <td><?= htmlspecialchars($request['scheduled_at'], ENT_QUOTES) ?></td>
                        <td><?= (int) $request['total_passengers'] ?></td>
                        <td><?= (int) $request['total_luggage'] ?></td>
                        <td><span class="badge"><?= htmlspecialchars($request['status'], ENT_QUOTES) ?></span></td>
                        <td><span class="badge"><?= htmlspecialchars($request['split_status'], ENT_QUOTES) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>

    <h2>Novo pedido</h2>
    <form method="post" action="/my-requests.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES) ?>">

        <label>Origem <input type="text" name="origin" required></label>
        <label>Destino <input type="text" name="destination" required></label>
        <label>Data/Hora <input type="datetime-local" name="scheduled_at" required></label>
        <label>Total de passageiros <input type="number" name="total_passengers" min="1" required></label>
        <label>Total de malas <input type="number" name="total_luggage" min="0" value="0" required></label>
        <label>Notas (opcional) <input type="text" name="notes"></label>

        <button type="submit">Enviar pedido</button>
    </form>
<?php require __DIR__ . '/../views/footer.php'; ?>
