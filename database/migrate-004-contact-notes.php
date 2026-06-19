<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

use App\Database;

$pdo = Database::connection();

function column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column"
    );
    $stmt->execute(['table' => $table, 'column' => $column]);

    return (int) $stmt->fetchColumn() > 0;
}

if (!column_exists($pdo, 'service_groups', 'contact_phone')) {
    $pdo->exec("ALTER TABLE service_groups ADD COLUMN contact_phone VARCHAR(30) NULL AFTER notes");
    echo "Coluna 'contact_phone' adicionada a service_groups.\n";
} else {
    echo "Coluna 'contact_phone' já existia em service_groups.\n";
}

if (!column_exists($pdo, 'trips', 'contact_phone')) {
    $pdo->exec("ALTER TABLE trips ADD COLUMN contact_phone VARCHAR(30) NULL AFTER agreed_price");
    echo "Coluna 'contact_phone' adicionada a trips.\n";
} else {
    echo "Coluna 'contact_phone' já existia em trips.\n";
}

if (!column_exists($pdo, 'trips', 'notes')) {
    $pdo->exec("ALTER TABLE trips ADD COLUMN notes TEXT NULL AFTER contact_phone");
    echo "Coluna 'notes' adicionada a trips.\n";
} else {
    echo "Coluna 'notes' já existia em trips.\n";
}
