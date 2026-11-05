<?php
declare(strict_types=1);

return [
    <<<'SQL'
CREATE TABLE bp_traffic_daily (
    day DATE PRIMARY KEY,
    requests BIGINT UNSIGNED NOT NULL,
    page_attempts BIGINT UNSIGNED NOT NULL,
    page_views BIGINT UNSIGNED NOT NULL,
    failed_attempts BIGINT UNSIGNED NOT NULL,
    bot_requests BIGINT UNSIGNED NOT NULL,
    collected_at DATETIME NOT NULL,
    mail_status VARCHAR(12) NOT NULL DEFAULT 'pending',
    mail_attempted_at DATETIME NULL
) ENGINE=InnoDB
SQL,
    <<<'SQL'
CREATE TABLE bp_traffic_visitors (
    day DATE NOT NULL,
    kind CHAR(1) CHARACTER SET ascii NOT NULL,
    fingerprint BINARY(32) NOT NULL,
    source VARCHAR(24) CHARACTER SET ascii NOT NULL,
    source_at BIGINT NOT NULL,
    PRIMARY KEY (day, kind, fingerprint)
) ENGINE=InnoDB
SQL,
    <<<'SQL'
CREATE TABLE bp_traffic_cursors (
    log_name VARCHAR(80) CHARACTER SET ascii PRIMARY KEY,
    byte_offset BIGINT UNSIGNED NOT NULL,
    boundary_hash BINARY(32) NOT NULL,
    archive_mtime BIGINT UNSIGNED NOT NULL,
    archive_size BIGINT UNSIGNED NOT NULL,
    finished TINYINT UNSIGNED NOT NULL
) ENGINE=InnoDB
SQL,
];
