<?php
declare(strict_types=1);

return [
    <<<'SQL'
CREATE TABLE bp_social_jobs (
    post_id BIGINT UNSIGNED NOT NULL,
    platform VARCHAR(20) CHARACTER SET ascii NOT NULL,
    channel_id VARCHAR(100) CHARACTER SET ascii NOT NULL,
    status VARCHAR(16) CHARACTER SET ascii NOT NULL DEFAULT 'queued',
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    run_after DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    remote_id VARCHAR(160) CHARACTER SET ascii NULL,
    PRIMARY KEY (post_id, platform),
    KEY bp_social_pending (status, run_after),
    CONSTRAINT bp_social_post FOREIGN KEY (post_id) REFERENCES bp_blog_posts (id) ON DELETE CASCADE
) ENGINE=InnoDB
SQL,
];
