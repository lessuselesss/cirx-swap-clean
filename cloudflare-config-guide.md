# Cloudflare Frontend → Local Backend Setup Guide

## Quick Setup Instructions

### 1. Start Your Local Services

```bash
# Terminal 1: Start enhanced IROH bridge
node scripts/enhanced-iroh-bridge.js

# Terminal 2: Start PHP backend  
cd backend && php -S localhost:8080 public/index.php

# Terminal 3: Expose via tunnel
./scripts/expose-local-backend.sh
```

### 2. Update Cloudflare Pages Environment Variables

After running the tunnel script, you'll get URLs like:
- Backend: `https://abc123.ngrok-free.app` 
- IROH Bridge: `https://def456.ngrok-free.app`

Add these to your Cloudflare Pages environment variables:

```bash
NUXT_PUBLIC_API_BASE_URL=https://abc123.ngrok-free.app/api
NUXT_PUBLIC_IROH_BRIDGE_URL=https://def456.ngrok-free.app  
NUXT_PUBLIC_IROH_ENABLED=true
```

### 3. Redeploy Cloudflare Pages

After setting environment variables, trigger a new deployment.

## Alternative: Deploy Backend to Cloud

If you prefer not to use tunnels, run:

```bash
./scripts/deploy-backend-cloud.sh
```

Choose from Railway, Render, Heroku, or DigitalOcean.

## CORS Configuration Updated

Your backend now accepts requests from:
- `localhost:3000` (local development)
- `*.ngrok-free.app` (ngrok tunnels)
- `*.trycloudflare.com` (Cloudflare tunnels) 
- `*.pages.dev` (Cloudflare Pages domains)

## Testing the Connection

Once configured, test with:

```bash
curl https://your-cloudflare-app.pages.dev
# Should successfully connect to your local backend via tunnel
```

## Result

✅ **Cloudflare frontend** ↔️ **Local backend** ↔️ **IROH distributed network**

Your Cloudflare-deployed frontend will seamlessly connect to your local backend through the tunnel, with full IROH networking capabilities!