-- KO-LMS V16 Initial Schema
-- Enforces application status requirements at the DB level

CREATE TABLE IF NOT EXISTS accounts (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NULL,
    status ENUM('IDLE','RESERVED','FARMING','DROPPED','FAILED_SECRET','ERROR_SECRET','STALE','RECOVERED','TIMEOUT') NOT NULL DEFAULT 'IDLE',
    last_run_at DATETIME NULL,
    locked_until DATETIME NULL,
    heartbeat_at DATETIME NULL,
    batch_id VARCHAR(64) NULL,
    last_error TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    key_version VARCHAR(20) DEFAULT 'v1',
    xp INT DEFAULT 0,
    level INT DEFAULT 0
);

CREATE TABLE IF NOT EXISTS farm_batches (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    batch_id VARCHAR(64) NOT NULL UNIQUE,
    status ENUM('CREATED','RESERVED','RUNNING','STALE','ABORTING','STOPPING','FINISHED','FAILED','RECOVERED','CANCELLED','TIMEOUT') NOT NULL,
    created_at DATETIME NOT NULL,
    started_at DATETIME NULL,
    finished_at DATETIME NULL,
    last_heartbeat_at DATETIME NULL,
    error_message TEXT NULL
);

CREATE TABLE IF NOT EXISTS farm_batch_accounts (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    batch_id VARCHAR(64) NOT NULL,
    account_id BIGINT NOT NULL,
    role VARCHAR(32) NOT NULL,
    container_name VARCHAR(128) NULL,
    status ENUM('RESERVED','RUNNING','STALE','STOPPING','FINISHED','FAILED','RECOVERED','CANCELLED') NOT NULL,
    heartbeat_at DATETIME NULL,
    last_error TEXT NULL
);

CREATE TABLE IF NOT EXISTS admin_users (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    MFA_SECRET VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL
);

CREATE TABLE IF NOT EXISTS admin_audit_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    admin_user VARCHAR(64) NOT NULL,
    ip_address VARCHAR(64) NOT NULL,
    action VARCHAR(128) NOT NULL,
    target_type VARCHAR(64) NULL,
    target_id VARCHAR(128) NULL,
    details JSON NULL,
    created_at DATETIME NOT NULL
);

CREATE TABLE IF NOT EXISTS action_queue (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    idempotency_key VARCHAR(128) UNIQUE NULL,
    action_type VARCHAR(64) NOT NULL,
    payload JSON NOT NULL,
    status ENUM('PENDING','PROCESSING','COMPLETED','FAILED','DEAD') DEFAULT 'PENDING',
    worker_id VARCHAR(100) NULL,
    retry_count INT DEFAULT 0,
    max_retry INT DEFAULT 3,
    timeout_seconds INT DEFAULT 300,
    locked_until DATETIME NULL,
    last_error TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    completed_at DATETIME NULL,
    result TEXT NULL
);

CREATE TABLE IF NOT EXISTS system_alerts (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    severity ENUM('INFO','WARNING','CRITICAL') NOT NULL,
    alert_type VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    related_entity_type VARCHAR(50) NULL,
    related_entity_id VARCHAR(100) NULL,
    status ENUM('OPEN','ACKED','RESOLVED') NOT NULL DEFAULT 'OPEN',
    created_at DATETIME NOT NULL,
    acknowledged_at DATETIME NULL,
    resolved_at DATETIME NULL
);

CREATE TABLE IF NOT EXISTS login_attempts (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NULL,
    ip_address VARCHAR(64) NOT NULL,
    success TINYINT(1) NOT NULL,
    failure_reason VARCHAR(100) NULL,
    created_at DATETIME NOT NULL
);

CREATE TABLE IF NOT EXISTS worker_heartbeats (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    worker_name VARCHAR(100) NOT NULL,
    worker_type ENUM('ORCHESTRATOR','HOST_WORKER','BACKUP_WORKER','ALERT_WORKER') NOT NULL,
    heartbeat_at DATETIME NOT NULL,
    status ENUM('OK','WARNING','ERROR') NOT NULL DEFAULT 'OK',
    last_error TEXT NULL
);

CREATE TABLE IF NOT EXISTS backup_runs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    backup_file VARCHAR(255) NOT NULL,
    backup_type ENUM('DB','KEY','FULL') NOT NULL,
    status ENUM('STARTED','COMPLETED','FAILED','RESTORE_TESTED') NOT NULL,
    size_bytes BIGINT NULL,
    checksum_sha256 VARCHAR(128) NULL,
    started_at DATETIME NOT NULL,
    finished_at DATETIME NULL,
    error_message TEXT NULL
);

CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value VARCHAR(255)
);

INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES ('maintenance_mode', 'COMPLETED');
INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES ('allowed_ips', '127.0.0.1,::1');
