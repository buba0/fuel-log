CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(48) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'PLN',
    language CHAR(2) NOT NULL DEFAULT 'en',
    unit_preset VARCHAR(8) NOT NULL DEFAULT 'metric',
    unit_distance VARCHAR(12) NOT NULL DEFAULT 'km',
    unit_volume VARCHAR(12) NOT NULL DEFAULT 'l',
    unit_economy VARCHAR(12) NOT NULL DEFAULT 'l_100km',
    unit_price VARCHAR(12) NOT NULL DEFAULT 'l',
    theme VARCHAR(8) NOT NULL DEFAULT 'system',
    api_token_hash CHAR(64) NULL,
    grafana_token_hash CHAR(64) NULL,
    default_car_id BIGINT UNSIGNED NULL,
    default_station_id BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    UNIQUE KEY users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cars (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    tank_capacity_liters DECIMAL(10,5) NOT NULL,
    vehicle_type VARCHAR(16) NOT NULL DEFAULT 'car',
    fuel_type VARCHAR(16) NOT NULL DEFAULT 'other',
    make VARCHAR(60) NOT NULL DEFAULT '',
    model VARCHAR(60) NOT NULL DEFAULT '',
    model_year SMALLINT UNSIGNED NULL,
    engine VARCHAR(100) NOT NULL DEFAULT '',
    registration VARCHAR(30) NOT NULL DEFAULT '',
    fuel_metering TINYINT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY cars_owner_id (user_id, id),
    CONSTRAINT cars_owner_fk FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    location VARCHAR(150) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    UNIQUE KEY stations_owner_id (user_id, id),
    CONSTRAINT stations_owner_fk FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fillups (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    car_id BIGINT UNSIGNED NOT NULL,
    station_id BIGINT UNSIGNED NOT NULL,
    filled_at DATE NOT NULL,
    liters DECIMAL(10,5) NOT NULL,
    price_per_liter DECIMAL(13,7) NOT NULL,
    total_paid DECIMAL(15,2) NOT NULL,
    odometer_km DECIMAL(14,5) NOT NULL,
    consumption_l_100km DECIMAL(11,6) NOT NULL,
    tank_before_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
    tank_capacity_liters DECIMAL(10,5) NOT NULL,
    tank_before_liters DECIMAL(10,5) NOT NULL,
    anomaly_dismissed TINYINT NOT NULL DEFAULT 0,
    estimated_tank TINYINT NOT NULL DEFAULT 0,
    estimated_odometer TINYINT NOT NULL DEFAULT 0,
    reported_consumption DECIMAL(11,6) NULL,
    PRIMARY KEY (id),
    KEY fillups_owner_date (user_id, filled_at),
    KEY fillups_owner_car_date (user_id, car_id, filled_at, odometer_km),
    CONSTRAINT fillups_car_fk FOREIGN KEY (user_id, car_id) REFERENCES cars(user_id, id),
    CONSTRAINT fillups_station_fk FOREIGN KEY (user_id, station_id) REFERENCES stations(user_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS api_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(80) NOT NULL,
    token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY api_tokens_hash (token_hash),
    KEY api_tokens_owner (user_id),
    CONSTRAINT api_tokens_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
