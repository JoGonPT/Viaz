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
    $pageTitle = 'Calendário';
    $activeTab = 'calendar';
    require __DIR__ . '/../views/header-app.php';
    echo '<p class="rounded-2xl bg-amber-100 text-amber-800 text-sm p-4">A tua conta de parceiro está pendente de aprovação por um administrador.</p>';
    require __DIR__ . '/../views/footer-app.php';
    exit;
}

$today = new DateTime('today');
$year = (int) ($_GET['year'] ?? $today->format('Y'));
$month = (int) ($_GET['month'] ?? $today->format('n'));

if ($month < 1) {
    $month = 12;
    $year--;
} elseif ($month > 12) {
    $month = 1;
    $year++;
}

$firstOfMonth = new DateTime(sprintf('%04d-%02d-01', $year, $month));
$daysInMonth = (int) $firstOfMonth->format('t');
$startWeekday = (int) $firstOfMonth->format('N');
$selectedDate = $_GET['date'] ?? $today->format('Y-m-d');

$rangeStart = $firstOfMonth->format('Y-m-d 00:00:00');
$rangeEnd = (clone $firstOfMonth)->modify('first day of next month')->format('Y-m-d 00:00:00');

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
       AND t.scheduled_at >= :range_start AND t.scheduled_at < :range_end
     ORDER BY t.scheduled_at ASC"
);
$tripsStmt->execute(['partner_id' => $partner['id'], 'range_start' => $rangeStart, 'range_end' => $rangeEnd]);
$monthTrips = $tripsStmt->fetchAll();

$tripsByDate = [];
foreach ($monthTrips as $trip) {
    $day = substr($trip['scheduled_at'], 0, 10);
    $tripsByDate[$day][] = $trip;
}

$selectedTrips = $tripsByDate[$selectedDate] ?? [];

$weekdayLabels = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];
$monthLabel = $firstOfMonth->format('F Y');

function render_calendar_trip_card(array $trip): void
{
    $templateId = 'caltrip-' . $trip['id'];
    $time = (new DateTime($trip['scheduled_at']))->format('H:i');
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
            <span class="block text-sm font-medium text-slate-800 truncate"><?= $time ?> · <?= htmlspecialchars($trip['origin'], ENT_QUOTES) ?> → <?= htmlspecialchars($trip['destination'], ENT_QUOTES) ?></span>
            <span class="block text-xs text-slate-500"><?= htmlspecialchars($vehicleLabel, ENT_QUOTES) ?></span>
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
            <p class="text-sm text-slate-500"><?= (new DateTime($trip['scheduled_at']))->format('d/m/Y H:i') ?> · <?= htmlspecialchars($vehicleLabel, ENT_QUOTES) ?></p>

            <dl class="grid grid-cols-2 gap-3 text-sm bg-slate-50 rounded-xl p-3">
                <div><dt class="text-slate-400 text-xs">Passageiros</dt><dd class="font-medium"><?= (int) $trip['passengers_count'] ?></dd></div>
                <div><dt class="text-slate-400 text-xs">Malas</dt><dd class="font-medium"><?= (int) $trip['luggage_count'] ?></dd></div>
                <div><dt class="text-slate-400 text-xs">Preço</dt><dd class="font-medium"><?= htmlspecialchars($price, ENT_QUOTES) ?></dd></div>
                <div><dt class="text-slate-400 text-xs">Criado por</dt><dd class="font-medium"><?= htmlspecialchars($trip['created_by_name'] ?? '—', ENT_QUOTES) ?></dd></div>
            </dl>

            <a href="/my-trips.php" class="block w-full text-center rounded-xl border border-slate-200 text-slate-600 font-medium py-3 active:bg-slate-50 transition">
                Gerir em "As minhas viagens"
            </a>

            <?php if (!empty($trip['created_by_phone'])): ?>
                <a href="/chat.php?phone=<?= urlencode($trip['created_by_phone']) ?>&name=<?= urlencode($trip['created_by_name'] ?? '') ?>"
                   class="w-full text-center rounded-xl border border-emerald-200 text-emerald-700 font-medium py-3 active:bg-emerald-50 transition">
                    💬 Falar no WhatsApp
                </a>
            <?php endif; ?>
        </div>
    </template>
    <?php
}

$pageTitle = 'Calendário';
$activeTab = 'calendar';
require __DIR__ . '/../views/header-app.php';
?>
    <div class="flex items-center justify-between mb-3">
        <a href="/calendar.php?year=<?= $month === 1 ? $year - 1 : $year ?>&month=<?= $month === 1 ? 12 : $month - 1 ?>"
           class="h-9 w-9 rounded-full bg-white border border-slate-200 flex items-center justify-center active:bg-slate-50">‹</a>
        <h2 class="text-sm font-semibold capitalize"><?= htmlspecialchars($monthLabel, ENT_QUOTES) ?></h2>
        <a href="/calendar.php?year=<?= $month === 12 ? $year + 1 : $year ?>&month=<?= $month === 12 ? 1 : $month + 1 ?>"
           class="h-9 w-9 rounded-full bg-white border border-slate-200 flex items-center justify-center active:bg-slate-50">›</a>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 p-3 mb-4">
        <div class="grid grid-cols-7 text-center text-[11px] font-medium text-slate-400 mb-1">
            <?php foreach ($weekdayLabels as $label): ?>
                <span><?= $label ?></span>
            <?php endforeach; ?>
        </div>
        <div class="grid grid-cols-7 gap-y-1 text-center text-sm">
            <?php for ($i = 1; $i < $startWeekday; $i++): ?>
                <span></span>
            <?php endfor; ?>

            <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
                <?php
                $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
                $hasTrips = isset($tripsByDate[$dateStr]);
                $isSelected = $dateStr === $selectedDate;
                $isToday = $dateStr === $today->format('Y-m-d');
                ?>
                <a href="/calendar.php?year=<?= $year ?>&month=<?= $month ?>&date=<?= $dateStr ?>"
                   class="mx-auto flex flex-col items-center justify-center h-9 w-9 rounded-full transition
                          <?= $isSelected ? 'bg-brand-600 text-white' : ($isToday ? 'bg-brand-50 text-brand-700 font-semibold' : 'text-slate-600 active:bg-slate-100') ?>">
                    <span><?= $day ?></span>
                    <span class="h-1 w-1 rounded-full -mt-0.5 <?= $hasTrips ? ($isSelected ? 'bg-white' : 'bg-brand-500') : 'bg-transparent' ?>"></span>
                </a>
            <?php endfor; ?>
        </div>
    </div>

    <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-2">
        <?= (new DateTime($selectedDate))->format('d/m/Y') ?>
    </h2>

    <?php if ($selectedTrips === []): ?>
        <p class="text-sm text-slate-500">Sem viagens agendadas para este dia.</p>
    <?php else: ?>
        <?php foreach ($selectedTrips as $trip): ?>
            <?php render_calendar_trip_card($trip); ?>
        <?php endforeach; ?>
    <?php endif; ?>
<?php require __DIR__ . '/../views/footer-app.php'; ?>
