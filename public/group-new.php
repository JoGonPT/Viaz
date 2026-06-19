<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

use App\Auth;
use App\Csrf;
use App\Database;

Auth::requireRole('admin', 'partner');

$pdo = Database::connection();

$clients = $pdo->query('SELECT id, company_name FROM clients WHERE status = "active" ORDER BY company_name')->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Sessão inválida, tente novamente.';
    } else {
        $clientId = (int) ($_POST['client_id'] ?? 0);
        $origin = trim($_POST['origin'] ?? '');
        $destination = trim($_POST['destination'] ?? '');
        $scheduledAt = trim($_POST['scheduled_at'] ?? '');
        $totalPassengers = (int) ($_POST['total_passengers'] ?? 0);
        $totalLuggage = (int) ($_POST['total_luggage'] ?? 0);

        if ($clientId <= 0 || $origin === '' || $destination === '' || $scheduledAt === '' || $totalPassengers <= 0) {
            $errors[] = 'Preenche todos os campos obrigatórios.';
        }

        if ($errors === []) {
            $pdo->prepare(
                "INSERT INTO service_groups (client_id, origin, destination, scheduled_at, total_passengers, total_luggage, status)
                 VALUES (:client_id, :origin, :destination, :scheduled_at, :passengers, :luggage, 'confirmed')"
            )->execute([
                'client_id' => $clientId,
                'origin' => $origin,
                'destination' => $destination,
                'scheduled_at' => $scheduledAt,
                'passengers' => $totalPassengers,
                'luggage' => $totalLuggage,
            ]);
            $groupId = (int) $pdo->lastInsertId();

            header('Location: /group-trips.php?group_id=' . $groupId);
            exit;
        }
    }
}
$pageTitle = 'Novo grupo de serviço';
require __DIR__ . '/../views/header.php';
?>
    <h1>Criar grupo de serviço</h1>
    <p><a href="/groups.php">← Voltar aos grupos</a></p>

    <?php foreach ($errors as $error): ?>
        <p class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES) ?></p>
    <?php endforeach; ?>

    <?php if ($clients === []): ?>
        <p class="muted">É preciso ter pelo menos um cliente ativo antes de criar um grupo.</p>
    <?php else: ?>
        <form method="post" action="/group-new.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES) ?>">

            <label>Cliente
                <select name="client_id" required>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?= (int) $client['id'] ?>"><?= htmlspecialchars($client['company_name'], ENT_QUOTES) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>Origem <input type="text" name="origin" required></label>
            <label>Destino <input type="text" name="destination" required></label>
            <label>Data/Hora <input type="datetime-local" name="scheduled_at" required></label>
            <label>Total de passageiros <input type="number" name="total_passengers" min="1" required></label>
            <label>Total de malas <input type="number" name="total_luggage" min="0" value="0" required></label>

            <button type="submit">Criar grupo</button>
        </form>
    <?php endif; ?>
<?php require __DIR__ . '/../views/footer.php'; ?>
