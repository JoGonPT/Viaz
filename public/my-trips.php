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
    $pageTitle = 'As minhas viagens';
    $activeTab = 'trips';
    require __DIR__ . '/../views/header-app.php';
    echo '<p class="rounded-2xl bg-amber-100 text-amber-800 text-sm p-4">A tua conta de parceiro está pendente de aprovação por um administrador.</p>';
    require __DIR__ . '/../views/footer-app.php';
    exit;
}

$tripsStmt = $pdo->prepare(
    "SELECT t.id, t.service_group_id, t.passengers_count, t.luggage_count, t.scheduled_at, t.listed_price, t.status,
            sg.origin, sg.destination, sg.total_passengers,
            v.license_plate, v.vehicle_type, v.seats_capacity,
            u.full_name AS created_by_name, u.phone AS created_by_phone
     FROM trips t
     INNER JOIN service_groups sg ON sg.id = t.service_group_id
     LEFT JOIN vehicles v ON v.id = t.assigned_vehicle_id
     LEFT JOIN users u ON u.id = sg.created_by_user_id
     WHERE t.assigned_partner_id = :partner_id
     ORDER BY t.scheduled_at DESC"
);
$tripsStmt->execute(['partner_id' => $partner['id']]);
$trips = $tripsStmt->fetchAll();

$outcomeMessages = [
    'updated' => ['class' => 'bg-emerald-100 text-emerald-800', 'text' => 'Estado da viagem atualizado.'],
    'cancelled' => ['class' => 'bg-emerald-100 text-emerald-800', 'text' => 'Viagem cancelada e reaberta no mural.'],
    'conflict' => ['class' => 'bg-red-100 text-red-800', 'text' => 'Não foi possível atualizar — o estado já tinha mudado entretanto.'],
    'not_found' => ['class' => 'bg-red-100 text-red-800', 'text' => 'Viagem não encontrada.'],
    'invalid_transition' => ['class' => 'bg-red-100 text-red-800', 'text' => 'Essa ação não é válida para o estado atual da viagem.'],
];
$outcome = $outcomeMessages[$_GET['outcome'] ?? ''] ?? null;

