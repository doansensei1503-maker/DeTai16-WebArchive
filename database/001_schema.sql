-- =====================================================================
-- DeTai16 - Luu noi dung trang Web de doc lai va tim kiem
-- Schema co ban - MySQL / MariaDB  (utf8mb4 - ho tro tieng Viet)
-- Chay lai nhieu lan an toan: dung IF NOT EXISTS
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1. users - Nguoi dung
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`      VARCHAR(64)  NOT NULL,
  `email`         VARCHAR(190) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `display_name`  VARCHAR(120) NOT NULL DEFAULT '',
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. libraries - Thu vien ( moi thu vien thuoc 1 user)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `libraries` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `owner_id`    INT UNSIGNED NOT NULL,
  `name`        VARCHAR(150) NOT NULL,
  `slug`        VARCHAR(160) NOT NULL,
  `description` TEXT         NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_libraries_owner_slug` (`owner_id`, `slug`),
  KEY `ix_libraries_owner` (`owner_id`),
  CONSTRAINT `fk_libraries_owner`
    FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. library_members - Thanh vien + quyen cua thu vien
--    role: owner | editor | viewer
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `library_members` (
  `library_id` INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL,
  `role`       ENUM('owner','editor','viewer') NOT NULL DEFAULT 'viewer',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`library_id`, `user_id`),
  KEY `ix_lm_user` (`user_id`),
  CONSTRAINT `fk_lm_library`
    FOREIGN KEY (`library_id`) REFERENCES `libraries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lm_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. tags - Nhan
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tags` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `library_id` INT UNSIGNED NOT NULL,
  `name`       VARCHAR(80)  NOT NULL,
  `color`      CHAR(7)      NOT NULL DEFAULT '#3b6ea5',
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tags_library_name` (`library_id`, `name`),
  CONSTRAINT `fk_tags_library`
    FOREIGN KEY (`library_id`) REFERENCES `libraries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. fetch_logs - Nhat ky moi lan lay noi dung ( trang thai thuc te )
--    status: pending|processing|saved|rejected|error|timeout|http_error
--            invalid_url|not_allowed|fetch_failed|no_article
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fetch_logs` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `library_id`       INT UNSIGNED NOT NULL,
  `requested_by`     INT UNSIGNED NULL,
  `requested_url`    VARCHAR(2048) NOT NULL,
  `final_url`        VARCHAR(2048) NULL,
  `status`           VARCHAR(32) NOT NULL DEFAULT 'pending',
  `http_code`        SMALLINT UNSIGNED NULL,
  `message`          VARCHAR(500) NULL,
  `redirect_chain`   TEXT NULL,
  `duration_ms`      INT UNSIGNED NULL,
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_fl_library_created` (`library_id`, `created_at`),
  KEY `ix_fl_status` (`status`),
  CONSTRAINT `fk_fl_library`
    FOREIGN KEY (`library_id`) REFERENCES `libraries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fl_user`
    FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6. saved_pages - Bai da luu ( URL nguon + trang thai + thong tin trich xuat )
