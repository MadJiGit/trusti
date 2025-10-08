CREATE SCHEMA IF NOT EXISTS `trusti` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `trusti`;

DROP TABLE IF EXISTS `provider_emails`;

CREATE TABLE IF NOT EXISTS `provider_emails` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        idempotency_key VARCHAR(255) NOT NULL UNIQUE,
        message_id VARCHAR(255) NOT NULL UNIQUE,
        status_id INT NOT NULL,
        error_message TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        delivered_at TIMESTAMP NULL DEFAULT NULL,
        FOREIGN KEY (status_id) REFERENCES email_statuses(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        INDEX idx_idempotency_key (idempotency_key),
        INDEX idx_message_id (message_id)
    );
