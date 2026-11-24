SET NAMES utf8mb4;
-- =========================================================================
-- FreightIQ Marine :: Intelligent Freight Forecasting, Vessel Chartering
-- & Bulk Cargo Procurement Platform  —  Database Schema
-- Ministry of Steel / SAIL — East Coast India bulk cargo procurement
-- Import via phpMyAdmin (XAMPP) or: mysql -u root -p < schema.sql
-- =========================================================================

CREATE DATABASE IF NOT EXISTS freightiq_marine
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE freightiq_marine;

SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------------------------------------------------
-- ROLES & PERMISSIONS (RBAC)
-- -------------------------------------------------------------------------
CREATE TABLE roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  role_key VARCHAR(30) NOT NULL UNIQUE,     -- admin, chartering_manager, shipper, analyst, viewer
  display_name VARCHAR(60) NOT NULL,
  description VARCHAR(255)
) ENGINE=InnoDB;

CREATE TABLE permissions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  perm_key VARCHAR(60) NOT NULL UNIQUE,     -- e.g. forecast.view, contracts.create, risk.view, admin.manage_users
  description VARCHAR(255)
) ENGINE=InnoDB;

CREATE TABLE role_permissions (
  role_id INT NOT NULL,
  permission_id INT NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -------------------------------------------------------------------------
-- USERS
-- -------------------------------------------------------------------------
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role_id INT NOT NULL,
  organisation VARCHAR(120) DEFAULT 'SAIL',
  phone VARCHAR(30),
  failed_attempts INT DEFAULT 0,
  locked_until DATETIME NULL,
  last_login DATETIME NULL,
  is_active TINYINT(1) DEFAULT 1,
  deleted_at DATETIME NULL,                 -- soft delete
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (role_id) REFERENCES roles(id),
  INDEX idx_users_email (email)
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  action VARCHAR(150) NOT NULL,
  entity_type VARCHAR(60),
  entity_id INT NULL,
  details TEXT,
  ip_address VARCHAR(64),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_audit_user (user_id), INDEX idx_audit_created (created_at)
) ENGINE=InnoDB;

CREATE TABLE notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,                          -- NULL = broadcast to all
  category ENUM('Freight Rate Change','Forecast Opportunity','Port Congestion','Vessel Delay',
                 'Contract Expiry','Risk Escalation','Cargo Status') NOT NULL,
  title VARCHAR(150) NOT NULL,
  message VARCHAR(500) NOT NULL,
  severity ENUM('Info','Success','Warning','Critical') DEFAULT 'Info',
  is_read TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -------------------------------------------------------------------------
-- ORIGINS & COMMODITIES
-- -------------------------------------------------------------------------
CREATE TABLE origins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  country VARCHAR(80) NOT NULL,
  load_point VARCHAR(120) NOT NULL,
  latitude DECIMAL(9,6), longitude DECIMAL(9,6),
  UNIQUE KEY uniq_origin (country, load_point)
) ENGINE=InnoDB;

CREATE TABLE commodities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE,          -- Iron Ore, Coking Coal, Limestone, Thermal Coal
  category VARCHAR(60) DEFAULT 'Bulk Mineral',
  stowage_factor DECIMAL(6,3) DEFAULT 0.5    -- m3/tonne, for capacity calc realism
) ENGINE=InnoDB;

-- -------------------------------------------------------------------------
-- PORTS (East Coast India) + LIVE-STYLE CONGESTION FEED
-- -------------------------------------------------------------------------
CREATE TABLE ports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  code VARCHAR(12) NOT NULL UNIQUE,
  coast ENUM('East','West') NOT NULL DEFAULT 'East',
  state VARCHAR(60),
  latitude DECIMAL(9,6) NOT NULL,
  longitude DECIMAL(9,6) NOT NULL,
  max_loa_m DECIMAL(6,2) NOT NULL,
  max_beam_m DECIMAL(5,2) NOT NULL,
  max_draft_m DECIMAL(5,2) NOT NULL,
  cargo_handling_capacity_tpd INT NOT NULL,      -- tonnes/day
  cargo_handling_rate_usd_per_tonne DECIMAL(6,2) DEFAULT 3.50,
  berthing_limitations VARCHAR(255),
  historical_turnaround_days DECIMAL(4,1) DEFAULT 3.0,
  status ENUM('Operational','Restricted','Closed') DEFAULT 'Operational',
  data_source VARCHAR(60) DEFAULT 'Simulated (demo)',
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE port_congestion (
  id INT AUTO_INCREMENT PRIMARY KEY,
  port_id INT NOT NULL,
  congestion_level ENUM('Low','Moderate','High','Severe') NOT NULL DEFAULT 'Low',
  congestion_index DECIMAL(4,2) NOT NULL DEFAULT 1.00,  -- 1.0 = normal
  estimated_turnaround_days DECIMAL(4,1) NOT NULL,
  vessels_waiting INT DEFAULT 0,
  weather_status ENUM('Clear','Advisory','Storm Warning','Cyclone Alert') DEFAULT 'Clear',
  recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (port_id) REFERENCES ports(id) ON DELETE CASCADE,
  INDEX idx_congestion_port_time (port_id, recorded_at)
) ENGINE=InnoDB;

