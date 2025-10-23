# Fantasy Formula 1 🏁

A fantasy F1 application where users create championships, select drivers within budget constraints, and compete based on real F1 race results.

## 🚀 Quick Start

### Prerequisites

- Docker & Docker Compose
- VS Code with SQLite extension (recommended)

### Launch Application

```bash
# Clone and navigate to project
cd fantaformula

# Start the application
chmod +x start.sh
./start.sh

# Or manually
docker-compose build
docker-compose up -d
```

### Access Points

- **Main Application**: http://localhost:8765
- **API Documentation**: http://localhost:8765/backend/database/info.php
- **Database Browser** (optional): http://localhost:8766
  ```bash
  docker-compose --profile tools up -d
  ```

## 🏗️ Tech Stack

### Backend

- **Runtime**: PHP 8.3 with Apache
- **Database**: SQLite (file-based)
- **Authentication**: JWT tokens
- **Architecture**: RESTful API

### Frontend (In Development)

- **Framework**: React 18
- **State Management**: Context API
- **HTTP Client**: Axios (planned)
- **Real-time**: API polling

### Infrastructure

- **Containerization**: Docker + Docker Compose
- **Web Server**: Apache with mod_rewrite
- **Development**: Hot reload, CORS enabled

## 📊 Database Schema

### Core Entities

- **Users**: Authentication and profiles
- **Seasons**: F1 seasons (2025, 2026, etc.)
- **Championships**: User-created fantasy leagues
- **Races**: F1 race calendar per season
- **Drivers**: F1 drivers with pricing
- **Teams**: F1 constructors

### Fantasy System

- **User Teams**: Driver selections per race with team association
- **Championship Teams**: Admin-managed teams within championships for team-based competition
- **Scoring Rules**: Flexible point system per season
- **Budget System**: ~250 budget per race (configurable)
- **Driver Selection Rules**:
  - Configurable teammate collaboration (min shared drivers)
  - Configurable player diversity (min different drivers between players)
- **Prediction System**: 
  - Fastest lap, GP winner, and DNF driver predictions
  - Configurable scoring with bonus multipliers
  - Enable/disable predictions per season

### Recent Enhancements (October 2025)

- **✅ Championship Teams**: Teams within championships managed by admins
- **✅ Driver Selection Rules**: Teammate collaboration and player diversity validation
- **✅ Enhanced Race Logic**: Current race detection and improved upcoming race timing
- **✅ Prediction System (Oct 23)**: Comprehensive prediction features with configurable scoring
- **✅ Database Schema**: Updated to 15 tables with team, rule, and prediction support

See `db_structure_plan.md` for complete schema documentation.

## 🔐 Authentication

### JWT Token System

- **Expiry**: 24 hours (configurable)
- **Algorithm**: HS256
- **Storage**: Include in `Authorization: Bearer <token>` header

### Available Endpoints

```bash
# Register new user
POST /backend/api/auth/register
{
  "username": "string",
  "email": "string",
  "password": "string"
}

# Login user
POST /backend/api/auth/login
{
  "username": "string", # or email
  "password": "string"
}

# Get user profile (requires auth)
GET /backend/api/auth/profile

# Update profile (requires auth)
PUT /backend/api/auth/profile
{
  "email": "string",     # optional
  "password": "string"   # optional
}
```

## 🧪 Testing API Endpoints

### 1. Register a User

```bash
curl -X POST http://localhost:8765/backend/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "username": "testuser",
    "email": "test@example.com",
    "password": "password123"
  }'
```

### 2. Login

```bash
curl -X POST http://localhost:8765/backend/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "username": "testuser",
    "password": "password123"
  }'
```

### 3. Access Protected Endpoint

```bash
# Use token from login response
curl -X GET http://localhost:8765/backend/api/auth/profile \
  -H "Authorization: Bearer YOUR_JWT_TOKEN_HERE"
```

## 📁 Project Structure

