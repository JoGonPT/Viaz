<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

use App\Auth;
use App\Csrf;
use App\Database;

Auth::requireRole('admin');

$pdo = Database::connection();

$groupId = (int) ($_GET['group_id'] ?? 0);

$groupStmt = $pdo->prepare(
    "SELECT sg.*, c.company_name
     FROM service_groups sg
     INNER JOIN clients c ON c.id = sg.client_id
     WHERE sg.id = :id"
);
$groupStmt->execute(['id' => $groupId]);
$group = $groupStmt->fetch();

if (!$group) {
    http_response_code(404);
    exit('Grupo não encontrado.');
}

$partners = $pdo->query('SELECT id, company_name FROM partners WHERE status = "active" ORDER BY company_name')->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Sessão inválida, tente novamente.';
    } else {
        $allocatedStmt = $pdo->prepare(
            "SELECT COALESCE(SUM(passengers_count), 0) AS passengers, COALESCE(SUM(luggage_count), 0) AS luggage
             FROM trips
             WHERE service_group_id = :group_id AND status != 'cancelled'"
        );
        $allocatedStmt->execute(['group_id' => $groupId]);
        $allocated = $allocatedStmt->fetch();

        $remainingPassengers = (int) $group['total_passengers'] - (int) $allocated['passengers'];
        $remainingLuggage = (int) $group['total_luggage'] - (int) $allocated['luggage'];

        $visibility = $_POST['visibility'] ?? 'public';
        $partnerId = (int) ($_POST['partner_id'] ?? 0);
        $passengersCount = (int) ($_POST['passengers_count'] ?? 0);
        $luggageCount = (int) ($_POST['luggage_count'] ?? 0);
        $scheduledAt = trim($_POST['scheduled_at'] ?? '') ?: $group['scheduled_at'];
        $listedPrice = trim($_POST['listed_price'] ?? '');

        if (!in_array($visibility, ['public', 'private'], true)) {
            $errors[] = 'Visibilidade inválida.';
        }

        if ($visibility === 'private' && $partnerId <= 0) {
            $errors[] = 'Escolhe o parceiro convidado para uma viagem privada.';
        }

        if ($passengersCount <= 0 || $passengersCount > $remainingPassengers) {
            $errors[] = "Passageiros tem de estar entre 1 e {$remainingPassengers} (restante no grupo).";
        }

        if ($luggageCount < 0 || $luggageCount > $remainingLuggage) {
            $errors[] = "Malas tem de estar entre 0 e {$remainingLuggage} (restante no grupo).";
        }

        if ($errors === []) {
            $pdo->prepare(
                "INSERT INTO trips (service_group_id, passengers_count, luggage_count, visibility, invited_partner_id, status, scheduled_at, listed_price)
                 VALUES (:group_id, :passengers, :luggage, :visibility, :partner_id, 'open', :scheduled_at, :listed_price)"
            )->execute([
                'group_id' => $groupId,
                'passengers' => $passengersCount,
                'luggage' => $luggageCount,
                'visibility' => $visibility,
                'partner_id' => $visibility === 'private' ? $partnerId : null,
                'scheduled_at' => $scheduledAt,
                'listed_price' => $listedPrice !== '' ? $listedPrice : null,
            ]);

            $newAllocatedPassengers = (int) $allocated['passengers'] + $passengersCount;
            $newAllocatedLuggage = (int) $allocated['luggage'] + $luggageCount;

            $splitStatus = 'partially_split';
            if ($newAllocatedPassengers === 0) {
                $splitStatus = 'not_split';
            } elseif ($newAllocatedPassengers >= (int) $group['total_passengers'] && $newAllocatedLuggage >= (int) $group['total_luggage']) {
                $splitStatus = 'fully_split';
            }

            $pdo->prepare('UPDATE service_groups SET split_status = :split_status WHERE id = :id')
                ->execute(['split_status' => $splitStatus, 'id' => $groupId]);

            header('Location: /group-trips.php?group_id=' . $groupId);
            exit;
        }
    }
}

$tripsStmt = $pdo->prepare(
    "SELECT t.id, t.passengers_count, t.luggage_count, t.visibility, t.status, t.scheduled_at, t.listed_price,
            ip.company_name AS invited_partner_name, ap.company_name AS assigned_partner_name
     FROM trips t
     LEFT JOIN partners ip ON ip.id = t.invited_partner_id
     LEFT JOIN partners ap ON ap.id = t.assigned_partner_id
     WHERE t.service_group_id = :group_id
     ORDER BY t.id"
);
$tripsStmt->execute(['group_id' => $groupId]);
$trips = $tripsStmt->fetchAll();

