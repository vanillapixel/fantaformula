# Fantasy Formula 1 - Development Guide

## Current State Summary

### ✅ Backend (PHP 8.3 + SQLite)

- **Container**: Running on port 8765
- **Database**: SQLite with complete schema (users, championships, races, drivers, constructors, lineups, results)
- **Authentication**: JWT with super admin roles (`is_super_admin` column)
- **API Endpoints**: Complete REST API with OpenAPI docs
- **Documentation**: Available at http://localhost:8765/backend/api/docs/

### ✅ Frontend (React 19 + Tailwind CSS)

- **Container**: Running on port 3000
- **Theme**: Dark theme (#1B2021 bg, #e62d2d accent, Titillium Web font)
- **Authentication**: Complete login/register flow with React Context
- **API Integration**: Axios client with JWT interceptors
- **Components**: AuthPages, LoginPage, RegisterPage, Dashboard

## Next Development Priorities

### 1. Championships Management Page

**Purpose**: List, join, create championships

**API Endpoints Ready**:

```javascript
championshipsAPI.getAll(); // GET /championships/index.php
championshipsAPI.create(data); // POST /championships/index.php
```

**Components Needed**:

```jsx
src/components/championships/
├── ChampionshipsPage.js           // Main page component
├── ChampionshipsList.js           // List all championships
├── ChampionshipCard.js            // Individual championship card
├── CreateChampionshipModal.js     // Modal for creating new championship
└── JoinChampionshipButton.js      // Join/leave functionality
```

**Features to Implement**:

- Display all public championships
- Join/leave championship functionality
- Create new championship (authenticated users)
- Search and filter championships
- Show participant count and admin info

### 2. Race Calendar & Lineup Selection

**Purpose**: View races, select a driver lineup within budget

**API Endpoints Ready**:

```javascript
racesAPI.getAll(season); // GET /races/all.php
driversAPI.getAll(raceId); // GET /drivers/all.php?race_id=X
lineupsAPI.saveLineup(data); // POST /lineups/index.php
lineupsAPI.getLineup(raceId, champId); // GET /lineups/index.php
```

**Components Needed**:

```jsx
src/components/races/
├── RacesPage.js                   // Race calendar view
├── RaceCard.js                    // Individual race card
├── RaceDetails.js                 // Detailed race view
└── LineupSelectionPage.js         // Driver lineup selection interface

src/components/lineupSelection/
├── DriverPicker.js                // Driver selection grid
├── BudgetCalculator.js            // Real-time budget tracking
├── LineupSummary.js               // Selected lineup overview
└── SaveLineupButton.js            // Submit lineup
```

**Features to Implement**:

- Race calendar with status indicators (upcoming/qualifying/completed)
- Driver selection with pricing and constructor affiliations
- Budget constraints and validation
- Save/update lineup selections
- Visual feedback for lineup composition

### 3. Results & Leaderboards

**Purpose**: View race results and championship standings

**API Endpoints Ready**:

```javascript
resultsAPI.getResults(raceId); // GET /results/index.php?race_id=X
resultsAPI.submitResults(data); // POST /results/index.php (super admin only)
```

**Components Needed**:

```jsx
src/components/results/
├── ResultsPage.js                 // Race results display
├── RaceResultsTable.js            // Formatted results table
├── ChampionshipLeaderboard.js     // Championship standings
└── UserPointsBreakdown.js         // Individual scoring details

src/components/admin/
├── AdminPanel.js                  // Super admin dashboard
├── ResultsSubmissionForm.js       // Submit race results
└── ResultsValidation.js           // Validate result data
```

**Features to Implement**:

- Display race results with driver positions
- Show fantasy points calculation
- Championship leaderboards
- Super admin: Submit race results
- Points breakdown and scoring transparency

## Technical Setup Guide

### Project Structure

```
frontend/src/
├── components/
│   ├── auth/              ✅ Complete (Login, Register, Dashboard)
│   ├── championships/     🚧 Next priority
│   ├── races/            🚧 Next priority
│   ├── teamSelection/    🚧 Next priority
│   ├── results/          🚧 Future
│   ├── admin/            🚧 Future
│   └── common/           🚧 Shared components (LoadingSpinner, Modal, etc.)
├── contexts/
│   └── AuthContext.js    ✅ Complete
├── services/
│   └── api.js            ✅ Complete
├── hooks/                🚧 Custom React hooks
└── utils/                🚧 Helper functions
```

### Available API Client

```javascript
// src/services/api.js - Already implemented
import {
  authAPI,
  championshipsAPI,
  racesAPI,
  driversAPI,
  lineupsAPI,
  resultsAPI,
} from "../services/api";
```

### Authentication Context

```javascript
// Available throughout app
const {
  user, // User object with is_super_admin flag
  isAuthenticated, // Boolean auth state
  login, // Login function
  logout, // Logout function
  isLoading, // Loading state
  error, // Error messages
} = useAuth();
```

### Routing Setup Required

```bash
# React Router already installed, needs setup in App.js
```

```jsx
// src/App.js - Add routing structure
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';

// Proposed routes:
/                    → Dashboard (authenticated) or AuthPages
/championships       → Championships listing and management
/races              → Race calendar
/races/:id/lineup   → Lineup selection for specific race
/results            → Results and leaderboards
/admin              → Super admin panel (protected route)
```

## Development Commands

### Container Management

```bash
# Start full stack
cd /path/to/fantaformula
docker compose up -d

# Service URLs
# Backend API: http://localhost:8765
# Frontend App: http://localhost:3000
# API Documentation: http://localhost:8765/backend/api/docs/

# Container logs
docker compose logs frontend
docker compose logs fantaformula

# Rebuild after changes
docker compose build frontend
docker compose restart frontend
```

### Database Management

```bash
# Access database directly
docker exec -it fantaformula-app sqlite3 database/fantaformula.db

# Create super admin user
INSERT INTO users (username, email, password_hash, is_super_admin)
VALUES ('admin', 'admin@ff1.com', '$2y$10$[hash]', 1);

# Check table structure
.schema users
.schema championships
```

## UI/UX Design System

### Color Palette

```css
/* Primary Colors */
--bg-primary: #1b2021; /* Main background */
--accent-primary: #e62d2d; /* Red accent color */
--text-primary: #ffffff; /* Primary text */
--text-secondary: #9ca3af; /* Gray text */

/* Component Colors */
--card-bg: #1f2937; /* bg-gray-800 */
--card-border: #374151; /* border-gray-700 */
--input-bg: #1f2937; /* bg-gray-800 */
--input-border: #4b5563; /* border-gray-600 */
```

### Component Patterns

```jsx
// Standard Card
<div className="bg-gray-800 rounded-lg p-6 border border-gray-700">

// Form Input
<input className="w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-colors" />

// Primary Button
<button className="btn-primary">Action</button>

// Secondary Button
<button className="bg-gray-700 hover:bg-gray-600 text-white py-2 px-4 rounded-lg transition-colors">Action</button>

// Typography
<h1 className="text-2xl font-bold text-white font-titillium">Header</h1>
<p className="text-gray-400">Body text</p>
```

### Layout Structure

```jsx
// Page Layout Pattern
<div className="min-h-screen bg-dark-100">
  {/* Header */}
  <header className="bg-gray-900 border-b border-gray-700">
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      {/* Navigation content */}
    </div>
  </header>

  {/* Main Content */}
  <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    {/* Page content */}
  </main>
</div>
```

## Data Flow Architecture

### Championship Flow

1. **List Championships** → `GET /championships/index.php`
2. **Join Championship** → `POST /championships/join.php` (needs implementation)
3. **Create Championship** → `POST /championships/index.php`

### Race & Team Selection Flow

1. **Load Races** → `GET /races/all.php?season=2025`
2. **Load Drivers** → `GET /drivers/all.php?race_id=X`

### Results Flow

1. **Load Race Results** → `GET /results/index.php?race_id=X`
2. **Submit Results** (Admin) → `POST /results/index.php`
3. **View Leaderboards** → Aggregate from teams and results data

## Security Considerations

### Authentication

- JWT tokens stored in localStorage with 24h expiry
- Automatic token refresh on API calls
- Protected routes based on authentication state
- Super admin role validation for sensitive operations

### API Security

- CORS configured for localhost development
- Input validation on all endpoints
- SQL injection protection via prepared statements
- Rate limiting should be added for production

## Testing Strategy

### Manual Testing Checklist

- [ ] User registration and login flow
- [ ] Championship creation and joining
- [ ] Race calendar navigation
- [ ] Driver selection within budget constraints
- [ ] Team saving and loading
- [ ] Results display and scoring
- [ ] Super admin result submission

### Test Data Setup

```sql
-- Create test championship
INSERT INTO championships (name, season_id, max_participants, is_public)
VALUES ('Test Championship 2025', 1, 10, 1);

-- Create test race with drivers
-- (Use existing schema.sql sample data)
```

## Deployment Considerations

### Environment Variables

```bash
# Backend (.env)
DB_PATH=/app/database/fantaformula.db
JWT_SECRET=your-production-secret
CORS_ALLOWED_ORIGINS=https://yourdomain.com

# Frontend (.env)
REACT_APP_API_BASE_URL=https://api.yourdomain.com
```

### Production Optimizations

- [ ] Switch from SQLite to PostgreSQL/MySQL
- [ ] Add Redis for session management
- [ ] Implement proper logging
- [ ] Add rate limiting and security headers
- [ ] Optimize Docker images for production
- [ ] Set up SSL/TLS certificates
- [ ] Configure CDN for static assets

## Next Session Priorities

1. **Set up React Router** in App.js with protected routes
2. **Create ChampionshipsPage** component with list and create functionality
3. **Implement RacesPage** with calendar view and race selection
4. **Build TeamSelectionPage** with driver picker and budget validation
5. **Add navigation header** component for authenticated users

This guide provides complete context for continuing development in a fresh session. Focus on championships management as the next logical step after authentication.