-- -------------------------------------------------------------------------
-- VESSEL TYPES + VESSEL FLEET + LIVE-STYLE GPS POSITIONS
-- -------------------------------------------------------------------------
CREATE TABLE vessel_classes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  class_name VARCHAR(40) NOT NULL UNIQUE,     -- Handysize, Supramax, Panamax, Capesize
  dwt_min INT NOT NULL, dwt_max INT NOT NULL,
  typical_loa_m DECIMAL(6,2), typical_beam_m DECIMAL(5,2), typical_draft_m DECIMAL(5,2),
  base_daily_hire_usd DECIMAL(10,2) NOT NULL,
  fuel_consumption_tpd DECIMAL(6,2) NOT NULL,
  service_speed_knots DECIMAL(4,1) DEFAULT 13.0
) ENGINE=InnoDB;

CREATE TABLE vessels (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vessel_name VARCHAR(100) NOT NULL,
  imo_number VARCHAR(20) NOT NULL UNIQUE,
  class_id INT NOT NULL,
  dwt INT NOT NULL, loa_m DECIMAL(6,2) NOT NULL, beam_m DECIMAL(5,2) NOT NULL, draft_m DECIMAL(5,2) NOT NULL,
  flag VARCHAR(60) DEFAULT 'Panama',
  build_year INT,
  availability ENUM('Available','On Voyage','Idle','Under Repair') DEFAULT 'Available',
  idle_status TINYINT(1) DEFAULT 0,
  current_voyage_id INT NULL,
  current_contract_id INT NULL,
  eta DATETIME NULL, etd DATETIME NULL,
  last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (class_id) REFERENCES vessel_classes(id)
) ENGINE=InnoDB;

CREATE TABLE vessel_positions (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  vessel_id INT NOT NULL,
  latitude DECIMAL(9,6) NOT NULL,
  longitude DECIMAL(9,6) NOT NULL,
  speed_knots DECIMAL(4,1) DEFAULT 0,
  heading_deg SMALLINT DEFAULT 0,
  data_source VARCHAR(60) DEFAULT 'Simulated (demo) — not live AIS',
  recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (vessel_id) REFERENCES vessels(id) ON DELETE CASCADE,
  INDEX idx_pos_vessel_time (vessel_id, recorded_at)
) ENGINE=InnoDB;

-- -------------------------------------------------------------------------
-- ROUTES
-- -------------------------------------------------------------------------
CREATE TABLE routes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  origin_id INT NOT NULL,
  destination_port_id INT NOT NULL,
  commodity_id INT NOT NULL,
  distance_nm INT NOT NULL,
  FOREIGN KEY (origin_id) REFERENCES origins(id),
  FOREIGN KEY (destination_port_id) REFERENCES ports(id),
  FOREIGN KEY (commodity_id) REFERENCES commodities(id),
  UNIQUE KEY uniq_route (origin_id, destination_port_id, commodity_id)
) ENGINE=InnoDB;

-- -------------------------------------------------------------------------
-- MARKET INDICATORS (bunker, economic, demand/supply)
-- -------------------------------------------------------------------------
CREATE TABLE market_indicators (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  indicator_date DATE NOT NULL,
  bunker_price_usd_per_tonne DECIMAL(8,2),
  baltic_dry_index DECIMAL(8,2),
  iron_ore_price_usd_per_tonne DECIMAL(8,2),
  coking_coal_price_usd_per_tonne DECIMAL(8,2),
  demand_index DECIMAL(5,2) DEFAULT 1.00,     -- >1 = above-average demand
  supply_index DECIMAL(5,2) DEFAULT 1.00,
  UNIQUE KEY uniq_indicator_date (indicator_date)
) ENGINE=InnoDB;