```
fantaformula/
├── README.md                       # This file
├── db_structure_plan.md           # Database documentation
├── PROJECT_STRUCTURE.md           # Development guide
├── schema.sql                     # Database schema
├── docker-compose.yml             # Container orchestration
├── Dockerfile                     # PHP/Apache container
├── .htaccess                      # URL routing & security
├── start.sh                       # Quick start script
│
├── backend/                       # PHP API
│   ├── config/
│   │   ├── config.php            # App configuration
│   │   └── database.php          # Database connection
│   ├── api/
│   │   └── auth/                 # Authentication endpoints
│   ├── middleware/
│   │   ├── auth.php              # JWT middleware
│   │   └── cors.php              # CORS handling
│   ├── utils/
│   │   └── response.php          # API response helpers
│   └── database/
│       ├── setup.php             # Database initialization
│       ├── info.php              # Database info endpoint
│       └── fantaformula.db       # SQLite database (created)
│
└── frontend/                     # React application
    └── (React app structure)
```

## 🎯 Game Mechanics

### Championship System

- Users create or join championships
- Multiple administrators per championship
- Public/private leagues
- Season-based competition

### Team Selection

- **Budget**: ~250 per race (configurable)
- **Team Size**: 6 drivers (configurable)
- **Strategy**: DRS multiplier option with cap
- **Timing**: Team selection before each qualifying

### Scoring System

- **Position Gains**: Different points for different ranges
  - Last to Top 10: 1.0 points per position
  - Top 10 to Top 5: 2.0 points per position
  - Top 4 positions: 3.0 points per position
- **Position Losses**: -0.5 multiplier per position lost
- **Bonuses**: Race winner (25), fastest lap (1)
- **DRS Bonus**: 1.2x multiplier (capped at 30 points)
- **Caps**: Maximum bonus (50), maximum malus (-30)

### AI Pricing

- Driver prices calculated before each qualifying
- Based on performance, team changes, and market dynamics
- Automatic price updates per race

## 🔧 Development

### Docker Commands

```bash
# Start application
docker-compose up -d

# View logs
docker-compose logs -f

# Restart services
docker-compose restart

# Stop application
docker-compose down

# Rebuild after code changes
docker-compose build --no-cache
```

### Database Management

```bash
# Initialize database
curl "http://localhost:8765/backend/database/setup.php?setup=confirm"

# Check database info
curl "http://localhost:8765/backend/database/info.php"

# Browse with VS Code SQLite extension
# Open: backend/database/fantaformula.db
```

### Configuration

Key settings in `backend/config/config.php`:

- `JWT_SECRET`: Change in production!
- `JWT_EXPIRY`: Token lifetime (24 hours)
- `DEFAULT_BUDGET`: Race budget (250)
- `CORS_ALLOWED_ORIGINS`: Restrict in production

## 🚧 Development Roadmap

### ✅ Completed

- [x] Database schema design (13 tables)
- [x] Docker containerization
- [x] PHP backend foundation
- [x] JWT authentication system
- [x] Basic API endpoints (auth)
- [x] CORS and security middleware
- [x] Database setup automation

### 🔄 In Progress

- [ ] Championship management API
- [ ] Race and driver management API
- [ ] Team selection API

### 📋 Next Steps

- [ ] Scoring calculation system
- [ ] React frontend setup
- [ ] API integration
- [ ] Real-time polling
- [ ] Admin dashboard
- [ ] Production deployment

## 🐛 Troubleshooting

### Common Issues

**Port conflicts:**

```bash
# Check if ports are in use
netstat -tlnp | grep :8765
netstat -tlnp | grep :8766

# Change ports in docker-compose.yml if needed
```

**Database not initializing:**

```bash
# Check container logs
docker-compose logs fantaformula

# Manual database setup
docker-compose exec fantaformula php backend/database/setup.php
```

**CORS issues in development:**

- Frontend requests should use `http://localhost:8765`
- Check `CORS_ALLOWED_ORIGINS` in config.php

### Performance Tips

- SQLite performs well for < 100 users
- Consider connection pooling for scale
- Use indexes for frequent queries (already included)

## 📄 License

Private project for beta testing.

## 🤝 Contributing

This is currently a private beta project.

---

**Ready to race?** 🏎️ Start your engines with `./start.sh`!
