-- ============================================================
-- 英语词库学习网站 数据库结构
-- MySQL 5.7+ / 8.0（宝塔面板直接导入本文件即可）
-- ============================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username                VARCHAR(50)  NOT NULL,
  email                   VARCHAR(190) NULL,
  email_verified          TINYINT(1) NOT NULL DEFAULT 0,
  password_hash           VARCHAR(255) NOT NULL,
  role                    ENUM('user','admin') NOT NULL DEFAULT 'user',
  wordbook_limit_override INT NULL,
  autofill_limit_override INT NULL,
  created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_username (username),
  KEY idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_tokens (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED NOT NULL,
  token         VARCHAR(64)  NOT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_activity DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at    DATETIME NOT NULL,
  UNIQUE KEY uq_token (token),
  KEY idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wordbooks (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id            INT UNSIGNED NOT NULL,
  name               VARCHAR(100) NOT NULL,
  description        TEXT NULL,
  is_public          TINYINT(1) NOT NULL DEFAULT 0,
  status             ENUM('active','taken_down') NOT NULL DEFAULT 'active',
  source_wordbook_id INT UNSIGNED NULL,
  copied_count       INT UNSIGNED NOT NULL DEFAULT 0,
  published_at       DATETIME NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_user (user_id),
  KEY idx_public (is_public, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS words (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  wordbook_id   INT UNSIGNED NOT NULL,
  word          VARCHAR(190) NOT NULL,
  phonetic      VARCHAR(190) NULL,
  audio_url     VARCHAR(500) NULL,
  pos           VARCHAR(50)  NULL,
  meaning_zh    TEXT NULL,
  definition_en TEXT NULL,
  example       TEXT NULL,
  example_zh    TEXT NULL,
  note          TEXT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_wordbook (wordbook_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quiz_records (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  wordbook_id INT UNSIGNED NOT NULL,
  type        VARCHAR(20) NOT NULL,
  total       INT UNSIGNED NOT NULL DEFAULT 0,
  correct     INT UNSIGNED NOT NULL DEFAULT 0,
  detail      JSON NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reports (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  wordbook_id INT UNSIGNED NOT NULL,
  reporter_id INT UNSIGNED NOT NULL,
  reason      TEXT NULL,
  status      ENUM('pending','resolved','rejected') NOT NULL DEFAULT 'pending',
  handled_by  INT UNSIGNED NULL,
  handled_at  DATETIME NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_status (status),
  KEY idx_wordbook (wordbook_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dict_cache (
  word       VARCHAR(190) PRIMARY KEY,
  data       JSON NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_settings (
  `key`      VARCHAR(100) PRIMARY KEY,
  value      TEXT NULL,
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
  KEY idx_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS donation_channels (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(100) NOT NULL,
  description TEXT NULL,
  image_url  VARCHAR(500) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active  TINYINT(1) NOT NULL DEFAULT 1,
  KEY idx_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_codes (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email      VARCHAR(190) NOT NULL,
  code_hash  VARCHAR(64)  NOT NULL,
  purpose    VARCHAR(30)  NOT NULL DEFAULT 'verify',
  expires_at DATETIME NOT NULL,
  attempts   INT NOT NULL DEFAULT 0,
  ip         VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_email_purpose (email, purpose),
  KEY idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usage_logs (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  metric     VARCHAR(50)  NOT NULL,
  period_key VARCHAR(20)  NOT NULL,
  count      INT UNSIGNED NOT NULL DEFAULT 0,
  UNIQUE KEY uq_usage (user_id, metric, period_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
