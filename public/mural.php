<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

use App\Auth;
use App\Csrf;
use App\Database;
use App\TripPresenter;

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
    $pageTitle = 'Mural';
    $activeTab = 'mural';
    require __DIR__ . '/../views/header-app.php';
    echo '<p class="rounded-2xl bg-amber-100 text-amber-800 text-sm p-4">A tua conta de parceiro está pendente de aprovação por um administrador. Vais poder usar o mural depois de seres aprovado.</p>';
    require __DIR__ . '/../views/footer-app.php';
    exit;
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
        "SELECT t.id, t.passengers_count, t.luggage_count, t.scheduled_at, t.listed_price, t.status,
                t.contact_phone, t.notes,
                sg.origin, sg.destination, u.full_name AS created_by_name, u.phone AS created_by_phone
         FROM trips t
         INNER JOIN service_groups sg ON sg.id = t.service_group_id
         LEFT JOIN users u ON u.id = sg.created_by_user_id
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
        "SELECT t.id, t.passengers_count, t.luggage_count, t.scheduled_at, t.listed_price, t.status,
                t.contact_phone, t.notes,
                sg.origin, sg.destination, u.full_name AS created_by_name, u.phone AS created_by_phone
         FROM trips t
         INNER JOIN service_groups sg ON sg.id = t.service_group_id
         LEFT JOIN users u ON u.id = sg.created_by_user_id
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

function render_trip_card(array $trip, int $vehicleId, string $prefix): void
{
    $templateId = $prefix . '-' . $trip['id'];
    $time = (new DateTime($trip['scheduled_at']))->format('d/m H:i');
    $price = $trip['listed_price'] !== null ? number_format((float) $trip['listed_price'], 2) . ' €' : 'Preço a combinar';
    $creator = $trip['created_by_name'] ?? null;
    ?>
    <button type="button"
            class="w-full text-left bg-white rounded-2xl shadow-sm border border-slate-200 p-3 flex items-center gap-3 active:scale-[0.99] transition mb-3"
            data-open-drawer="<?= htmlspecialchars($templateId, ENT_QUOTES) ?>">
        <span class="h-10 w-10 rounded-full flex items-center justify-center text-base shrink-0 <?= TripPresenter::iconBgClasses($trip['status']) ?>">
            <?= TripPresenter::icon($trip['status']) ?>
        </span>
        <span class="flex-1 min-w-0">
            <span class="block text-sm font-medium text-slate-800 truncate"><?= htmlspecialchars($trip['origin'], ENT_QUOTES) ?> → <?= htmlspecialchars($trip['destination'], ENT_QUOTES) ?></span>
            <span class="block text-xs text-slate-500"><?= $time ?> · <?= (int) $trip['passengers_count'] ?> pax · <?= (int) $trip['luggage_count'] ?> malas</span>
        </span>
        <span class="text-[11px] font-semibold px-2 py-1 rounded-full shrink-0 <?= TripPresenter::badgeClasses($trip['status']) ?>">
            <?= TripPresenter::label($trip['status']) ?>
        </span>
    </button>

    <template id="<?= htmlspecialchars($templateId, ENT_QUOTES) ?>-content">
        <div class="space-y-4">
            <div class="flex items-start justify-between gap-2">
                <h3 class="text-lg font-semibold text-slate-800"><?= htmlspecialchars($trip['origin'], ENT_QUOTES) ?> → <?= htmlspecialchars($trip['destination'], ENT_QUOTES) ?></h3>
                <span class="text-[11px] font-semibold px-2 py-1 rounded-full shrink-0 <?= TripPresenter::badgeClasses($trip['status']) ?>"><?= TripPresenter::label($trip['status']) ?></span>
            </div>
            <p class="text-sm text-slate-500"><?= $time ?></p>

            <dl class="grid grid-cols-2 gap-3 text-sm bg-slate-50 rounded-xl p-3">
                <div><dt class="text-slate-400 text-xs">Passageiros</dt><dd class="font-medium"><?= (int) $trip['passengers_count'] ?></dd></div>
                <div><dt class="text-slate-400 text-xs">Malas</dt><dd class="font-medium"><?= (int) $trip['luggage_count'] ?></dd></div>
                <div><dt class="text-slate-400 text-xs">Preço</dt><dd class="font-medium"><?= htmlspecialchars($price, ENT_QUOTES) ?></dd></div>
                <div><dt class="text-slate-400 text-xs">Criado por</dt><dd class="font-medium"><?= htmlspecialchars($creator ?? '—', ENT_QUOTES) ?></dd></div>
                <?php if (!empty($trip['contact_phone'])): ?>
                    <div><dt class="text-slate-400 text-xs">Contacto do cliente</dt><dd class="font-medium"><?= htmlspecialchars($trip['contact_phone'], ENT_QUOTES) ?></dd></div>
                <?php endif; ?>
            </dl>

            <?php if (!empty($trip['notes'])): ?>
                <p class="text-sm bg-amber-50 text-amber-800 rounded-xl p-3">📝 <?= htmlspecialchars($trip['notes'], ENT_QUOTES) ?></p>
            <?php endif; ?>

            <div class="flex flex-col gap-2">
                <form method="post" action="/accept-trip.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Csrf::token(), ENT_QUOTES) ?>">
                    <input type="hidden" name="trip_id" value="<?= (int) $trip['id'] ?>">
                    <input type="hidden" name="vehicle_id" value="<?= $vehicleId ?>">
                    <button type="submit" class="w-full rounded-xl bg-emerald-600 text-white font-medium py-3 active:bg-emerald-700 transition">Aceitar viagem</button>
                </form>

                <?php if (!empty($trip['created_by_phone'])): ?>
                    <a href="/chat.php?phone=<?= urlencode($trip['created_by_phone']) ?>&name=<?= urlencode($creator ?? '') ?>"
                       class="w-full text-center rounded-xl border border-emerald-200 text-emerald-700 font-medium py-3 active:bg-emerald-50 transition">
                        💬 Falar no WhatsApp
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </template>
    <?php
}

