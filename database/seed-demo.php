<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

use App\Database;

$pdo = Database::connection();

$stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email');
$stmt->execute(['email' => 'parceiro-demo@viaz.pt']);

if ($stmt->fetch()) {
    fwrite(STDERR, "Os dados de demonstração já existem (parceiro-demo@viaz.pt encontrado). Nada a fazer.\n");
    exit(1);
}

$pdo->beginTransaction();

try {
    $pdo->prepare(
        "INSERT INTO users (email, password_hash, role, full_name, status)
         VALUES ('parceiro-demo@viaz.pt', :password_hash, 'partner', 'Parceiro Demo', 'active')"
    )->execute(['password_hash' => password_hash('Demo12345!', PASSWORD_DEFAULT)]);
    $partnerUserId = (int) $pdo->lastInsertId();

    $pdo->prepare(
        "INSERT INTO partners (user_id, company_name, partner_type, status)
         VALUES (:user_id, 'Transportes Demo', 'company', 'active')"
    )->execute(['user_id' => $partnerUserId]);
    $partnerId = (int) $pdo->lastInsertId();

    $pdo->prepare(
        "INSERT INTO vehicles (partner_id, license_plate, vehicle_type, seats_capacity, luggage_capacity, status)
         VALUES (:partner_id, 'DEMO-01', 'van', 8, 8, 'active')"
    )->execute(['partner_id' => $partnerId]);

    $pdo->prepare(
        "INSERT INTO users (email, password_hash, role, full_name, status)
         VALUES ('cliente-demo@viaz.pt', :password_hash, 'client', 'Cliente Demo', 'active')"
    )->execute(['password_hash' => password_hash('Demo12345!', PASSWORD_DEFAULT)]);
    $clientUserId = (int) $pdo->lastInsertId();

    $pdo->prepare(
        "INSERT INTO clients (user_id, company_name, status)
         VALUES (:user_id, 'Cliente Demo Lda', 'active')"
    )->execute(['user_id' => $clientUserId]);
    $clientId = (int) $pdo->lastInsertId();

    $scheduledAt = (new DateTime('+1 day'))->setTime(12, 0)->format('Y-m-d H:i:s');

    $pdo->prepare(
        "INSERT INTO service_groups (client_id, origin, destination, scheduled_at, total_passengers, total_luggage, status)
         VALUES (:client_id, 'Aeroporto de Lisboa', 'Hotel Marriott', :scheduled_at, 10, 10, 'confirmed')"
    )->execute(['client_id' => $clientId, 'scheduled_at' => $scheduledAt]);
    $groupId = (int) $pdo->lastInsertId();

    $pdo->prepare(
        "INSERT INTO trips (service_group_id, passengers_count, luggage_count, visibility, status, scheduled_at, listed_price)
         VALUES (:group_id, 4, 4, 'public', 'open', :scheduled_at, 35.00)"
    )->execute(['group_id' => $groupId, 'scheduled_at' => $scheduledAt]);

    $pdo->prepare(
        "INSERT INTO trips (service_group_id, passengers_count, luggage_count, visibility, status, scheduled_at, listed_price)
         VALUES (:group_id, 10, 10, 'public', 'open', :scheduled_at, 70.00)"
    )->execute(['group_id' => $groupId, 'scheduled_at' => $scheduledAt]);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}

echo "Dados de demonstração criados com sucesso.\n";
echo "Login do parceiro: parceiro-demo@viaz.pt / Demo12345!\n";
echo "Viatura DEMO-01: 8 lugares, 8 malas.\n";
echo "2 viagens publicas criadas: uma com 4 passageiros/4 malas (elegivel), outra com 10/10 (nao elegivel).\n";
