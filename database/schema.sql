SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
-- users
-- ---------------------------------------------------------------------------
CREATE TABLE users (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email           VARCHAR(190) NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    role            ENUM('admin', 'partner', 'client') NOT NULL,
    full_name       VARCHAR(150) NOT NULL,
    phone           VARCHAR(30) NULL,
    status          ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
    last_login_at   DATETIME NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- partners (parceiros transportadores)
-- ---------------------------------------------------------------------------
CREATE TABLE partners (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NOT NULL,
    company_name    VARCHAR(150) NOT NULL,
    tax_id          VARCHAR(30) NULL,
    partner_type    ENUM('company', 'individual') NOT NULL,
    status          ENUM('pending_approval', 'active', 'suspended', 'blocked') NOT NULL DEFAULT 'pending_approval',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_partners_user (user_id),
    UNIQUE KEY uq_partners_tax_id (tax_id),
    KEY idx_partners_status (status),
    CONSTRAINT fk_partners_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- drivers (motoristas)
-- ---------------------------------------------------------------------------
CREATE TABLE drivers (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    partner_id      BIGINT UNSIGNED NOT NULL,
    full_name       VARCHAR(150) NOT NULL,
    license_number  VARCHAR(50) NULL,
    phone           VARCHAR(30) NULL,
    status          ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_drivers_partner (partner_id),
    CONSTRAINT fk_drivers_partner FOREIGN KEY (partner_id) REFERENCES partners (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- vehicles (viaturas)
-- ---------------------------------------------------------------------------
CREATE TABLE vehicles (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    partner_id          BIGINT UNSIGNED NOT NULL,
    license_plate       VARCHAR(20) NOT NULL,
    vehicle_type        ENUM('sedan', 'minivan', 'van', 'minibus', 'bus') NOT NULL,
    seats_capacity      SMALLINT UNSIGNED NOT NULL,
    luggage_capacity    SMALLINT UNSIGNED NOT NULL,
    status              ENUM('active', 'maintenance', 'inactive') NOT NULL DEFAULT 'active',
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_vehicles_plate (license_plate),
    KEY idx_vehicles_partner (partner_id),
    KEY idx_vehicles_capacity (seats_capacity, luggage_capacity, status),
    CONSTRAINT fk_vehicles_partner FOREIGN KEY (partner_id) REFERENCES partners (id) ON DELETE RESTRICT
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- clients
-- ---------------------------------------------------------------------------
CREATE TABLE clients (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NOT NULL,
    company_name    VARCHAR(150) NOT NULL,
    tax_id          VARCHAR(30) NULL,
    status          ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_clients_user (user_id),
    UNIQUE KEY uq_clients_tax_id (tax_id),
    CONSTRAINT fk_clients_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- service_groups (pedidos consolidados / grupos)
-- ---------------------------------------------------------------------------
CREATE TABLE service_groups (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id           BIGINT UNSIGNED NOT NULL,
    origin              VARCHAR(255) NOT NULL,
    destination          VARCHAR(255) NOT NULL,
    scheduled_at        DATETIME NOT NULL,
    total_passengers    SMALLINT UNSIGNED NOT NULL,
    total_luggage       SMALLINT UNSIGNED NOT NULL,
    split_status        ENUM('not_split', 'partially_split', 'fully_split') NOT NULL DEFAULT 'not_split',
    status              ENUM('draft', 'confirmed', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'draft',
    notes               TEXT NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_service_groups_client (client_id),
    KEY idx_service_groups_scheduled (scheduled_at),
    CONSTRAINT fk_service_groups_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE RESTRICT
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- trips (viagens - unidade real do mural/atribuicao)
-- ---------------------------------------------------------------------------
CREATE TABLE trips (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    service_group_id        BIGINT UNSIGNED NOT NULL,
    passengers_count        SMALLINT UNSIGNED NOT NULL,
    luggage_count           SMALLINT UNSIGNED NOT NULL,
    visibility              ENUM('public', 'private') NOT NULL,
    invited_partner_id      BIGINT UNSIGNED NULL,
    status                  ENUM('open', 'assigned', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'open',
    assigned_partner_id     BIGINT UNSIGNED NULL,
    assigned_vehicle_id     BIGINT UNSIGNED NULL,
    assigned_driver_id      BIGINT UNSIGNED NULL,
    scheduled_at            DATETIME NOT NULL,
    listed_price            DECIMAL(10, 2) NULL,
    agreed_price             DECIMAL(10, 2) NULL,
    version                 INT UNSIGNED NOT NULL DEFAULT 0,
    cancellation_reason     VARCHAR(255) NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_trips_mural (visibility, status, passengers_count, luggage_count, scheduled_at),
    KEY idx_trips_service_group (service_group_id),
    KEY idx_trips_assigned_partner (assigned_partner_id, status),
    CONSTRAINT fk_trips_service_group FOREIGN KEY (service_group_id) REFERENCES service_groups (id) ON DELETE RESTRICT,
    CONSTRAINT fk_trips_invited_partner FOREIGN KEY (invited_partner_id) REFERENCES partners (id) ON DELETE RESTRICT,
    CONSTRAINT fk_trips_assigned_partner FOREIGN KEY (assigned_partner_id) REFERENCES partners (id) ON DELETE RESTRICT,
    CONSTRAINT fk_trips_assigned_vehicle FOREIGN KEY (assigned_vehicle_id) REFERENCES vehicles (id) ON DELETE RESTRICT,
    CONSTRAINT fk_trips_assigned_driver FOREIGN KEY (assigned_driver_id) REFERENCES drivers (id) ON DELETE SET NULL,
    CONSTRAINT chk_trips_visibility CHECK (
        (visibility = 'private' AND invited_partner_id IS NOT NULL) OR
        (visibility = 'public' AND invited_partner_id IS NULL)
    )
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- trip_status_history (auditoria)
-- ---------------------------------------------------------------------------
CREATE TABLE trip_status_history (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    trip_id             BIGINT UNSIGNED NOT NULL,
    from_status         VARCHAR(20) NULL,
    to_status           VARCHAR(20) NOT NULL,
    changed_by_user_id  BIGINT UNSIGNED NULL,
    reason              VARCHAR(255) NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_trip_history_trip (trip_id, created_at),
    CONSTRAINT fk_trip_history_trip FOREIGN KEY (trip_id) REFERENCES trips (id) ON DELETE CASCADE,
    CONSTRAINT fk_trip_history_user FOREIGN KEY (changed_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- passengers (manifesto nominal - opcional)
-- ---------------------------------------------------------------------------
CREATE TABLE passengers (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    trip_id             BIGINT UNSIGNED NOT NULL,
    full_name           VARCHAR(150) NOT NULL,
    document_number     VARCHAR(50) NULL,
    document_type       ENUM('passport', 'id_card', 'other') NULL,
    is_lead_passenger   TINYINT(1) NOT NULL DEFAULT 0,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_passengers_trip (trip_id),
    CONSTRAINT fk_passengers_trip FOREIGN KEY (trip_id) REFERENCES trips (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
