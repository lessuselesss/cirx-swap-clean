# Deployment Configuration Guide

## Backend API URL Configuration Issue

### Problem
Deployed frontends show "Connect to backend" because they're trying to reach `localhost:8080` which doesn't exist in their environment.

### Root Cause
The `NUXT_PUBLIC_API_BASE_URL` environment variable is not set for production deployments, so it defaults to `http://localhost:8080/api/v1`.

## Solutions

### Option 1: Deploy Backend Publicly (Recommended)
1. **Deploy your PHP backend** to a publicly accessible URL (e.g., Railway, Heroku, DigitalOcean, etc.)
2. **Set environment variable** in Cloudflare Pages:
   ```
   NUXT_PUBLIC_API_BASE_URL=https://your-backend-domain.com/api/v1
   ```

### Option 2: Use Cloudflare Tunnel (Advanced)
Create a secure tunnel from your local backend to a public URL:
```bash
cloudflare-tunnel --url http://localhost:8080
```

### Option 3: Backend on Same Domain (If Possible)
Deploy backend to same domain as frontend:
```
NUXT_PUBLIC_API_BASE_URL=/api/v1
```

## Cloudflare Pages Environment Configuration

1. Go to Cloudflare Dashboard → Pages → Your Project → Settings → Environment Variables
2. Add production environment variables:
   ```
   NUXT_PUBLIC_API_BASE_URL=https://your-backend-domain.com/api/v1
   NUXT_PUBLIC_TESTNET_MODE=false
   NUXT_PUBLIC_ETHEREUM_NETWORK=mainnet
   NUXT_PUBLIC_ETHEREUM_CHAIN_ID=1
   NUXT_PUBLIC_DEBUG_MODE=false
   ```

## Current Status
- ✅ Fixed API path mismatch (`/api/v1` vs `/api`)
- ✅ Transaction processing working correctly
- ✅ Removed bogus test transaction causing UI issues
- ❌ **Need backend deployed to public URL for production frontends**

## Backend Deployment Options

### Railway (Easy)
```bash
# Connect your GitHub repo to Railway
# It will auto-deploy your backend/
```

### Docker + Cloud Provider
```bash
# Build and push Docker container
cd backend/
docker build -t your-backend .
# Deploy to your preferred cloud provider
```

### Traditional VPS
```bash
# Upload files to server
# Configure Apache/Nginx to serve public/index.php
# Set up SSL certificate
```

## Testing
After setting the environment variable:
1. Trigger a new deployment in Cloudflare Pages
2. Check browser Network tab that API calls go to your backend URL
3. Verify "Connect to backend" message disappears