# 🚀 CIRX OTC Platform - Complete Startup Guide

This guide will help you get the complete CIRX OTC trading platform running locally with all services.

## 📋 Prerequisites

Make sure you have these tools installed:

- **Node.js** 18+ and npm
- **PHP** 8.1+ with composer
- **Foundry** (forge, anvil, cast)
- **Git** for version control

### Quick Install Commands

```bash
# Install Foundry
curl -L https://foundry.paradigm.xyz | bash
foundryup

# Verify installations
node --version    # Should be 18+
php --version     # Should be 8.1+
forge --version   # Should show foundry version
```

## 🏗️ Project Architecture

The project consists of 4 main components:

```
cirx-swap/
├── 📦 Smart Contracts (Foundry)    # Solidity contracts with Anvil
├── 🔗 Indexer Service (Node.js)    # Blockchain event indexer
├── 🌐 Backend API (PHP)            # Payment verification & OTC logic
└── 🎨 Frontend (Nuxt.js)           # User interface
```

## 🔄 Complete Setup Process

### Step 1: Smart Contracts & Local Blockchain

```bash
# 1. Start local blockchain (keep this running)
anvil
# This runs on http://localhost:8545 with funded test accounts
```

In a new terminal:

```bash
# 2. Build and deploy contracts
forge build

# 3. Deploy contracts to local anvil
forge script script/Counter.s.sol:CounterScript --rpc-url http://localhost:8545 --private-key 0xac0974bec39a17e36ba4a6b4d238ff944bacb478cbed5efcae784d7bf4f2ff80
```

> **Note**: The private key above is Anvil's first default account - safe for local development only!

### Step 2: Indexer Service Setup

```bash
# 1. Navigate to indexer directory
cd indexer

# 2. Install dependencies
npm install

# 3. Create environment file (optional - has good defaults)
cp .env.example .env

# 4. Initialize database
npm run init-db

# 5. Start indexer service (keep running)
npm run dev
```

The indexer will:
- Connect to Anvil at `http://localhost:8545`
- Create SQLite database at `./data/transactions.db`
- Start API server at `http://localhost:3001`
- Begin monitoring blockchain for events

### Step 3: Backend API Setup

```bash
# 1. Navigate to backend directory
cd ../backend

# 2. Install PHP dependencies
composer install

# 3. Set up environment
cp .env.example .env

# 4. Run database migrations
php migrate.php

# 5. Start backend server
php -S localhost:8080 -t public
```

The backend will:
- Run at `http://localhost:8080`
- Connect to indexer at `http://localhost:3001`
- Use SQLite database for transaction tracking
- Provide OTC swap API endpoints

### Step 4: Frontend Setup

```bash
# 1. Navigate to frontend directory
cd ../ui

# 2. Install dependencies
npm install

# 3. Set up environment
cp .env.example .env

# 4. Start development server
npm run dev
```

The frontend will:
- Run at `http://localhost:3000`
- Connect to backend API at `http://localhost:8080`
- Connect to indexer at `http://localhost:3001`
- Provide wallet connection and swap interface

## 🔧 Environment Configuration

### Indexer `.env` (optional)
```bash
# indexer/.env
RPC_URL=http://localhost:8545
START_BLOCK=0
INDEXER_PORT=3001
OTC_SWAP_ADDRESS=0x0000000000000000000000000000000000000000
VESTING_CONTRACT_ADDRESS=0x0000000000000000000000000000000000000000
```

### Backend `.env`
```bash
# backend/.env
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8080

# Database
DB_CONNECTION=sqlite
DB_DATABASE=/full/path/to/backend/storage/database.sqlite

# Blockchain
ETHEREUM_RPC_URL=http://localhost:8545
INDEXER_URL=http://localhost:3001

# Security (development only)
API_KEY=dev-api-key
JWT_SECRET=dev-jwt-secret
```

### Frontend `.env`
```bash
# ui/.env
NUXT_PUBLIC_BACKEND_URL=http://localhost:8080
NUXT_PUBLIC_INDEXER_URL=http://localhost:3001
NUXT_PUBLIC_WALLETCONNECT_PROJECT_ID=your-project-id-here
```

## 🧪 Testing the Setup

### 1. Verify All Services

Check that all services are running:

