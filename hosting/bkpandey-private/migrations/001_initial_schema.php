<?php
declare(strict_types=1);
// Immutable initial MySQL/MariaDB schema. Never edit an applied migration.
return [
    <<<'SQL'
CREATE TABLE IF NOT EXISTS bp_blog_categories (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(80) NOT NULL UNIQUE,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS bp_blog_posts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 category_id BIGINT UNSIGNED NOT NULL,
 slug VARCHAR(160) NOT NULL UNIQUE,
 title VARCHAR(220) NOT NULL,
 excerpt VARCHAR(700) NOT NULL,
 body MEDIUMTEXT NOT NULL,
 status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
 source_url VARCHAR(2000) NULL,
 origin VARCHAR(80) NOT NULL DEFAULT 'Blog article',
 published_at DATETIME NULL,
 pdf_display ENUM('file','carousel') NOT NULL DEFAULT 'file',
 slider_interval TINYINT UNSIGNED NOT NULL DEFAULT 6,
 version INT UNSIGNED NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_bp_blog_public (status,published_at,id),
 CONSTRAINT fk_bp_blog_category FOREIGN KEY (category_id) REFERENCES bp_blog_categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS bp_blog_assets (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 original_name VARCHAR(255) NOT NULL,
 storage_key VARCHAR(128) NULL,
 public_path VARCHAR(1000) NULL,
 mime_type VARCHAR(80) NOT NULL,
 size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS bp_blog_post_assets (
 post_id BIGINT UNSIGNED NOT NULL,
 asset_id BIGINT UNSIGNED NOT NULL,
 is_cover TINYINT(1) NOT NULL DEFAULT 0,
 sort_order INT UNSIGNED NOT NULL DEFAULT 0,
 PRIMARY KEY(post_id,asset_id),
 KEY idx_bp_asset_posts(asset_id,post_id),
 CONSTRAINT fk_bp_blog_asset_post FOREIGN KEY (post_id) REFERENCES bp_blog_posts(id),
 CONSTRAINT fk_bp_blog_asset_file FOREIGN KEY (asset_id) REFERENCES bp_blog_assets(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS bp_blog_admins (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 email VARCHAR(254) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS bp_blog_login_limits (
 attempt_key CHAR(64) PRIMARY KEY,
 attempts INT UNSIGNED NOT NULL DEFAULT 0,
 window_started BIGINT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS bp_site_settings (
 setting_key VARCHAR(80) PRIMARY KEY,
 setting_value TEXT NOT NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
];