--    Goc de mo rong cho chuc nang 4 (tim kiem) va 5 (phien ban).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `saved_pages` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `library_id`         INT UNSIGNED NOT NULL,
  `created_by`         INT UNSIGNED NOT NULL,
  `source_url`         VARCHAR(2048) NOT NULL,
  `source_url_hash`    CHAR(64)     NOT NULL,
  `title`              VARCHAR(500) NOT NULL DEFAULT '',
  `author`             VARCHAR(190) NOT NULL DEFAULT '',
  `published_at`       DATETIME     NULL,
  `excerpt`            TEXT         NULL,
  `content_html`       LONGTEXT     NULL,
  `content_text`       LONGTEXT     NULL,
  `word_count`         INT UNSIGNED NOT NULL DEFAULT 0,
  `language`           VARCHAR(12)  NOT NULL DEFAULT 'vi',
  `status`             VARCHAR(32)  NOT NULL DEFAULT 'saved',
  `content_hash`       CHAR(64)     NULL,
  `layout_used`        VARCHAR(64)  NULL,
  `fetch_log_id`       BIGINT UNSIGNED NULL,
  `saved_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pages_library_url` (`library_id`, `source_url_hash`),
  KEY `ix_pages_library_saved` (`library_id`, `saved_at`),
  KEY `ix_pages_library_status` (`library_id`, `status`),
  KEY `ix_pages_title` (`title`(191)),
  CONSTRAINT `fk_pages_library`
    FOREIGN KEY (`library_id`) REFERENCES `libraries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pages_creator`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pages_fetchlog`
    FOREIGN KEY (`fetch_log_id`) REFERENCES `fetch_logs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7. snapshots - Ban doc lai da lam sach ( luu doc lap, offline)
--    Goc mo rong cho chuc nang 5 (so sanh phien ban): hien tai moi
--    tao snapshot_01 cho moi bai.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `snapshots` (
  `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `saved_page_id`      INT UNSIGNED NOT NULL,
  `version_no`         INT UNSIGNED NOT NULL DEFAULT 1,
  `html`               LONGTEXT     NOT NULL,
  `text_length`        INT UNSIGNED NOT NULL DEFAULT 0,
  `html_hash`          CHAR(64)     NOT NULL,
  `image_count`        INT UNSIGNED NOT NULL DEFAULT 0,
  `missing_image_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `csp`                VARCHAR(500) NULL,
  `created_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_snap_page_version` (`saved_page_id`, `version_no`),
  KEY `ix_snap_hash` (`html_hash`),
  CONSTRAINT `fk_snap_page`
    FOREIGN KEY (`saved_page_id`) REFERENCES `saved_pages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 8. assets - Tai nguyen (anh) da luu cuc bo
--    status: saved|failed|skipped ; failure_reason ghi ro ly do
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `assets` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `library_id`      INT UNSIGNED NOT NULL,
  `original_url`    VARCHAR(2048) NOT NULL,
  `url_hash`        CHAR(64)     NOT NULL,
  `local_path`      VARCHAR(255) NULL,
  `mime_type`       VARCHAR(100) NULL,
  `extension`       VARCHAR(10)  NULL,
  `byte_size`       INT UNSIGNED NOT NULL DEFAULT 0,
  `width`           INT UNSIGNED NULL,
  `height`          INT UNSIGNED NULL,
  `status`          VARCHAR(16)  NOT NULL DEFAULT 'pending',
  `failure_reason`  VARCHAR(190) NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_assets_library_url` (`library_id`, `url_hash`),
  KEY `ix_assets_library_status` (`library_id`, `status`),
  CONSTRAINT `fk_assets_library`
    FOREIGN KEY (`library_id`) REFERENCES `libraries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 9. snapshot_assets - Lien ket snapshot <-> asset
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `snapshot_assets` (
  `snapshot_id` BIGINT UNSIGNED NOT NULL,
  `asset_id`    BIGINT UNSIGNED NOT NULL,
  `ordinal`     INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`snapshot_id`, `asset_id`),
  KEY `ix_sa_asset` (`asset_id`),
  CONSTRAINT `fk_sa_snapshot`
    FOREIGN KEY (`snapshot_id`) REFERENCES `snapshots` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sa_asset`
    FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 10. page_tags - Gan nhan cho bai da luu
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `page_tags` (
  `saved_page_id` INT UNSIGNED NOT NULL,
  `tag_id`        INT UNSIGNED NOT NULL,
  PRIMARY KEY (`saved_page_id`, `tag_id`),
  KEY `ix_pt_tag` (`tag_id`),
  CONSTRAINT `fk_pt_page`
    FOREIGN KEY (`saved_page_id`) REFERENCES `saved_pages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pt_tag`
    FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;