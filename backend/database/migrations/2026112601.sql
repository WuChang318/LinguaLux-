-- ============================================================
-- LinguaLux 增量迁移 2026112601（用于已部署的旧库升级）
-- 在宝塔数据库里执行本文件即可（不要重复执行 schema.sql 全量）
-- ============================================================

SET NAMES utf8mb4;

ALTER TABLE users
  ADD COLUMN email VARCHAR(190) NULL AFTER username,
  ADD COLUMN email_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER email,
  ADD COLUMN wordbook_limit_override INT NULL,
  ADD COLUMN autofill_limit_override INT NULL;

ALTER TABLE auth_tokens
  ADD COLUMN last_activity DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER created_at;

CREATE TABLE IF NOT EXISTS site_settings (
  `key`      VARCHAR(80) PRIMARY KEY,
  `value`    TEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS announcements (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title      VARCHAR(200) NOT NULL,
  content    MEDIUMTEXT NOT NULL,
  is_pinned  TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  is_active  TINYINT(1) NOT NULL DEFAULT 1,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_active_sort (is_active, is_pinned, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS donation_channels (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(100) NOT NULL,
  description VARCHAR(500) NULL,
  image_url   VARCHAR(255) NULL,
  sort_order  INT NOT NULL DEFAULT 0,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_codes (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email      VARCHAR(190) NOT NULL,
  code_hash  VARCHAR(64) NOT NULL,
  purpose    VARCHAR(30) NOT NULL DEFAULT 'verify',
  expires_at DATETIME NOT NULL,
  attempts   INT NOT NULL DEFAULT 0,
  ip         VARCHAR(45) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_email_time (email, created_at),
  KEY idx_ip_time (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usage_logs (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  metric     VARCHAR(30) NOT NULL,
  period_key VARCHAR(20) NOT NULL,
  `count`    INT UNSIGNED NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_metric_period (user_id, metric, period_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
