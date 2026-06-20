<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

use App\Database;

$pdo = Database::connection();

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS email_change_requests (
        id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id                 BIGINT UNSIGNED NOT NULL,
        current_email           VARCHAR(190) NOT NULL,
        new_email               VARCHAR(190) NOT NULL,
        status                  ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
        created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        resolved_at             DATETIME NULL,
        resolved_by_user_id     BIGINT UNSIGNED NULL,
        KEY idx_email_change_status (status),
        CONSTRAINT fk_email_change_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
        CONSTRAINT fk_email_change_resolver FOREIGN KEY (resolved_by_user_id) REFERENCES users (id) ON DELETE SET NULL
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci"
);

echo "Tabela 'email_change_requests' criada (ou já existia).\n";
