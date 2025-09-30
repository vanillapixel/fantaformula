-- Fantasy Formula 1 - SQLite Database Schema
-- File: schema.sql
-- Created for beta testing with 6 users
-- Migration ready for scaling to PostgreSQL/MySQL

-- Enable foreign key constraints
PRAGMA foreign_keys = ON;

-- =====================================================
-- CORE TABLES
-- =====================================================

-- Users table
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    email TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Seasons table (F1 seasons by year)
CREATE TABLE seasons (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    year INTEGER UNIQUE NOT NULL,
    status TEXT DEFAULT 'upcoming' CHECK (status IN ('upcoming', 'active', 'completed')),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Season rules (flexible scoring and budget system)
CREATE TABLE season_rules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    season_id INTEGER NOT NULL,
    default_budget DECIMAL(6,2) DEFAULT 250.0,
    last_to_top10_points DECIMAL(4,2) DEFAULT 1.0,
    top10_to_top5_points DECIMAL(4,2) DEFAULT 2.0,
    top4_points DECIMAL(4,2) DEFAULT 3.0,
    position_loss_multiplier DECIMAL(4,2) DEFAULT -0.5,
    bonus_cap_value DECIMAL(6,2) DEFAULT 50.0,
    malus_cap_value DECIMAL(6,2) DEFAULT -30.0,
    race_winner_points DECIMAL(4,2) DEFAULT 25.0,
    fastest_lap_points DECIMAL(4,2) DEFAULT 1.0,
    drs_multiplier_bonus DECIMAL(4,2) DEFAULT 1.2,
    drs_cap_value DECIMAL(6,2) DEFAULT 30.0,
    max_drivers_count INTEGER DEFAULT 6,
    user_position_points TEXT DEFAULT '[25,18,14,10,6,3,1]', -- JSON array
    FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE,
    UNIQUE(season_id)
);

-- Championships (user-created fantasy leagues)
CREATE TABLE championships (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    season_id INTEGER NOT NULL,
    settings TEXT, -- JSON for flexible championship-specific rules
    status TEXT DEFAULT 'upcoming' CHECK (status IN ('upcoming', 'active', 'completed')),
    max_participants INTEGER,
    is_public BOOLEAN DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE
);

-- Championship admins (multiple admins per championship)
CREATE TABLE championship_admins (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    championship_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (championship_id) REFERENCES championships(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE(championship_id, user_id)
);

-- Races (F1 race calendar)
CREATE TABLE races (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    season_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    track_name TEXT NOT NULL,
    country TEXT NOT NULL,
    race_date DATETIME NOT NULL,
    qualifying_date DATETIME NOT NULL,
    round_number INTEGER NOT NULL,
    budget_override DECIMAL(6,2), -- NULL means use default from season_rules
    FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE
);

-- Constructors (formerly f1_teams)
CREATE TABLE constructors (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    season_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    short_name TEXT,
    color_primary TEXT, -- hex color for UI
    picture_url TEXT,
    logo_url TEXT,
    FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE
);

-- F1 drivers
CREATE TABLE drivers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    first_name TEXT NOT NULL,
    last_name TEXT NOT NULL,
    driver_number INTEGER,
    driver_code TEXT, -- e.g., "VER", "HAM"
    nationality TEXT,
    picture_url TEXT,
    logo_url TEXT, -- driver number logo
    active BOOLEAN DEFAULT 1
);

-- Race drivers (links drivers to races with AI pricing)
CREATE TABLE race_drivers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    race_id INTEGER NOT NULL,
    driver_id INTEGER NOT NULL,
    constructor_id INTEGER NOT NULL,
    price DECIMAL(6,2) NOT NULL,
    ai_calculated_at DATETIME,
    FOREIGN KEY (race_id) REFERENCES races(id) ON DELETE CASCADE,
    FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE,
    FOREIGN KEY (constructor_id) REFERENCES constructors(id) ON DELETE CASCADE,
    UNIQUE(race_id, driver_id)
);

