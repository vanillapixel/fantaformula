# Fantasy Formula 1 - Database Structure Plan

## Overview

File-based SQLite database for a Fantasy Formula 1 application supporting 6 beta users. Designed for easy migration to a more scalable stack later.

## Tech Stack (Beta)

- **Database**: SQLite (file-based)
- **Backend**: PHP + SQLite PDO
- **Frontend**: React with API polling for real-time updates
- **Hosting**: Existing PHP hosting

## Migration Path

When scaling beyond beta, migrate to:

- **Hardware**: Zimablade mini PC
- **Database**: PostgreSQL/MySQL
- **Backend**: Node.js + Express
- **Frontend**: Same React app (no changes needed)

---

## Database Schema (13 Tables)

### Core Tables

#### 1. **users**

```sql
- id (INTEGER PRIMARY KEY)
- username (TEXT UNIQUE NOT NULL)
- email (TEXT UNIQUE NOT NULL)
- password_hash (TEXT NOT NULL)
- created_at (DATETIME DEFAULT CURRENT_TIMESTAMP)
- updated_at (DATETIME DEFAULT CURRENT_TIMESTAMP)
```

#### 2. **seasons**

```sql
- id (INTEGER PRIMARY KEY)
- year (INTEGER UNIQUE NOT NULL)
- status (TEXT DEFAULT 'upcoming') -- upcoming, active, completed
- created_at (DATETIME DEFAULT CURRENT_TIMESTAMP)
```

#### 3. **season_rules**

_Flexible rules system allowing different seasons to have different scoring and budget rules_

```sql
- id (INTEGER PRIMARY KEY)
- season_id (INTEGER REFERENCES seasons(id))
- default_budget (DECIMAL(6,2) DEFAULT 250.0) -- default budget per race
- last_to_top10_points (DECIMAL(4,2) DEFAULT 1.0)
- top10_to_top5_points (DECIMAL(4,2) DEFAULT 2.0)
- top4_points (DECIMAL(4,2) DEFAULT 3.0)
- position_loss_multiplier (DECIMAL(4,2) DEFAULT -0.5)
- bonus_cap_value (DECIMAL(6,2) DEFAULT 50.0)
- malus_cap_value (DECIMAL(6,2) DEFAULT -30.0)
- race_winner_points (DECIMAL(4,2) DEFAULT 25.0)
- fastest_lap_points (DECIMAL(4,2) DEFAULT 1.0)
- drs_multiplier_bonus (DECIMAL(4,2) DEFAULT 1.2)
- drs_cap_value (DECIMAL(6,2) DEFAULT 30.0)
- max_drivers_count (INTEGER DEFAULT 6)
- user_position_points (JSON DEFAULT '[25,18,14,10,6,3,1]') -- F1-style points
- UNIQUE(season_id)
```

#### 4. **championships**

_User-created fantasy leagues within a season_

```sql
- id (INTEGER PRIMARY KEY)
- name (TEXT NOT NULL)
- season_id (INTEGER REFERENCES seasons(id))
- settings (JSON) -- flexible championship-specific rules
- status (TEXT DEFAULT 'upcoming') -- upcoming, active, completed
- max_participants (INTEGER)
- is_public (BOOLEAN DEFAULT true)
- created_at (DATETIME DEFAULT CURRENT_TIMESTAMP)
```

#### 5. **championship_admins**

_Multiple admins per championship support_

```sql
- id (INTEGER PRIMARY KEY)
- championship_id (INTEGER REFERENCES championships(id))
- user_id (INTEGER REFERENCES users(id))
- created_at (DATETIME DEFAULT CURRENT_TIMESTAMP)
- UNIQUE(championship_id, user_id)
```

#### 6. **races**

_F1 races with flexible budget per race_

```sql
- id (INTEGER PRIMARY KEY)
- season_id (INTEGER REFERENCES seasons(id))
- name (TEXT NOT NULL)
- track_name (TEXT NOT NULL)
- country (TEXT NOT NULL)
- race_date (DATETIME NOT NULL)
- qualifying_date (DATETIME NOT NULL)
- round_number (INTEGER NOT NULL)
- budget_override (DECIMAL(6,2)) -- NULL means use default from season_rules
```

#### 7. **constructors**

