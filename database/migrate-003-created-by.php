<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

use App\Database;

$pdo = Database::connection();

$columnExistsStmt = $pdo->query(
    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'service_groups' AND COLUMN_NAME = 'created_by_user_id'"
);

if ((int) $columnExistsStmt->fetchColumn() === 0) {
    $pdo->exec(
        "ALTER TABLE service_groups
         ADD COLUMN created_by_user_id BIGINT UNSIGNED NULL AFTER notes,
         ADD CONSTRAINT fk_service_groups_created_by FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE SET NULL"
    );
    echo "Coluna 'created_by_user_id' adicionada a service_groups.\n";
} else {
    echo "Coluna 'created_by_user_id' já existia em service_groups.\n";
}
