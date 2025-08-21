#!/bin/bash

echo "☁️ Deploy Backend to Cloud for Cloudflare Frontend"
echo "================================================="

# Check for deployment options
deploy_to_railway() {
    echo "🚂 Deploying to Railway..."
    
    if ! command -v railway >/dev/null 2>&1; then
        echo "📦 Installing Railway CLI..."
        curl -fsSL https://railway.app/install.sh | sh
    fi
    
    echo "🚀 Starting Railway deployment..."
    cd backend
    railway login
    railway init
    railway up
    
    echo "✅ Backend deployed to Railway!"
    echo "Get your backend URL from: https://railway.app/dashboard"
}

deploy_to_render() {
    echo "🎨 Deploy to Render instructions:"
    echo ""
    echo "1. Go to https://render.com"
    echo "2. Connect your GitHub repo"
    echo "3. Create a new Web Service"
    echo "4. Set these configurations:"
    echo "   - Build Command: composer install"
    echo "   - Start Command: php -S 0.0.0.0:\$PORT public/index.php"
    echo "   - Root Directory: backend"
    echo "5. Add environment variables from backend/.env"
    echo "6. Deploy and get your backend URL"
}

deploy_to_heroku() {
    echo "🚀 Deploying to Heroku..."
    
    if ! command -v heroku >/dev/null 2>&1; then
        echo "📦 Please install Heroku CLI first:"
        echo "curl https://cli-assets.heroku.com/install.sh | sh"
        exit 1
    fi
    
    echo "Setting up Heroku deployment..."
    cd backend
    
    # Create Procfile
    echo "web: php -S 0.0.0.0:\$PORT public/index.php" > Procfile
    
    # Create heroku app
    heroku create cirx-swap-backend-$(date +%s)
    
    # Set environment variables
    echo "Setting up environment variables..."
    heroku config:set IROH_ENABLED=true
    heroku config:set IROH_BRIDGE_URL=https://your-iroh-bridge.herokuapp.com
    
    # Deploy
    git add .
    git commit -m "Deploy to Heroku"
    git push heroku main
    
    echo "✅ Backend deployed to Heroku!"
    heroku open
}

deploy_to_digitalocean() {
    echo "🌊 Deploy to DigitalOcean instructions:"
    echo ""
    echo "1. Go to https://cloud.digitalocean.com/apps"
    echo "2. Create a new App"
    echo "3. Connect your GitHub repo"  
    echo "4. Configure:"
    echo "   - Source Directory: backend"
    echo "   - Build Command: composer install"
    echo "   - Run Command: php -S 0.0.0.0:\$PORT public/index.php"
    echo "5. Add environment variables"
    echo "6. Deploy and get your backend URL"
}

echo "Choose your deployment method:"
echo "1) Railway (Recommended - Free tier)"
echo "2) Render (Free tier available)" 
echo "3) Heroku (Paid plans)"
echo "4) DigitalOcean (Paid plans)"
echo "5) Manual instructions for other providers"

read -p "Enter choice (1-5): " choice

case $choice in
    1)
        deploy_to_railway
        ;;
    2) 
        deploy_to_render
        ;;
    3)
        deploy_to_heroku
        ;;
    4)
        deploy_to_digitalocean
        ;;
    5)
        echo ""
        echo "📋 Manual Deployment Instructions:"
        echo "================================="
        echo ""
        echo "For any cloud provider, you need:"
        echo ""
        echo "1. 📁 Deploy the 'backend' folder contents"
        echo "2. 🔧 Set build command: composer install"  
        echo "3. 🚀 Set start command: php -S 0.0.0.0:\$PORT public/index.php"
        echo "4. 🌍 Copy all environment variables from backend/.env"
        echo "5. 🔗 Update IROH_BRIDGE_URL to your deployed IROH bridge URL"
        echo ""
        echo "💡 Popular free options:"
        echo "   - Railway: https://railway.app"
        echo "   - Render: https://render.com"  
        echo "   - Fly.io: https://fly.io"
        echo "   - Vercel: https://vercel.com"
        echo ""
        ;;
    *)
        echo "❌ Invalid choice"
        exit 1
        ;;
esac

echo ""
echo "🔧 After deployment, update your Cloudflare Pages environment:"
echo "============================================================="
echo ""
echo "In Cloudflare Pages settings, add these environment variables:"
echo "NUXT_PUBLIC_API_BASE_URL=https://your-backend-url.com/api"
echo "NUXT_PUBLIC_IROH_BRIDGE_URL=https://your-iroh-bridge-url.com"
echo "NUXT_PUBLIC_IROH_ENABLED=true"
echo ""
echo "Then redeploy your Cloudflare frontend!"
echo ""
echo "🎉 Result: Cloudflare frontend ↔️ Cloud backend with IROH networking!"