function render_my_trip_card(array $trip): void
{
    $templateId = 'mytrip-' . $trip['id'];
    $time = (new DateTime($trip['scheduled_at']))->format('d/m H:i');
    $price = $trip['listed_price'] !== null ? number_format((float) $trip['listed_price'], 2) . ' €' : 'Preço a combinar';
    $vehicleLabel = $trip['license_plate']
        ? htmlspecialchars($trip['vehicle_type'], ENT_QUOTES) . ' · ' . (int) $trip['seats_capacity'] . ' lug. (' . htmlspecialchars($trip['license_plate'], ENT_QUOTES) . ')'
        : 'Sem viatura atribuída';
    ?>
    <button type="button"
            class="w-full text-left bg-white rounded-2xl shadow-sm border border-slate-200 p-3 flex items-center gap-3 active:scale-[0.99] transition mb-3"
            data-open-drawer="<?= htmlspecialchars($templateId, ENT_QUOTES) ?>">
        <span class="h-10 w-10 rounded-full flex items-center justify-center text-base shrink-0 <?= TripPresenter::iconBgClasses($trip['status']) ?>">
            <?= TripPresenter::icon($trip['status']) ?>
        </span>
        <span class="flex-1 min-w-0">
            <span class="block text-sm font-medium text-slate-800 truncate"><?= htmlspecialchars($trip['origin'], ENT_QUOTES) ?> → <?= htmlspecialchars($trip['destination'], ENT_QUOTES) ?></span>
            <span class="block text-xs text-slate-500"><?= $time ?> · <?= htmlspecialchars($vehicleLabel, ENT_QUOTES) ?></span>
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
            <p class="text-sm text-slate-500"><?= $time ?> · <?= htmlspecialchars($vehicleLabel, ENT_QUOTES) ?></p>

            <dl class="grid grid-cols-2 gap-3 text-sm bg-slate-50 rounded-xl p-3">
                <div><dt class="text-slate-400 text-xs">Passageiros</dt><dd class="font-medium"><?= (int) $trip['passengers_count'] ?></dd></div>
                <div><dt class="text-slate-400 text-xs">Malas</dt><dd class="font-medium"><?= (int) $trip['luggage_count'] ?></dd></div>
                <div><dt class="text-slate-400 text-xs">Preço</dt><dd class="font-medium"><?= htmlspecialchars($price, ENT_QUOTES) ?></dd></div>
                <div><dt class="text-slate-400 text-xs">Criado por</dt><dd class="font-medium"><?= htmlspecialchars($trip['created_by_name'] ?? '—', ENT_QUOTES) ?></dd></div>
            </dl>

            <?php if ((int) $trip['total_passengers'] > (int) $trip['passengers_count']): ?>
                <a href="/group-trips.php?group_id=<?= (int) $trip['service_group_id'] ?>"
                   class="block w-full text-center rounded-xl border border-slate-200 text-slate-600 font-medium py-3 active:bg-slate-50 transition">
                    Repassar parte do grupo
                </a>
            <?php endif; ?>

            <div class="flex flex-col gap-2">
                <?php if ($trip['status'] === 'assigned'): ?>
                    <form method="post" action="/update-trip-status.php">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Csrf::token(), ENT_QUOTES) ?>">
                        <input type="hidden" name="trip_id" value="<?= (int) $trip['id'] ?>">
                        <input type="hidden" name="action" value="start">
                        <button type="submit" class="w-full rounded-xl bg-sky-600 text-white font-medium py-3 active:bg-sky-700 transition">Iniciar viagem</button>
                    </form>
                <?php endif; ?>

                <?php if ($trip['status'] === 'in_progress'): ?>
                    <form method="post" action="/update-trip-status.php">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Csrf::token(), ENT_QUOTES) ?>">
                        <input type="hidden" name="trip_id" value="<?= (int) $trip['id'] ?>">
                        <input type="hidden" name="action" value="complete">
                        <button type="submit" class="w-full rounded-xl bg-emerald-600 text-white font-medium py-3 active:bg-emerald-700 transition">Concluir viagem</button>
                    </form>
                <?php endif; ?>

                <?php if (in_array($trip['status'], ['assigned', 'in_progress'], true)): ?>
                    <form method="post" action="/update-trip-status.php" class="flex gap-2">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Csrf::token(), ENT_QUOTES) ?>">
                        <input type="hidden" name="trip_id" value="<?= (int) $trip['id'] ?>">
                        <input type="hidden" name="action" value="cancel">
                        <input type="text" name="reason" placeholder="Motivo (opcional)" class="flex-1 rounded-xl border border-slate-200 px-3 text-sm">
                        <button type="submit" class="rounded-xl bg-red-600 text-white font-medium px-4 active:bg-red-700 transition">Cancelar</button>
                    </form>
                <?php endif; ?>

                <?php if (!empty($trip['created_by_phone'])): ?>
                    <a href="/chat.php?phone=<?= urlencode($trip['created_by_phone']) ?>&name=<?= urlencode($trip['created_by_name'] ?? '') ?>"
                       class="w-full text-center rounded-xl border border-emerald-200 text-emerald-700 font-medium py-3 active:bg-emerald-50 transition">
                        💬 Falar no WhatsApp
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </template>
    <?php
}

$pageTitle = 'As minhas viagens';
$activeTab = 'trips';
require __DIR__ . '/../views/header-app.php';
?>
    <?php if ($outcome !== null): ?>
        <p class="rounded-2xl text-sm p-3 mb-3 <?= $outcome['class'] ?>"><?= htmlspecialchars($outcome['text'], ENT_QUOTES) ?></p>
    <?php endif; ?>

    <?php if ($trips === []): ?>
        <p class="text-sm text-slate-500">Ainda não aceitaste nenhuma viagem.</p>
    <?php else: ?>
        <?php foreach ($trips as $trip): ?>
            <?php render_my_trip_card($trip); ?>
        <?php endforeach; ?>
    <?php endif; ?>
<?php require __DIR__ . '/../views/footer-app.php'; ?>
