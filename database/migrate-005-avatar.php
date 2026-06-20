<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

use App\Database;

$pdo = Database::connection();

$columnExistsStmt = $pdo->query(
    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'avatar_path'"
);

if ((int) $columnExistsStmt->fetchColumn() === 0) {
    $pdo->exec("ALTER TABLE users ADD COLUMN avatar_path VARCHAR(255) NULL AFTER phone");
    echo "Coluna 'avatar_path' adicionada a users.\n";
} else {
    echo "Coluna 'avatar_path' já existia em users.\n";
}