-- Championship participants
CREATE TABLE championship_participants (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    championship_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (championship_id) REFERENCES championships(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE(championship_id, user_id)
);

-- =====================================================
-- RESULTS & PERFORMANCE TABLES
-- =====================================================

-- Race results (actual F1 results for fantasy scoring)
CREATE TABLE race_results (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    race_id INTEGER NOT NULL,
    driver_id INTEGER NOT NULL,
    qualifying_position INTEGER,
    race_position INTEGER,
    fastest_lap BOOLEAN DEFAULT 0,
    dnf BOOLEAN DEFAULT 0, -- Did Not Finish
    points_earned DECIMAL(6,2), -- calculated fantasy points
    calculated_at DATETIME,
    FOREIGN KEY (race_id) REFERENCES races(id) ON DELETE CASCADE,
    FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE,
    UNIQUE(race_id, driver_id)
);

-- =====================================================
-- FANTASY TEAM TABLES
-- =====================================================

-- User race lineups (fantasy lineup setup per race, formerly user_race_teams)
CREATE TABLE user_race_lineups (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    race_id INTEGER NOT NULL,
    championship_id INTEGER NOT NULL,
    drs_enabled BOOLEAN DEFAULT 1,
    total_cost DECIMAL(6,2),
    total_points DECIMAL(6,2), -- calculated after race
    submitted_at DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (race_id) REFERENCES races(id) ON DELETE CASCADE,
    FOREIGN KEY (championship_id) REFERENCES championships(id) ON DELETE CASCADE,
    UNIQUE(user_id, race_id, championship_id)
);

-- User selected drivers (individual driver picks)
CREATE TABLE user_selected_drivers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_race_lineup_id INTEGER NOT NULL,
    race_driver_id INTEGER NOT NULL,
    FOREIGN KEY (user_race_lineup_id) REFERENCES user_race_lineups(id) ON DELETE CASCADE,
    FOREIGN KEY (race_driver_id) REFERENCES race_drivers(id) ON DELETE CASCADE,
    UNIQUE(user_race_lineup_id, race_driver_id) -- same driver can't be selected twice
);

-- =====================================================
-- INDEXES FOR PERFORMANCE
-- =====================================================

-- User indexes
CREATE INDEX idx_users_username ON users(username);
CREATE INDEX idx_users_email ON users(email);

-- Championship indexes
CREATE INDEX idx_championships_season ON championships(season_id);
CREATE INDEX idx_championships_status ON championships(status);
CREATE INDEX idx_championship_participants_championship ON championship_participants(championship_id);
CREATE INDEX idx_championship_participants_user ON championship_participants(user_id);

-- Race indexes
CREATE INDEX idx_races_season ON races(season_id);
CREATE INDEX idx_races_date ON races(race_date);
CREATE INDEX idx_race_drivers_race ON race_drivers(race_id);
CREATE INDEX idx_race_drivers_driver ON race_drivers(driver_id);

-- Results indexes
CREATE INDEX idx_race_results_race ON race_results(race_id);
CREATE INDEX idx_race_results_driver ON race_results(driver_id);

-- Fantasy lineup indexes
CREATE INDEX idx_user_race_lineups_user ON user_race_lineups(user_id);
CREATE INDEX idx_user_race_lineups_race ON user_race_lineups(race_id);
CREATE INDEX idx_user_race_lineups_championship ON user_race_lineups(championship_id);
CREATE INDEX idx_user_selected_drivers_lineup ON user_selected_drivers(user_race_lineup_id);

-- =====================================================
-- SAMPLE DATA FOR TESTING
-- =====================================================

-- Insert 2025 F1 season
INSERT INTO seasons (year, status) VALUES (2025, 'active');

-- Insert season rules for 2025
INSERT INTO season_rules (season_id) VALUES (1);

-- Insert test users
INSERT INTO users (username, email, password_hash) VALUES 
('testuser1', 'test1@example.com', '$2y$10$example_hash_1'),
('testuser2', 'test2@example.com', '$2y$10$example_hash_2'),
('admin', 'admin@example.com', '$2y$10$example_hash_admin');

-- Insert test championship
INSERT INTO championships (name, season_id, max_participants, is_public) VALUES 
('Beta Test Championship', 1, 6, 1);

-- Make admin user the championship admin
INSERT INTO championship_admins (championship_id, user_id) VALUES (1, 3);

-- Insert test race (Bahrain GP 2025)
INSERT INTO races (season_id, name, track_name, country, race_date, qualifying_date, round_number) VALUES 
(1, 'Bahrain Grand Prix', 'Bahrain International Circuit', 'Bahrain', '2025-03-16 15:00:00', '2025-03-15 15:00:00', 1);

-- Insert test constructors (simplified)
INSERT INTO constructors (season_id, name, short_name, color_primary) VALUES 
(1, 'Red Bull Racing', 'RBR', '#0600EF'),
(1, 'Mercedes', 'MER', '#00D2BE'),
(1, 'Ferrari', 'FER', '#DC0000');

-- Insert test drivers
INSERT INTO drivers (first_name, last_name, driver_number, driver_code, nationality) VALUES 
('Max', 'Verstappen', 1, 'VER', 'Dutch'),
('Lewis', 'Hamilton', 44, 'HAM', 'British'),
('Charles', 'Leclerc', 16, 'LEC', 'Monégasque');

-- Link drivers to race with test prices
INSERT INTO race_drivers (race_id, driver_id, constructor_id, price, ai_calculated_at) VALUES 
(1, 1, 1, 45.50, CURRENT_TIMESTAMP), -- Verstappen at Red Bull
(1, 2, 2, 42.00, CURRENT_TIMESTAMP), -- Hamilton at Mercedes  
(1, 3, 3, 38.75, CURRENT_TIMESTAMP); -- Leclerc at Ferrari
