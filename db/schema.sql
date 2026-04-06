-- -------------------------------------------------------
-- SheepSite resident database schema
-- MySQL 5.7+ / MariaDB 10.3+
--
-- Import via phpMyAdmin:
--   1. Create a database in cPanel (e.g. sheepsite_db)
--   2. Create a MySQL user and grant all privileges on that database
--   3. Open phpMyAdmin, select the database, click Import, upload this file
--
-- All tables use a `building` column (e.g. 'LyndhurstH') so all
-- buildings share one database. Indexes on (building, unit) cover
-- the most common queries.
-- -------------------------------------------------------

-- -------------------------------------------------------
-- residents
-- One row per person per unit. Mirrors the Database tab.
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS residents (
  id          INT           NOT NULL AUTO_INCREMENT,
  building    VARCHAR(50)   NOT NULL,
  unit        VARCHAR(20)   NOT NULL,
  first_name  VARCHAR(100)  NOT NULL DEFAULT '',
  last_name   VARCHAR(100)  NOT NULL DEFAULT '',
  email       VARCHAR(200)  NOT NULL DEFAULT '',
  phone1      VARCHAR(50)   NOT NULL DEFAULT '',
  phone2      VARCHAR(50)   NOT NULL DEFAULT '',
  full_time   TINYINT(1)    NOT NULL DEFAULT 0,
  is_resident TINYINT(1)    NOT NULL DEFAULT 1,
  is_owner    TINYINT(1)    NOT NULL DEFAULT 1,
  board_role  VARCHAR(50)   NOT NULL DEFAULT '',
  created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_building_unit (building, unit),
  INDEX idx_building      (building)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- unit_info
-- One row per unit. Mirrors the UnitDB tab.
-- Insurance and equipment replacement dates belong to the
-- unit, not to individual residents.
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS unit_info (
  id          INT           NOT NULL AUTO_INCREMENT,
  building    VARCHAR(50)   NOT NULL,
  unit        VARCHAR(20)   NOT NULL,
  insurance   VARCHAR(200)  NOT NULL DEFAULT '',
  policy_num  VARCHAR(100)  NOT NULL DEFAULT '',
  ac_replaced DATE              NULL,
  water_tank  DATE              NULL,
  created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY  uk_building_unit (building, unit),
  INDEX       idx_building     (building)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- car_db
-- One row per unit. Mirrors the CarDB tab.
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS car_db (
  id           INT           NOT NULL AUTO_INCREMENT,
  building     VARCHAR(50)   NOT NULL,
  unit         VARCHAR(20)   NOT NULL,
  parking_spot VARCHAR(50)   NOT NULL DEFAULT '',
  make         VARCHAR(100)  NOT NULL DEFAULT '',
  model        VARCHAR(100)  NOT NULL DEFAULT '',
  color        VARCHAR(50)   NOT NULL DEFAULT '',
  plate        VARCHAR(50)   NOT NULL DEFAULT '',
  notes        VARCHAR(500)  NOT NULL DEFAULT '',
  created_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY  uk_building_unit (building, unit),
  INDEX       idx_building     (building)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- emergency
-- One or more rows per unit. Mirrors the Emergency & Condo Sitter tab.
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS emergency (
  id           INT           NOT NULL AUTO_INCREMENT,
  building     VARCHAR(50)   NOT NULL,
  unit         VARCHAR(20)   NOT NULL,
  condo_sitter TINYINT(1)    NOT NULL DEFAULT 0,
  first_name   VARCHAR(100)  NOT NULL DEFAULT '',
  last_name    VARCHAR(100)  NOT NULL DEFAULT '',
  email        VARCHAR(200)  NOT NULL DEFAULT '',
  phone1       VARCHAR(50)   NOT NULL DEFAULT '',
  phone2       VARCHAR(50)   NOT NULL DEFAULT '',
  created_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_building_unit (building, unit),
  INDEX       idx_building (building)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
