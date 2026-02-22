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
