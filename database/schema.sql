CREATE TABLE provinces (
    id      SMALLINT UNSIGNED NOT NULL,
    name    VARCHAR(100)      NOT NULL,
    capital VARCHAR(100)      NOT NULL,
    code    VARCHAR(5)        NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cities (
    id          SMALLINT UNSIGNED NOT NULL,
    name        VARCHAR(100)      NOT NULL,
    province_id SMALLINT UNSIGNED NOT NULL,
    latitude    DECIMAL(12,8)     NOT NULL,
    longitude   DECIMAL(12,8)     NOT NULL,
    PRIMARY KEY (id),
    KEY idx_name        (name),
    KEY idx_province_id (province_id),
    CONSTRAINT fk_city_province FOREIGN KEY (province_id) REFERENCES provinces(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    id         BIGINT UNSIGNED  NOT NULL  COMMENT 'Telegram user_id',
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

CREATE TABLE cities (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    province_id SMALLINT UNSIGNED NOT NULL,
    district_id SMALLINT UNSIGNED NULL,
    latitude DECIMAL(10,7) NOT NULL,
    longitude DECIMAL(10,7) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_name (name),
    KEY idx_province_id (province_id),
    KEY idx_district_id (district_id),
    CONSTRAINT fk_cities_province FOREIGN KEY (province_id) REFERENCES provinces(id),
    CONSTRAINT fk_cities_district FOREIGN KEY (district_id) REFERENCES districts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
