CREATE SCHEMA IF NOT EXISTS `trusti` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `trusti`;

DROP TABLE IF EXISTS `emails`;
DROP TABLE IF EXISTS `email_statuses`;

CREATE TABLE IF NOT EXISTS `email_statuses` (
    id int auto_increment primary key,
    code varchar(50) not null,
    display_order int default 0
);

CREATE TABLE IF NOT EXISTS `emails` (
    id int auto_increment primary key,
    message_id varchar(255) default null,
    status_id int not null,
    email varchar(255) not null,
    retries int default 0,
    subject varchar(255) not null,
    body text not null,
    idempotency_key varchar(255) default null,
    provider varchar(50) default null,
    last_attempt timestamp default current_timestamp,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    error_message text,
    FOREIGN KEY (status_id) REFERENCES email_statuses(id) ON DELETE RESTRICT ON UPDATE CASCADE
);

INSERT INTO `email_statuses` (code, display_order) VALUES
('queued', 1),
('processing', 2),
('sent', 3),
('delivered', 4),
('failed', 5);

