CREATE SCHEMA IF NOT EXISTS `trusti` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `trusti`;

DROP TABLE IF EXISTS `emails`;

CREATE TABLE IF NOT EXISTS `emails` (
    id int auto_increment primary key,
    message_id varchar(255) default null,
    status varchar(50) not null,
    email varchar(255) not null,
    retries int default 0,
    subject varchar(255) not null,
    body text not null,
    idempotency_key varchar(255) default null,
    provider varchar(50) default null,
    last_attempt timestamp default current_timestamp,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    error_message text
);