$pageTitle = 'Mural';
$activeTab = 'mural';
require __DIR__ . '/../views/header-app.php';
?>
    <?php if (($_GET['outcome'] ?? null) === 'accepted'): ?>
        <p class="rounded-2xl bg-emerald-100 text-emerald-800 text-sm p-3 mb-3">Viagem aceite com sucesso.</p>
    <?php elseif (($_GET['outcome'] ?? null) === 'accepted_warning'): ?>
        <p class="rounded-2xl bg-amber-100 text-amber-800 text-sm p-3 mb-3">Viagem aceite, mas atenção: esta viatura tem outra viagem a menos de 2 horas de distância.</p>
    <?php elseif (($_GET['outcome'] ?? null) === 'schedule_conflict'): ?>
        <p class="rounded-2xl bg-red-100 text-red-800 text-sm p-3 mb-3">Não foi possível aceitar: esta viatura já tem outra viagem exatamente à mesma hora.</p>
    <?php elseif (($_GET['outcome'] ?? null) === 'conflict'): ?>
        <p class="rounded-2xl bg-red-100 text-red-800 text-sm p-3 mb-3">Essa viagem já tinha sido aceite por outro parceiro entretanto.</p>
    <?php elseif (($_GET['outcome'] ?? null) === 'not_found'): ?>
        <p class="rounded-2xl bg-red-100 text-red-800 text-sm p-3 mb-3">Essa viagem já não está disponível.</p>
    <?php endif; ?>

    <?php if ($vehicles === []): ?>
        <p class="text-sm text-slate-500">Não tens viaturas ativas registadas.</p>
    <?php else: ?>
        <form method="get" action="/mural.php" class="mb-4">
            <label class="block text-xs font-medium text-slate-500 mb-1">Viatura</label>
            <select name="vehicle_id" onchange="this.form.submit()"
                    class="w-full rounded-xl border border-slate-200 bg-white py-2.5 px-3 text-sm">
                <?php foreach ($vehicles as $vehicle): ?>
                    <option value="<?= (int) $vehicle['id'] ?>" <?= $selectedVehicle && (int) $selectedVehicle['id'] === (int) $vehicle['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($vehicle['license_plate'], ENT_QUOTES) ?>
                        (<?= (int) $vehicle['seats_capacity'] ?> lugares, <?= (int) $vehicle['luggage_capacity'] ?> malas)
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-2">Convites privados</h2>
        <?php if ($privateInvites === []): ?>
            <p class="text-sm text-slate-500 mb-4">Não tens convites privados em aberto para esta viatura.</p>
        <?php else: ?>
            <?php foreach ($privateInvites as $trip): ?>
                <?php render_trip_card($trip, (int) $selectedVehicle['id'], 'private'); ?>
            <?php endforeach; ?>
        <?php endif; ?>

        <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-2 mt-4">Viagens públicas</h2>
        <?php if ($trips === []): ?>
            <p class="text-sm text-slate-500">Não há viagens elegíveis para esta viatura neste momento.</p>
        <?php else: ?>
            <?php foreach ($trips as $trip): ?>
                <?php render_trip_card($trip, (int) $selectedVehicle['id'], 'public'); ?>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>
<?php require __DIR__ . '/../views/footer-app.php'; ?>