CREATE TABLE weather_events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  region VARCHAR(100) NOT NULL,
  event_type ENUM('Cyclone','Storm','Rough Seas','Fog','Clear') DEFAULT 'Clear',
  severity ENUM('Low','Moderate','High','Severe') DEFAULT 'Low',
  affected_port_id INT NULL,
  starts_at DATETIME, ends_at DATETIME,
  FOREIGN KEY (affected_port_id) REFERENCES ports(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- -------------------------------------------------------------------------
-- FREIGHT RATES (historical time series) + FORECASTS
-- -------------------------------------------------------------------------
CREATE TABLE freight_rates (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  route_id INT NOT NULL,
  vessel_class_id INT NOT NULL,
  rate_date DATE NOT NULL,
  rate_usd_per_tonne DECIMAL(8,2) NOT NULL,
  data_source VARCHAR(60) DEFAULT 'Simulated (demo)',
  FOREIGN KEY (route_id) REFERENCES routes(id),
  FOREIGN KEY (vessel_class_id) REFERENCES vessel_classes(id),
  INDEX idx_rates_lookup (route_id, vessel_class_id, rate_date)
) ENGINE=InnoDB;

CREATE TABLE forecast_models (
  id INT AUTO_INCREMENT PRIMARY KEY,
  model_key VARCHAR(40) NOT NULL UNIQUE,     -- e.g. 'weighted_regression_v1', 'arima', 'prophet', 'lstm'
  display_name VARCHAR(80) NOT NULL,
  description VARCHAR(255),
  is_active TINYINT(1) DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE freight_forecasts (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  route_id INT NOT NULL,
  vessel_class_id INT NOT NULL,
  model_id INT NOT NULL,
  horizon_days INT NOT NULL,                  -- 7 / 14 / 30
  current_rate DECIMAL(8,2),
  predicted_rate DECIMAL(8,2) NOT NULL,
  lower_bound DECIMAL(8,2), upper_bound DECIMAL(8,2),
  confidence_pct DECIMAL(5,2),
  market_direction ENUM('Rising','Falling','Stable') DEFAULT 'Stable',
  volatility_pct DECIMAL(5,2),
  is_simulated TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (route_id) REFERENCES routes(id),
  FOREIGN KEY (vessel_class_id) REFERENCES vessel_classes(id),
  FOREIGN KEY (model_id) REFERENCES forecast_models(id)
) ENGINE=InnoDB;

-- -------------------------------------------------------------------------
-- PORT CONSTRAINT VALIDATION LOG (explainable compatibility checks)
-- -------------------------------------------------------------------------
CREATE TABLE port_constraints_log (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  port_id INT NOT NULL,
  vessel_class_id INT NOT NULL,
  draft_ok TINYINT(1), loa_ok TINYINT(1), beam_ok TINYINT(1),
  overall_status ENUM('Compatible','Restricted','Not Compatible') NOT NULL,
  reason VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (port_id) REFERENCES ports(id),
  FOREIGN KEY (vessel_class_id) REFERENCES vessel_classes(id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- -------------------------------------------------------------------------
-- CARGO REQUESTS (Shipper workspace)
-- -------------------------------------------------------------------------
CREATE TABLE cargo_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  shipper_id INT NOT NULL,
  commodity_id INT NOT NULL,
  origin_id INT NOT NULL,
  destination_port_id INT NOT NULL,
  quantity_tonnes INT NOT NULL,
  loading_window_start DATE, loading_window_end DATE,
  delivery_window_start DATE, delivery_window_end DATE,
  contract_duration ENUM('Spot','Short-Term','Medium-Term') DEFAULT 'Spot',
  status ENUM('Draft','Submitted','Under Review','Quoted','Contracted','Rejected','Cancelled') DEFAULT 'Draft',
  recommended_vessel_class_id INT NULL,
  estimated_freight_usd_per_tonne DECIMAL(8,2),
  risk_level ENUM('Low','Moderate','High','Severe') DEFAULT 'Low',
  deleted_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (shipper_id) REFERENCES users(id),
  FOREIGN KEY (commodity_id) REFERENCES commodities(id),
  FOREIGN KEY (origin_id) REFERENCES origins(id),
  FOREIGN KEY (destination_port_id) REFERENCES ports(id),
  FOREIGN KEY (recommended_vessel_class_id) REFERENCES vessel_classes(id)
) ENGINE=InnoDB;

-- -------------------------------------------------------------------------
-- CONTRACTS + VOYAGES (multi-voyage COA support)
-- -------------------------------------------------------------------------
CREATE TABLE contracts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cargo_request_id INT NULL,
  created_by INT NOT NULL,
  route_id INT NOT NULL,
  vessel_class_id INT NOT NULL,
  contract_type ENUM('Spot','Short-Term (COA)','Medium-Term (COA)') NOT NULL,
  num_voyages INT DEFAULT 1,
  cargo_volume_tonnes_per_voyage INT NOT NULL,
  target_rate_usd_per_tonne DECIMAL(8,2) NOT NULL,
  forecast_rate_low DECIMAL(8,2), forecast_rate_high DECIMAL(8,2),
  total_exposure_usd DECIMAL(14,2),
  estimated_savings_usd DECIMAL(14,2) DEFAULT 0,
  status ENUM('Draft','Active','Completed','Cancelled') DEFAULT 'Draft',
  start_date DATE NOT NULL, end_date DATE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (cargo_request_id) REFERENCES cargo_requests(id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES users(id),
  FOREIGN KEY (route_id) REFERENCES routes(id),
  FOREIGN KEY (vessel_class_id) REFERENCES vessel_classes(id)
) ENGINE=InnoDB;

CREATE TABLE contract_voyages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  contract_id INT NOT NULL,
  voyage_number INT NOT NULL,
  vessel_id INT NULL,
  planned_load_date DATE, planned_delivery_date DATE,
  status ENUM('Planned','Loading','In Transit','Discharging','Completed','Cancelled') DEFAULT 'Planned',
  FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
  FOREIGN KEY (vessel_id) REFERENCES vessels(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- -------------------------------------------------------------------------
-- IDLE / RISK / ALERTS
-- -------------------------------------------------------------------------
CREATE TABLE idle_scenarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vessel_id INT NOT NULL,
  cause ENUM('Low Demand','Port Congestion','Contract Gap','Delayed Loading','Delayed Discharge','Route Imbalance') NOT NULL,
  estimated_idle_days DECIMAL(5,1) NOT NULL,
  estimated_idle_cost_usd DECIMAL(12,2) NOT NULL,
  suggested_action VARCHAR(255),
  alternative_revenue_usd DECIMAL(12,2) DEFAULT 0,
  status ENUM('Open','Mitigated','Closed') DEFAULT 'Open',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (vessel_id) REFERENCES vessels(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE risk_events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  risk_category ENUM('Freight','Port','Vessel','Route') NOT NULL,
  route_id INT NULL, port_id INT NULL, vessel_id INT NULL,
  risk_score DECIMAL(5,1) NOT NULL,           -- 0-100
  risk_level ENUM('Low','Moderate','High','Severe') NOT NULL,
  description VARCHAR(255) NOT NULL,
  recommended_action VARCHAR(255),
  is_resolved TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (route_id) REFERENCES routes(id) ON DELETE SET NULL,
  FOREIGN KEY (port_id) REFERENCES ports(id) ON DELETE SET NULL,
  FOREIGN KEY (vessel_id) REFERENCES vessels(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE alerts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  alert_type ENUM('Freight Volatility','Port Congestion','Vessel Delay','Contract Opportunity','Route Disruption') NOT NULL,
  severity ENUM('Low','Moderate','High','Severe') NOT NULL,
  route_id INT NULL, port_id INT NULL, vessel_id INT NULL,
  message VARCHAR(255) NOT NULL,
  is_resolved TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (route_id) REFERENCES routes(id) ON DELETE SET NULL,
  FOREIGN KEY (port_id) REFERENCES ports(id) ON DELETE SET NULL,
  FOREIGN KEY (vessel_id) REFERENCES vessels(id) ON DELETE SET NULL
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;
