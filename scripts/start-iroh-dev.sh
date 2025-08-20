#!/bin/bash

echo "🚀 Starting CIRX Swap Platform with IROH Networking"
echo "=================================================="

# Set environment variables for IROH
export IROH_ENABLED=true
export IROH_BRIDGE_URL=http://localhost:9090
export NUXT_PUBLIC_IROH_ENABLED=true
export NUXT_PUBLIC_IROH_BRIDGE_URL=http://localhost:9090

echo "✅ Environment configured for IROH networking"

# Function to check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Check dependencies
echo "🔍 Checking dependencies..."

if ! command_exists cargo; then
    echo "❌ Rust/Cargo not found. Please install Rust: https://rustup.rs/"
    exit 1
fi

if ! command_exists php; then
    echo "❌ PHP not found. Please install PHP 8.2+"
    exit 1
fi

if ! command_exists npm; then
    echo "❌ Node.js/npm not found. Please install Node.js"
    exit 1
fi

echo "✅ All dependencies available"

# Build IROH bridge
echo "🔨 Building IROH bridge..."
cd iroh-bridge
if cargo build --release; then
    echo "✅ IROH bridge built successfully"
else
    echo "❌ Failed to build IROH bridge"
    exit 1
fi
cd ..

# Start services in the background
echo "🚀 Starting services..."

# Start IROH bridge
echo "Starting IROH bridge on port 9090..."
cd iroh-bridge
cargo run --release -- --listen 0.0.0.0:9090 &
IROH_PID=$!
cd ..

# Wait for IROH bridge to start
sleep 3

# Start backend
echo "Starting PHP backend on port 8080..."
cd backend
php -S localhost:8080 public/index.php &
BACKEND_PID=$!
cd ..

# Wait for backend to start
sleep 2

# Start frontend
echo "Starting Nuxt.js frontend on port 3000..."
cd ui
npm run dev &
FRONTEND_PID=$!
cd ..

# Store PIDs for cleanup
echo $IROH_PID > /tmp/iroh-dev.pid
echo $BACKEND_PID >> /tmp/iroh-dev.pid  
echo $FRONTEND_PID >> /tmp/iroh-dev.pid

echo ""
echo "🎉 All services started!"
echo "======================="
echo "IROH Bridge:  http://localhost:9090/health"
echo "Backend API:  http://localhost:8080/api/v1/health"
echo "Frontend:     http://localhost:3000"
echo ""
echo "Test the integration:"
echo "  ./scripts/test-iroh.sh"
echo ""
echo "Stop all services:"
echo "  ./scripts/stop-iroh-dev.sh"
echo ""
echo "Press Ctrl+C to view logs or stop services"

# Function to cleanup on exit
cleanup() {
    echo ""
    echo "🛑 Stopping services..."
    if [ -f /tmp/iroh-dev.pid ]; then
        while read pid; do
            if kill -0 $pid 2>/dev/null; then
                kill $pid
            fi
        done < /tmp/iroh-dev.pid
        rm -f /tmp/iroh-dev.pid
    fi
    echo "✅ All services stopped"
    exit 0
}

# Set up signal handlers
trap cleanup EXIT INT TERM

# Wait for user input or show logs
echo "Following logs (Ctrl+C to stop):"
echo "================================"

# Simple log following (replace with more sophisticated logging if needed)
tail -f /dev/null