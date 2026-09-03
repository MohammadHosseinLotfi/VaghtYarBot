CREATE TABLE `provinces` (
 `id` tinyint(3) unsigned NOT NULL,
 `name_fa` varchar(100) DEFAULT NULL,
 `name_normalized` varchar(100) DEFAULT NULL,
 `name_en` varchar(100) DEFAULT NULL,
 PRIMARY KEY (`id`),
 KEY `idx_prov_name_normalized` (`name_normalized`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci

CREATE TABLE users (
    id         BIGINT UNSIGNED  NOT NULL  COMMENT 'Telegram user_id',
    context    JSON             NULL      DEFAULT NULL,
    created_at TIMESTAMP        NOT NULL  DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP        NOT NULL  DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `calendar_events` (
  `id`           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `calendar`     ENUM('Persian','Hijri','Gregorian') NOT NULL,
  `month`        TINYINT UNSIGNED NOT NULL,
  `day`          TINYINT UNSIGNED DEFAULT NULL,
  `title`        VARCHAR(512)     NOT NULL,
  `type`         VARCHAR(20)      NOT NULL DEFAULT 'Iran',
  `holiday`      TINYINT(1)       NOT NULL DEFAULT 0,
  `is_irregular` TINYINT(1)       NOT NULL DEFAULT 0,
  `rule`         VARCHAR(30)      DEFAULT NULL,
  `nth`          TINYINT          DEFAULT NULL,
  `weekday`      TINYINT UNSIGNED DEFAULT NULL,
  `offset`       TINYINT          DEFAULT NULL,
  `year`         SMALLINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_lookup`  (`calendar`, `month`, `day`),
  INDEX `idx_holiday` (`holiday`),
  INDEX `idx_type`    (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cities` (
 `id` mediumint(8) unsigned NOT NULL,
 `p_id` tinyint(3) unsigned NOT NULL,
 `name_fa` varchar(100) NOT NULL,
 `name_normalized` varchar(100) DEFAULT NULL,
 `name_en` varchar(100) DEFAULT NULL,
 `lat` decimal(9,6) DEFAULT NULL,
 `lon` decimal(9,6) DEFAULT NULL,
 `is_capital` tinyint(1) NOT NULL DEFAULT 0,
 PRIMARY KEY (`id`),
 KEY `idx_province_id` (`p_id`),
 KEY `idx_name_normalized` (`name_normalized`),
 CONSTRAINT `fk_city_province` FOREIGN KEY (`p_id`) REFERENCES `provinces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
