-- مکان پیش‌فرض کاربر، داده موقت (context) و اعلان اوقات شرعی
-- اجرا روی دیتابیس موجود:
--   mysql ... < database/migrations/2026_09_03_user_place_notify.sql

ALTER TABLE `users`
  ADD COLUMN `context` JSON NULL DEFAULT NULL AFTER `id`;

CREATE TABLE `user_locations` (
  `user_id`    BIGINT UNSIGNED   NOT NULL COMMENT 'Telegram user_id',
  `city_id`    MEDIUMINT UNSIGNED DEFAULT NULL,
  `lat`        DECIMAL(9,6)      NOT NULL,
  `lng`        DECIMAL(9,6)      NOT NULL,
  `created_at` TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  KEY `idx_user_locations_city` (`city_id`),
  CONSTRAINT `fk_user_locations_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_locations_city` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_notify_settings` (
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `fajr`       TINYINT(1)      NOT NULL DEFAULT 0,
  `sunrise`    TINYINT(1)      NOT NULL DEFAULT 0,
  `dhuhr`      TINYINT(1)      NOT NULL DEFAULT 0,
  `asr`        TINYINT(1)      NOT NULL DEFAULT 0,
  `sunset`     TINYINT(1)      NOT NULL DEFAULT 0,
  `maghrib`    TINYINT(1)      NOT NULL DEFAULT 0,
  `isha`       TINYINT(1)      NOT NULL DEFAULT 0,
  `midnight`   TINYINT(1)      NOT NULL DEFAULT 0,
  `updated_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_user_notify_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notify_sent` (
  `user_id`  BIGINT UNSIGNED NOT NULL,
  `prayer`   VARCHAR(16)     NOT NULL,
  `for_date` DATE            NOT NULL,
  `sent_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `prayer`, `for_date`),
  KEY `idx_notify_sent_date` (`for_date`),
  CONSTRAINT `fk_notify_sent_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
