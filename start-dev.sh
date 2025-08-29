#!/bin/bash

echo "🚀 Starting CIRX Swap Platform Development Environment"
echo "====================================================="

# Function to check if port is in use
check_port() {
    nc -z localhost $1 2>/dev/null
    return $?
}

# Function to cleanup on exit
cleanup() {
    echo ""
    echo "🛑 Stopping services..."
    
    # Kill backend if we started it
    if [ ! -z "$BACKEND_PID" ]; then
        kill $BACKEND_PID 2>/dev/null
        echo "✅ Backend stopped"
    fi
    
    # Kill frontend if we started it
    if [ ! -z "$FRONTEND_PID" ]; then
        kill $FRONTEND_PID 2>/dev/null
        echo "✅ Frontend stopped"
    fi
    
    exit 0
}

# Set up signal handlers
trap cleanup EXIT INT TERM

# Check if backend is already running
if check_port 18423; then
    echo "✅ Backend already running on port 18423"
else
    echo "🚀 Starting PHP backend on port 18423..."
    cd backend
    nix run nixpkgs#php -- -S localhost:18423 public/index.php &
    BACKEND_PID=$!
    cd ..
    
    # Wait for backend to be ready
    attempts=0
    while [ $attempts -lt 30 ]; do
        if check_port 18423; then
            echo "✅ Backend is ready"
            break
        fi
        sleep 1
        attempts=$((attempts + 1))
    done
    
    if [ $attempts -eq 30 ]; then
        echo "❌ Backend failed to start"
        exit 1
    fi
fi

# Check if frontend is already running
if check_port 3000; then
    echo "✅ Frontend already running on port 3000"
else
    echo "🚀 Starting Nuxt frontend on port 3000..."
    cd ui
    npm run dev &
    FRONTEND_PID=$!
    cd ..
    
    # Wait for frontend to be ready
    attempts=0
    while [ $attempts -lt 30 ]; do
        if check_port 3000; then
            echo "✅ Frontend is ready"
            break
        fi
        sleep 1
        attempts=$((attempts + 1))
    done
fi

echo ""
echo "🎉 Development environment ready!"
echo "================================="
echo "Frontend:     http://localhost:3000"
echo "Backend API:  http://localhost:18423/api/v1"
echo ""
echo "Press Ctrl+C to stop all services"
echo ""

# Keep script running
while true; do
    sleep 1
done