```bash
# Anvil blockchain
curl http://localhost:8545 -X POST -H "Content-Type: application/json" -d '{"jsonrpc":"2.0","method":"eth_blockNumber","params":[],"id":1}'

# Indexer health
curl http://localhost:3001/health

# Backend health  
curl http://localhost:8080/health

# Frontend (open in browser)
open http://localhost:3000
```

### 2. Test Wallet Connection

1. Open http://localhost:3000
2. Click "Connect Wallet"
3. Use MetaMask with Anvil network:
   - Network: `http://localhost:8545`
   - Chain ID: `31337`
   - Currency: `ETH`

### 3. Test OTC Swap Flow

1. Connect wallet to frontend
2. Navigate to swap page
3. Try a small test swap
4. Check transaction in indexer and backend

## 📱 Development Workflow

### Daily Development Startup

```bash
# Terminal 1: Blockchain
anvil

# Terminal 2: Indexer
cd indexer && npm run dev

# Terminal 3: Backend
cd backend && php -S localhost:8080 -t public

# Terminal 4: Frontend
cd ui && npm run dev
```

### Making Changes

1. **Smart Contracts**: Edit in `/src/`, run `forge build`, redeploy
2. **Indexer**: Changes auto-reload with `npm run dev`
3. **Backend**: Restart PHP server after changes
4. **Frontend**: Hot reload enabled with `npm run dev`

## 🐛 Troubleshooting

### Common Issues

**1. Indexer can't connect to blockchain**
```bash
# Check Anvil is running
curl http://localhost:8545

# Check indexer logs
cd indexer && DEBUG=true npm run dev
```

**2. Backend API errors**
```bash
# Check backend logs
tail -f backend/storage/logs/application.log

# Test direct backend call
curl http://localhost:8080/health
```

**3. Frontend wallet connection issues**
```bash
# Check browser console for errors
# Ensure MetaMask is configured for localhost:8545
# Chain ID should be 31337
```

**4. Database connection errors**
```bash
# Check database file exists
ls -la backend/storage/database.sqlite
ls -la indexer/data/transactions.db

# Reinitialize if needed
cd backend && php migrate.php
cd indexer && npm run init-db
```

### Service Health Checks

```bash
# Check all services at once
echo "Anvil:"; curl -s http://localhost:8545 -X POST -H "Content-Type: application/json" -d '{"jsonrpc":"2.0","method":"eth_blockNumber","params":[],"id":1}' | jq .result
echo "Indexer:"; curl -s http://localhost:3001/health | jq .status
echo "Backend:"; curl -s http://localhost:8080/health | jq .status
echo "Frontend: Open http://localhost:3000 in browser"
```

## 🚀 Production Deployment

### Smart Contracts
```bash
# Deploy to testnet/mainnet
forge script script/Deploy.s.sol --rpc-url $RPC_URL --private-key $PRIVATE_KEY --broadcast
```

### Indexer
```bash
# Production start
cd indexer
npm install --production
npm start
```

### Backend
```bash
# Production setup
cd backend
composer install --no-dev --optimize-autoloader
# Use proper web server (nginx + php-fpm)
```

### Frontend
```bash
# Build for production
cd ui
npm run build
# Deploy to Cloudflare Pages, Vercel, or Netlify
```

## 📚 Additional Resources

- **Smart Contracts**: See `/README.md` for Foundry commands
- **Indexer**: See `/indexer/README.md` for API documentation
- **Backend**: See `/backend/docs/` for API documentation
- **Frontend**: See `/ui/README.md` for component documentation
- **E2E Testing**: See `/backend/docs/E2E_TESTING_GUIDE.md`

## 🎯 Quick Start Summary

1. **Start Anvil**: `anvil`
2. **Deploy contracts**: `forge script script/Counter.s.sol:CounterScript --rpc-url http://localhost:8545 --private-key 0xac0974bec39a17e36ba4a6b4d238ff944bacb478cbed5efcae784d7bf4f2ff80`
3. **Start indexer**: `cd indexer && npm install && npm run init-db && npm run dev`
4. **Start backend**: `cd backend && composer install && php migrate.php && php -S localhost:8080 -t public`
5. **Start frontend**: `cd ui && npm install && npm run dev`
6. **Open browser**: http://localhost:3000

Happy trading! 🎉