_F1 constructors with visual assets (renamed from f1_teams)_

```sql
- id (INTEGER PRIMARY KEY)
- season_id (INTEGER REFERENCES seasons(id))
- name (TEXT NOT NULL)
- short_name (TEXT)
- color_primary (TEXT) -- hex color for UI
- picture_url (TEXT) -- team picture URL
- logo_url (TEXT) -- team logo URL
```

#### 8. **drivers**

_F1 drivers with visual assets_

```sql
- id (INTEGER PRIMARY KEY)
- first_name (TEXT NOT NULL)
- last_name (TEXT NOT NULL)
- driver_number (INTEGER)
- driver_code (TEXT) -- e.g., "VER", "HAM"
- nationality (TEXT)
- picture_url (TEXT) -- driver picture URL
- logo_url (TEXT) -- driver number logo URL
- active (BOOLEAN DEFAULT true)
```

#### 9. **race_drivers**

_Links drivers to specific races with AI-calculated prices_

```sql
- id (INTEGER PRIMARY KEY)
- race_id (INTEGER REFERENCES races(id))
- driver_id (INTEGER REFERENCES drivers(id))
- constructor_id (INTEGER REFERENCES constructors(id))
- price (DECIMAL(6,2) NOT NULL) -- AI-calculated before qualifying
- ai_calculated_at (DATETIME)
- UNIQUE(race_id, driver_id)
```

#### 10. **championship_participants**

_Users participating in championships_

```sql
- id (INTEGER PRIMARY KEY)
- championship_id (INTEGER REFERENCES championships(id))
- user_id (INTEGER REFERENCES users(id))
- joined_at (DATETIME DEFAULT CURRENT_TIMESTAMP)
- UNIQUE(championship_id, user_id)
```

### Results & Performance Tables

#### 11. **race_results**

_Actual F1 race results for points calculation_

```sql
- id (INTEGER PRIMARY KEY)
- race_id (INTEGER REFERENCES races(id))
- driver_id (INTEGER REFERENCES drivers(id))
- qualifying_position (INTEGER)
- race_position (INTEGER)
- fastest_lap (BOOLEAN DEFAULT false)
- dnf (BOOLEAN DEFAULT false) -- Did Not Finish
- points_earned (DECIMAL(6,2)) -- calculated fantasy points
- calculated_at (DATETIME)
- UNIQUE(race_id, driver_id)
```

### Fantasy Lineup Tables

#### 12. **user_race_lineups**

_User's fantasy lineup setup for each race (renamed from user_race_teams)_

```sql
- id (INTEGER PRIMARY KEY)
- user_id (INTEGER REFERENCES users(id))
- race_id (INTEGER REFERENCES races(id))
- championship_id (INTEGER REFERENCES championships(id))
- drs_enabled (BOOLEAN DEFAULT true)
- total_cost (DECIMAL(6,2))
- total_points (DECIMAL(6,2)) -- calculated after race
- submitted_at (DATETIME)
- UNIQUE(user_id, race_id, championship_id)
```

#### 13. **user_selected_drivers**

_Individual driver selections for each user's race lineup_

```sql
- id (INTEGER PRIMARY KEY)
- user_race_lineup_id (INTEGER REFERENCES user_race_lineups(id))
- race_driver_id (INTEGER REFERENCES race_drivers(id))
- UNIQUE(user_race_lineup_id, race_driver_id) -- same driver can't be selected twice
```

---

## Key Design Features

### Flexible Scoring System

The `season_rules` table supports complex position gain/loss calculations:

- **Position gains**: Different point values for different position ranges
- **Position losses**: Uniform malus with multiplier
- **Caps**: Maximum bonus/malus values
- **Special bonuses**: Race winner, fastest lap, DRS multiplier

### Budget System

- **Default budget**: Set per season (default 250)
- **Race overrides**: Custom budget per race when needed
- **Flexible pricing**: AI-calculated driver prices before each qualifying

### Multi-Admin Support

Championships can have multiple administrators through the `championship_admins` table.

### Real-time Ready

Structure supports frontend polling for:

- Live leaderboards
- Race result updates
- Budget/team changes
- Championship standings

### Migration Ready

Clean relational structure easily converts to PostgreSQL/MySQL when scaling.
