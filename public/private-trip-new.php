<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

use App\Auth;
use App\Csrf;
use App\Database;

Auth::requireRole('admin');

$pdo = Database::connection();

$clients = $pdo->query('SELECT id, company_name FROM clients WHERE status = "active" ORDER BY company_name')->fetchAll();
$partners = $pdo->query('SELECT id, company_name FROM partners WHERE status = "active" ORDER BY company_name')->fetchAll();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Sessão inválida, tente novamente.';
    } else {
        $clientId = (int) ($_POST['client_id'] ?? 0);
        $partnerId = (int) ($_POST['partner_id'] ?? 0);
        $origin = trim($_POST['origin'] ?? '');
        $destination = trim($_POST['destination'] ?? '');
        $scheduledAt = trim($_POST['scheduled_at'] ?? '');
        $passengersCount = (int) ($_POST['passengers_count'] ?? 0);
        $luggageCount = (int) ($_POST['luggage_count'] ?? 0);
        $listedPrice = trim($_POST['listed_price'] ?? '');
        $contactPhone = trim($_POST['contact_phone'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if ($clientId <= 0 || $partnerId <= 0 || $origin === '' || $destination === '' || $scheduledAt === '' || $passengersCount <= 0) {
            $errors[] = 'Preenche todos os campos obrigatórios.';
        }

        if ($errors === []) {
            $pdo->beginTransaction();

            try {
                $pdo->prepare(
                    "INSERT INTO service_groups (client_id, origin, destination, scheduled_at, total_passengers, total_luggage, status)
                     VALUES (:client_id, :origin, :destination, :scheduled_at, :passengers, :luggage, 'confirmed')"
                )->execute([
                    'client_id' => $clientId,
                    'origin' => $origin,
                    'destination' => $destination,
                    'scheduled_at' => $scheduledAt,
                    'passengers' => $passengersCount,
                    'luggage' => $luggageCount,
                ]);
                $groupId = (int) $pdo->lastInsertId();

                $pdo->prepare(
                    "INSERT INTO trips (service_group_id, passengers_count, luggage_count, visibility, invited_partner_id, status, scheduled_at, listed_price, contact_phone, notes)
                     VALUES (:group_id, :passengers, :luggage, 'private', :partner_id, 'open', :scheduled_at, :listed_price, :contact_phone, :notes)"
                )->execute([
                    'group_id' => $groupId,
                    'passengers' => $passengersCount,
                    'luggage' => $luggageCount,
                    'partner_id' => $partnerId,
                    'scheduled_at' => $scheduledAt,
                    'listed_price' => $listedPrice !== '' ? $listedPrice : null,
                    'contact_phone' => $contactPhone !== '' ? $contactPhone : null,
                    'notes' => $notes !== '' ? $notes : null,
                ]);

                $pdo->commit();
                $success = true;
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        }
    }
}
$pageTitle = 'Novo envio privado';
require __DIR__ . '/../views/header.php';
?>
    <h1>Criar envio privado</h1>

    <?php if ($success): ?>
        <p class="alert alert-success">Envio privado criado e atribuído ao parceiro com sucesso.</p>
    <?php endif; ?>

    <?php foreach ($errors as $error): ?>
        <p class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES) ?></p>
    <?php endforeach; ?>

    <?php if ($clients === [] || $partners === []): ?>
        <p class="muted">É preciso ter pelo menos um cliente e um parceiro ativos antes de criar um envio privado.</p>
    <?php else: ?>
        <form method="post" action="/private-trip-new.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES) ?>">

            <label>Cliente
                <select name="client_id" required>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?= (int) $client['id'] ?>"><?= htmlspecialchars($client['company_name'], ENT_QUOTES) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>Parceiro convidado
                <select name="partner_id" required>
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
            <label>Contacto do cliente (telefone) <input type="tel" name="contact_phone"></label>
            <label>Observações (ex: cadeirinhas, mobilidade reduzida) <input type="text" name="notes"></label>

            <button type="submit">Criar envio privado</button>
        </form>
    <?php endif; ?>
<?php require __DIR__ . '/../views/footer.php'; ?>
