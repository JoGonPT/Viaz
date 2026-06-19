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
$ownPartner = $partnerStmt->fetch();

if (!$ownPartner) {
    http_response_code(403);
    exit('Conta de parceiro não encontrada.');
}

if ($ownPartner['status'] !== 'active') {
    $pageTitle = 'Criar viagem';
    require __DIR__ . '/../views/header.php';
    echo '<p class="alert alert-warning">A tua conta de parceiro está pendente de aprovação por um administrador.</p>';
    require __DIR__ . '/../views/footer.php';
    exit;
}

$clients = $pdo->query('SELECT id, company_name FROM clients WHERE status = "active" ORDER BY company_name')->fetchAll();
$partners = $pdo->query('SELECT id, company_name FROM partners WHERE status = "active" ORDER BY company_name')->fetchAll();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Sessão inválida, tente novamente.';
    } else {
        $tipo = $_POST['tipo'] ?? '';
        $clientId = (int) ($_POST['client_id'] ?? 0);
        $invitedPartnerId = (int) ($_POST['partner_id'] ?? 0);
        $origin = trim($_POST['origin'] ?? '');
        $destination = trim($_POST['destination'] ?? '');
        $scheduledAt = trim($_POST['scheduled_at'] ?? '');
        $passengersCount = (int) ($_POST['passengers_count'] ?? 0);
        $luggageCount = (int) ($_POST['luggage_count'] ?? 0);
        $listedPrice = trim($_POST['listed_price'] ?? '');

        if (!in_array($tipo, ['grupo', 'privado'], true)) {
            $errors[] = 'Tipo inválido.';
        }

        if ($clientId <= 0 || $origin === '' || $destination === '' || $scheduledAt === '' || $passengersCount <= 0) {
            $errors[] = 'Preenche todos os campos obrigatórios.';
        }

        if ($tipo === 'privado' && $invitedPartnerId <= 0) {
            $errors[] = 'Escolhe o parceiro convidado para uma viagem privada.';
        }

        if ($errors === []) {
            $visibility = $tipo === 'grupo' ? 'public' : 'private';

            $pdo->beginTransaction();

            try {
                $pdo->prepare(
                    "INSERT INTO service_groups (client_id, origin, destination, scheduled_at, total_passengers, total_luggage, status, created_by_user_id)
                     VALUES (:client_id, :origin, :destination, :scheduled_at, :passengers, :luggage, 'confirmed', :created_by)"
                )->execute([
                    'client_id' => $clientId,
                    'origin' => $origin,
                    'destination' => $destination,
                    'scheduled_at' => $scheduledAt,
                    'passengers' => $passengersCount,
                    'luggage' => $luggageCount,
                    'created_by' => $_SESSION['user_id'],
                ]);
                $groupId = (int) $pdo->lastInsertId();

                $pdo->prepare(
                    "INSERT INTO trips (service_group_id, passengers_count, luggage_count, visibility, invited_partner_id, status, scheduled_at, listed_price)
                     VALUES (:group_id, :passengers, :luggage, :visibility, :partner_id, 'open', :scheduled_at, :listed_price)"
                )->execute([
                    'group_id' => $groupId,
                    'passengers' => $passengersCount,
                    'luggage' => $luggageCount,
                    'visibility' => $visibility,
                    'partner_id' => $visibility === 'private' ? $invitedPartnerId : null,
                    'scheduled_at' => $scheduledAt,
                    'listed_price' => $listedPrice !== '' ? $listedPrice : null,
                ]);

                $pdo->prepare("UPDATE service_groups SET split_status = 'fully_split' WHERE id = :id")
                    ->execute(['id' => $groupId]);

                $pdo->commit();
                $success = true;
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        }
    }
}

$pageTitle = 'Criar viagem';
require __DIR__ . '/../views/header.php';
?>
    <h1>Criar viagem</h1>

    <?php if ($success): ?>
        <p class="alert alert-success">Viagem criada com sucesso.</p>
    <?php endif; ?>

    <?php foreach ($errors as $error): ?>
        <p class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES) ?></p>
    <?php endforeach; ?>

    <?php if ($clients === []): ?>
        <p class="muted">É preciso ter pelo menos um cliente ativo antes de criar uma viagem.</p>
    <?php else: ?>
        <form method="post" action="/new-trip.php" id="new-trip-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES) ?>">

            <label>Tipo
                <select name="tipo" id="tipo">
                    <option value="grupo">Grupo (publica no mural)</option>
                    <option value="privado">Privado (escolhes o parceiro)</option>
                </select>
            </label>

            <label>Cliente
                <select name="client_id" required>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?= (int) $client['id'] ?>"><?= htmlspecialchars($client['company_name'], ENT_QUOTES) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label id="partner-field">Parceiro convidado (só para privada)
                <select name="partner_id">
                    <option value="">—</option>
                    <?php foreach ($partners as $partner): ?>
                        <option value="<?= (int) $partner['id'] ?>"><?= htmlspecialchars($partner['company_name'], ENT_QUOTES) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>Origem <input type="text" name="origin" required></label>
            <label>Destino <input type="text" name="destination" required></label>
            <label>Data/Hora <input type="datetime-local" name="scheduled_at" required></label>
            <label>Passageiros <input type="number" name="passengers_count" min="1" required></label>
            <label>Malas <input type="number" name="luggage_count" min="0" value="0" required></label>
            <label>Preço (opcional) <input type="number" name="listed_price" step="0.01" min="0"></label>

            <button type="submit">Criar viagem</button>
        </form>
    <?php endif; ?>
<?php require __DIR__ . '/../views/footer.php'; ?>
