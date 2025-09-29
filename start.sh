#!/bin/bash
# Fantasy Formula 1 - Quick Start Script

echo "🏁 Fantasy Formula 1 - Docker Setup"
echo "===================================="

# Check if Docker is installed
if ! command -v docker &> /dev/null; then
    echo "❌ Docker is not installed. Please install Docker first."
    echo "   https://docs.docker.com/get-docker/"
    exit 1
fi

# Check if Docker Compose is available
if ! command -v docker-compose &> /dev/null && ! docker compose version &> /dev/null; then
    echo "❌ Docker Compose is not available. Please install Docker Compose."
    exit 1
fi

echo "✅ Docker found!"

# Build and start the application
echo ""
echo "🔨 Building Docker image..."
docker-compose build

echo ""
echo "🚀 Starting Fantasy Formula 1..."
docker-compose up -d

echo ""
echo "⏳ Waiting for application to be ready..."
sleep 10

# Initialize database
echo ""
echo "🗄️ Initializing database..."
curl -s "http://localhost:8765/backend/database/setup.php?setup=confirm" || echo "⚠️  Database setup may have failed - check logs"

echo ""
echo "🎉 Fantasy Formula 1 is ready!"
echo ""
echo "📍 Application URLs:"
echo "   Main App: http://localhost:8765"
echo "   API Info: http://localhost:8765/backend/database/info.php"
echo "   Database Browser: http://localhost:8766 (run: docker-compose --profile tools up -d)"
echo ""
echo "🧪 Test API:"
echo "   Register: curl -X POST http://localhost:8765/backend/api/auth/register -H \"Content-Type: application/json\" -d '{\"username\":\"testuser\",\"email\":\"test@example.com\",\"password\":\"password123\"}'"
echo ""
echo "📋 Management:"
echo "   View logs: docker-compose logs -f"
echo "   Stop app:  docker-compose down"
echo "   Restart:   docker-compose restart"
