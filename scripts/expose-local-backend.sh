#!/bin/bash

echo "🌐 Exposing Local Backend for Cloudflare Frontend"
echo "==============================================="

# Check if ngrok is available
if command -v ngrok >/dev/null 2>&1; then
    echo "✅ Using ngrok to expose local backend"
    
    # Start ngrok for backend
    echo "🚀 Starting ngrok for backend (port 8080)..."
    ngrok http 8080 &
    NGROK_BACKEND_PID=$!
    
    # Start ngrok for IROH bridge  
    echo "🚀 Starting ngrok for IROH bridge (port 9090)..."
    ngrok http 9090 &
    NGROK_IROH_PID=$!
    
    sleep 3
    
    # Get the ngrok URLs
    BACKEND_URL=$(curl -s localhost:4040/api/tunnels | jq -r '.tunnels[0].public_url')
    IROH_URL=$(curl -s localhost:4041/api/tunnels | jq -r '.tunnels[0].public_url')
    
    echo ""
    echo "🎉 Local services exposed!"
    echo "========================"
    echo "Backend URL: $BACKEND_URL"
    echo "IROH URL: $IROH_URL"
    echo ""
    echo "📋 Cloudflare Environment Variables:"
    echo "NUXT_PUBLIC_API_BASE_URL=$BACKEND_URL/api"
    echo "NUXT_PUBLIC_IROH_BRIDGE_URL=$IROH_URL"
    echo "NUXT_PUBLIC_IROH_ENABLED=true"
    echo ""
    echo "🔧 Next Steps:"
    echo "1. Set these environment variables in your Cloudflare Pages deployment"
    echo "2. Redeploy your Cloudflare frontend"
    echo "3. Your Cloudflare frontend will now connect to your local backend!"
    
    # Cleanup function
    cleanup() {
        echo ""
        echo "🛑 Stopping ngrok tunnels..."
        kill $NGROK_BACKEND_PID $NGROK_IROH_PID 2>/dev/null
        echo "✅ Cleanup complete"
        exit 0
    }
    
    trap cleanup EXIT INT TERM
    
    echo ""
    echo "Press Ctrl+C to stop tunnels..."
    wait
    
elif command -v cloudflared >/dev/null 2>&1; then
    echo "✅ Using cloudflared tunnel to expose local backend"
    
    # Start cloudflare tunnel for backend
    echo "🚀 Starting cloudflare tunnel for backend (port 8080)..."
    cloudflared tunnel --url http://localhost:8080 &
    CF_BACKEND_PID=$!
    
    # Start cloudflare tunnel for IROH bridge
    echo "🚀 Starting cloudflare tunnel for IROH bridge (port 9090)..."
    cloudflared tunnel --url http://localhost:9090 &
    CF_IROH_PID=$!
    
    echo ""
    echo "🎉 Cloudflare tunnels started!"
    echo "Check the output above for the tunnel URLs"
    echo ""
    echo "Use those URLs as:"
    echo "NUXT_PUBLIC_API_BASE_URL=https://[tunnel-url]/api"
    echo "NUXT_PUBLIC_IROH_BRIDGE_URL=https://[tunnel-url]"
    
    # Cleanup function
    cleanup() {
        echo ""
        echo "🛑 Stopping cloudflare tunnels..."
        kill $CF_BACKEND_PID $CF_IROH_PID 2>/dev/null
        echo "✅ Cleanup complete"
        exit 0
    }
    
    trap cleanup EXIT INT TERM
    
    echo ""
    echo "Press Ctrl+C to stop tunnels..."
    wait
    
else
    echo "❌ Neither ngrok nor cloudflared found"
    echo ""
    echo "Install one of these tools to expose your local backend:"
    echo ""
    echo "🔗 ngrok:"
    echo "  curl -s https://ngrok-agent.s3.amazonaws.com/ngrok.asc | sudo tee /etc/apt/trusted.gpg.d/ngrok.asc >/dev/null"
    echo "  echo 'deb https://ngrok-agent.s3.amazonaws.com buster main' | sudo tee /etc/apt/sources.list.d/ngrok.list"
    echo "  sudo apt update && sudo apt install ngrok"
    echo ""
    echo "🔗 cloudflared:"
    echo "  wget -q https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64.deb"
    echo "  sudo dpkg -i cloudflared-linux-amd64.deb"
    echo ""
    echo "📋 Manual Alternative:"
    echo "Deploy your backend to a cloud service and use that URL instead:"
    echo "- Heroku, Railway, Render, DigitalOcean, AWS, etc."
    exit 1
fi