$allocatedStmt = $pdo->prepare(
    "SELECT COALESCE(SUM(passengers_count), 0) AS passengers, COALESCE(SUM(luggage_count), 0) AS luggage
     FROM trips
     WHERE service_group_id = :group_id AND status != 'cancelled'"
);
$allocatedStmt->execute(['group_id' => $groupId]);
$allocated = $allocatedStmt->fetch();

$remainingPassengers = (int) $group['total_passengers'] - (int) $allocated['passengers'];
$remainingLuggage = (int) $group['total_luggage'] - (int) $allocated['luggage'];
$pageTitle = 'Grupo #' . (int) $group['id'];
require __DIR__ . '/../views/header.php';
?>
    <h1>Grupo #<?= (int) $group['id'] ?> — <?= htmlspecialchars($group['company_name'], ENT_QUOTES) ?></h1>
    <p><a href="/groups.php">← Voltar aos grupos</a></p>

    <div class="card">
        <p>
            <?= htmlspecialchars($group['origin'], ENT_QUOTES) ?> → <?= htmlspecialchars($group['destination'], ENT_QUOTES) ?><br>
            Data/Hora: <?= htmlspecialchars($group['scheduled_at'], ENT_QUOTES) ?><br>
            Total: <?= (int) $group['total_passengers'] ?> passageiros, <?= (int) $group['total_luggage'] ?> malas<br>
            Já alocado: <?= (int) $allocated['passengers'] ?> passageiros, <?= (int) $allocated['luggage'] ?> malas<br>
            Estado: <span class="badge"><?= htmlspecialchars($group['split_status'], ENT_QUOTES) ?></span>
        </p>
    </div>

    <h2>Viagens deste grupo</h2>
    <?php if ($trips === []): ?>
        <p class="muted">Ainda não há viagens criadas para este grupo.</p>
    <?php else: ?>
        <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Passageiros</th>
                    <th>Malas</th>
                    <th>Visibilidade</th>
                    <th>Convidado/Atribuído</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($trips as $trip): ?>
                    <tr>
                        <td><?= (int) $trip['id'] ?></td>
                        <td><?= (int) $trip['passengers_count'] ?></td>
                        <td><?= (int) $trip['luggage_count'] ?></td>
                        <td><?= htmlspecialchars($trip['visibility'], ENT_QUOTES) ?></td>
                        <td><?= htmlspecialchars($trip['assigned_partner_name'] ?? $trip['invited_partner_name'] ?? '-', ENT_QUOTES) ?></td>
                        <td><span class="badge"><?= htmlspecialchars($trip['status'], ENT_QUOTES) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>

    <?php foreach ($errors as $error): ?>
        <p class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES) ?></p>
    <?php endforeach; ?>

    <?php if ($remainingPassengers <= 0 && $remainingLuggage <= 0): ?>
        <p class="alert alert-success">Grupo totalmente alocado — não é possível adicionar mais viagens.</p>
    <?php else: ?>
        <h2>Adicionar viagem (restam <?= $remainingPassengers ?> passageiros, <?= $remainingLuggage ?> malas)</h2>
        <form method="post" action="/group-trips.php?group_id=<?= (int) $group['id'] ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES) ?>">

            <label>Visibilidade
                <select name="visibility" id="visibility">
                    <option value="public">Pública (mural)</option>
                    <option value="private">Privada (parceiro convidado)</option>
                </select>
            </label>

            <label>Parceiro convidado (só para privada)
                <select name="partner_id">
                    <option value="">—</option>
                    <?php foreach ($partners as $partner): ?>
                        <option value="<?= (int) $partner['id'] ?>"><?= htmlspecialchars($partner['company_name'], ENT_QUOTES) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>Passageiros <input type="number" name="passengers_count" min="1" max="<?= $remainingPassengers ?>" required></label>
            <label>Malas <input type="number" name="luggage_count" min="0" max="<?= $remainingLuggage ?>" value="0" required></label>
            <label>Data/Hora (opcional, herda do grupo se vazio) <input type="datetime-local" name="scheduled_at"></label>
            <label>Preço (opcional) <input type="number" name="listed_price" step="0.01" min="0"></label>

            <button type="submit">Adicionar viagem</button>
        </form>
    <?php endif; ?>
<?php require __DIR__ . '/../views/footer.php'; ?>
