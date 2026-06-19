<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

use App\Database;

$pdo = Database::connection();

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS password_reset_requests (
        id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id                 BIGINT UNSIGNED NOT NULL,
        email                   VARCHAR(190) NOT NULL,
        status                  ENUM('pending', 'resolved') NOT NULL DEFAULT 'pending',
        created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        resolved_at             DATETIME NULL,
        resolved_by_user_id     BIGINT UNSIGNED NULL,
        KEY idx_password_reset_status (status),
        CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
        CONSTRAINT fk_password_reset_resolver FOREIGN KEY (resolved_by_user_id) REFERENCES users (id) ON DELETE SET NULL
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci"
);

echo "Tabela 'password_reset_requests' criada (ou já existia).\n";
