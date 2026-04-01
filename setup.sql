-- ============================================================
--  setup.sql  —  Stock Tracking Database Schema
--  Run once:  mysql -u root -p < setup.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS stock_tracking
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE stock_tracking;

-- ── Users ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    name       VARCHAR(120)    NOT NULL,
    email      VARCHAR(254)    NOT NULL UNIQUE,
    pass_hash  VARCHAR(255)    NOT NULL,
    initials   VARCHAR(4)      NOT NULL DEFAULT '',
    created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Portfolios ───────────────────────────────────────────────
-- Each user has exactly one portfolio row (upsert on login/trade)
CREATE TABLE IF NOT EXISTS portfolios (
    user_id   INT UNSIGNED NOT NULL,
    cash      DECIMAL(18,4) NOT NULL DEFAULT 1000000.0000,
    hold_json MEDIUMTEXT,          -- JSON: { "RELIANCE.NS": { sh:10, avg:2900 }, ... }
    txns_json MEDIUMTEXT,          -- JSON: [ { t, sym, sh, p, tot, d }, ... ]
    updated   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    CONSTRAINT fk_port_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Watchlists ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS watchlists (
    user_id  INT UNSIGNED NOT NULL,
    symbols  TEXT,                 -- JSON: ["RELIANCE.NS","TCS.NS",...]
    updated  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    CONSTRAINT fk_wl_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── User Settings ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS user_settings (
    user_id       INT UNSIGNED NOT NULL,
    settings_json TEXT,            -- JSON: { theme, layout, font, autoref, ... }
    updated       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    CONSTRAINT fk_set_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
