# Fantasy Formula 1 - Project Structure

## 🏗️ Current Structure

```
fantaformula/
├── README.md                       # App documentation & design
├── db_structure_plan.md           # Database schema documentation
├── schema.sql                     # SQLite database schema
├── package.json                   # React app dependencies
├── .htaccess                      # Apache configuration & routing
│
├── backend/                       # PHP Backend API
│   ├── config/
│   │   ├── config.php            # Main app configuration
│   │   └── database.php          # Database connection & utilities
│   ├── api/
│   │   └── auth/
│   │       ├── register.php      # POST /backend/api/auth/register
│   │       ├── login.php         # POST /backend/api/auth/login
│   │       └── profile.php       # GET/PUT /backend/api/auth/profile
│   ├── middleware/
│   │   ├── cors.php              # CORS handling
│   │   └── auth.php              # JWT authentication
│   ├── utils/
│   │   └── response.php          # Standard API responses
│   └── database/
│       ├── setup.php             # Database initialization
│       └── info.php              # Database information endpoint
│
└── frontend/                     # React Frontend (to be created)
    └── (React app structure)
```

## ✅ What's Ready

### Backend Foundation

- **Database**: SQLite schema with 15 tables (including championship teams)
- **Authentication**: JWT-based auth system
- **API Structure**: RESTful endpoints with CORS support
- **Error Handling**: Standardized JSON responses
- **Security**: Input validation, password hashing

### Working Endpoints

- `POST /backend/api/auth/register` - User registration
- `POST /backend/api/auth/login` - User login
- `GET /backend/api/auth/profile` - Get user profile (requires auth)
- `PUT /backend/api/auth/profile` - Update profile (requires auth)
- `GET /backend/database/info` - Database information

## 🚀 Quick Start

### 1. Database Setup

```bash
# Visit in browser or run via CLI
php backend/database/setup.php
# OR via web: /backend/database/setup.php?setup=confirm
```

### 2. Test API Endpoints

**Register a user:**

```bash
curl -X POST http://your-domain/backend/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"username":"testuser","email":"test@example.com","password":"password123"}'
```

**Login:**

```bash
curl -X POST http://your-domain/backend/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"testuser","password":"password123"}'
```

**Get Profile (use token from login):**

```bash
curl -X GET http://your-domain/backend/api/auth/profile \
  -H "Authorization: Bearer YOUR_JWT_TOKEN_HERE"
```

### 3. Frontend Setup (Next Step)

```bash
cd frontend
npx create-react-app . --template typescript
# Setup API services and components
```

## 🎯 Development Status & Next Steps

### ✅ Recently Completed (October 2025)

- **✅ Database Schema Enhanced**: Updated to 15 tables with championship teams support
- **✅ Championship Teams System**: Admin-managed teams within championships
- **✅ Driver Selection Rules**: Configurable teammate collaboration and player diversity
- **✅ Enhanced Race Logic**: Current vs upcoming race detection with proper timing
- **✅ Migration System**: Applied database migrations for all new features

### Phase 1: Championship Teams API (Next Priority)

- [ ] Championship teams CRUD endpoints (`/backend/api/championships/{id}/teams/`)
- [ ] Team member management (`/backend/api/teams/{id}/members/`)
- [ ] Team assignment and validation APIs

### Phase 2: Enhanced Lineup Validation

- [ ] Driver selection validation with new rules
- [ ] Real-time validation during lineup creation
- [ ] Team-aware lineup constraints

### Phase 3: Frontend Team Management

- [ ] Championship admin team management UI
- [ ] Player assignment interfaces
- [ ] Team-based leaderboards and statistics

### Phase 4: Advanced Features

- [ ] Real-time polling with team updates
- [ ] Team-based scoring calculations
- [ ] Enhanced admin features for team management

## 🛠️ Development Tips

### JWT Tokens

- Tokens expire in 24 hours (configurable in `backend/config/config.php`)
- Include in Authorization header: `Bearer YOUR_TOKEN`
- Tokens contain user info for API use

### Database

- SQLite file: `backend/database/fantaformula.db`
- View/edit with tools like DB Browser for SQLite
- Sample data included for testing

### Error Handling

- All errors return JSON with `success: false`
- HTTP status codes match error types
- Development mode shows detailed errors

### CORS

- Currently allows all origins (`*`) for development
- Restrict in production by updating `CORS_ALLOWED_ORIGINS`

Ready to build the championship and team management endpoints next! 🏁
