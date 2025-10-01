Backend now supports environment variables via phpdotenv (if vendor installed) or a minimal fallback parser.

Quick setup:

1. cp backend/.env.example backend/.env
2. Generate a strong JWT secret (openssl rand -base64 48) and set JWT_SECRET=...
3. (Optional) Set DB_PATH to override SQLite location.
4. Build/run docker: docker compose up --build

Production: Always set a unique JWT_SECRET and keep backend/.env out of version control.
