# 🚀 CIRX OTC Platform - Simple Startup Guide

**No smart contracts needed!** This is a traditional OTC platform that works with existing tokens.

## 🏗️ Simple Architecture

```
📥 User Payment (ETH/USDC/USDT) 
    ↓
🏦 Project Wallet (receives payments)
    ↓  
📊 Indexer (monitors payments)
    ↓
🌐 Backend API (calculates CIRX owed, handles vesting)
    ↓
📤 CIRX Token Transfer (to user's wallet)
    ↓
🎨 Frontend (shows transaction history)
```

## 📋 Prerequisites

- **Node.js** 18+ and npm
- **PHP** 8.1+ and composer
- **Existing CIRX token contract** (ERC20)
- **Project wallet** with CIRX tokens to distribute

## 🚀 Quick Start (3 Services Only!)

### Step 1: Configure Project Wallet

You need:
1. **Project wallet address** - Where users send ETH/USDC/USDT
2. **Project wallet private key** - To send CIRX tokens
3. **CIRX token contract address** - The token you're selling

### Step 2: Start Indexer Service

```bash
cd indexer

# Install dependencies
npm install

# Configure for real blockchain
cp .env.example .env
```

Edit `indexer/.env`:
```bash
# Use real Ethereum mainnet or testnet
RPC_URL=https://mainnet.infura.io/v3/YOUR_INFURA_KEY
# Or for testing: https://sepolia.infura.io/v3/YOUR_INFURA_KEY

# Your project wallet address (where payments come in)
PROJECT_WALLET_ADDRESS=0xYourProjectWalletAddress

# CIRX token contract
CIRX_TOKEN_ADDRESS=0xYourCIRXTokenContract

# Start from recent block (much faster)
START_BLOCK=19000000

INDEXER_PORT=3001
```

```bash
# Initialize database and start
npm run init-db
npm run dev
```

### Step 3: Start Backend API

```bash
cd backend

# Install dependencies
composer install

# Set up environment
cp .env.example .env
```

Edit `backend/.env`:
```bash
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=sqlite
DB_DATABASE=/full/path/to/backend/storage/database.sqlite

# Real blockchain connection
ETHEREUM_RPC_URL=https://mainnet.infura.io/v3/YOUR_INFURA_KEY
INDEXER_URL=http://localhost:3001

# Your project wallet (same as indexer)
PROJECT_WALLET_ADDRESS=0xYourProjectWalletAddress
PROJECT_WALLET_PRIVATE_KEY=0xYourPrivateKey

# CIRX token contract
CIRX_TOKEN_CONTRACT=0xYourCIRXTokenContract

# Security (use strong values)
API_KEY=your-secure-api-key
JWT_SECRET=your-jwt-secret
```

```bash
# Run migrations and start
php migrate.php
php -S localhost:8080 -t public
```

### Step 4: Start Frontend

```bash
cd ui

# Install dependencies
npm install

# Configure environment
cp .env.example .env
```

Edit `ui/.env`:
```bash
NUXT_PUBLIC_BACKEND_URL=http://localhost:8080
NUXT_PUBLIC_INDEXER_URL=http://localhost:3001

# Your project wallet (where users send payments)
NUXT_PUBLIC_PROJECT_WALLET=0xYourProjectWalletAddress

# CIRX token for display
NUXT_PUBLIC_CIRX_TOKEN_ADDRESS=0xYourCIRXTokenContract

# Optional: WalletConnect for better UX
NUXT_PUBLIC_WALLETCONNECT_PROJECT_ID=your-walletconnect-id
```

```bash
# Start frontend
npm run dev
```

## 🎯 How It Works

### 1. User Makes Payment
- User connects wallet to your frontend (`localhost:3000`)
- User sends ETH/USDC/USDT to your project wallet address
- Frontend shows payment instructions and tracks status

### 2. Payment Detection
- Indexer monitors your project wallet for incoming payments
- Detects ETH, USDC, USDT transfers
- Stores payment data in database

### 3. OTC Processing  
- Backend calculates CIRX tokens owed based on:
  - Payment amount
  - Current CIRX price
  - OTC discount tier (5%, 8%, 12%)
- For liquid purchases: Sends CIRX immediately
- For vested purchases: Creates vesting schedule

### 4. Token Distribution
- Backend uses your project wallet private key
- Sends CIRX tokens to user's wallet
- Updates transaction status

### 5. User Interface
- Frontend shows transaction history
- Displays vesting positions and claimable amounts
- Allows users to claim vested tokens

## ✅ What You Need to Provide

1. **CIRX Token Contract** - Already deployed ERC20
2. **Project Wallet** - Funded with CIRX tokens to sell
3. **RPC Access** - Infura/Alchemy for blockchain connection
4. **Pricing Oracle** - How you determine CIRX/USD price

## 🧪 Testing Your Setup

```bash
# 1. Check all services
curl http://localhost:3001/health  # Indexer
curl http://localhost:8080/health  # Backend
open http://localhost:3000         # Frontend

# 2. Send test payment to your project wallet
# 3. Check indexer detected it:
curl http://localhost:3001/api/transactions/YOUR_PROJECT_WALLET

# 4. Check backend processed it:
curl http://localhost:8080/api/v1/transactions
```

## 🚀 Production Deployment

### Environment Setup
- Use mainnet RPC URLs
- Secure your private keys (use env vars, not files)
- Set up monitoring for your project wallet
- Configure proper logging

### Scaling Considerations
- Indexer can run on separate server
- Backend can use MySQL instead of SQLite
- Frontend can deploy to CDN (Cloudflare/Vercel)
- Set up backup wallet monitoring

## 💡 Key Benefits of This Approach

✅ **No custom contracts** - Use existing, tested ERC20 tokens  
✅ **Faster development** - No Solidity coding or testing  
✅ **Lower gas costs** - Only standard token transfers  
✅ **More flexible** - Easy to change OTC rules in backend  
✅ **Better UX** - Immediate feedback, detailed transaction tracking  
✅ **Easier auditing** - Traditional backend logic vs complex smart contracts  

## 🔧 Troubleshooting

**Indexer not detecting payments?**
- Check RPC URL is working
- Verify project wallet address is correct
- Make sure START_BLOCK is before your first payment

**Backend not sending CIRX?**
- Check project wallet has CIRX tokens
- Verify private key has permission to transfer tokens
- Check CIRX token contract address is correct

**Frontend wallet connection issues?**
- Make sure you're on the right network (mainnet/testnet)
- Check WalletConnect project ID if using WC

This is much simpler and more practical for most OTC use cases! 🎉