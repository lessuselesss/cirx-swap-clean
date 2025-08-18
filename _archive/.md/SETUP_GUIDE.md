# CIRX OTC Platform Setup Guide

## Overview

The frontend has been successfully updated to use the backend API instead of smart contracts. The system now operates as a wallet-to-wallet transfer platform where:

1. **Users send payments** to designated deposit addresses
2. **Backend verifies payments** on various blockchains
3. **Backend transfers CIRX** from a hot wallet to user's Circular address
4. **Pricing and discounts** are handled by the backend service

## Environment Variables Needed

### Frontend Configuration (ui/.env.local)

```bash
# Backend API Configuration
NUXT_PUBLIC_API_BASE_URL=http://localhost:8080
NUXT_PUBLIC_API_KEY=dev_api_key_replace_in_production

# Deposit Wallet Addresses (CRITICAL - Replace with real addresses)
NUXT_PUBLIC_ETH_DEPOSIT_ADDRESS=0x1234567890123456789012345678901234567890
NUXT_PUBLIC_USDC_DEPOSIT_ADDRESS=0x1234567890123456789012345678901234567890
NUXT_PUBLIC_USDT_DEPOSIT_ADDRESS=0x1234567890123456789012345678901234567890
NUXT_PUBLIC_POLYGON_DEPOSIT_ADDRESS=0x1234567890123456789012345678901234567890
NUXT_PUBLIC_BSC_DEPOSIT_ADDRESS=0x1234567890123456789012345678901234567890

# WalletConnect Configuration
NUXT_PUBLIC_WALLETCONNECT_PROJECT_ID=your-walletconnect-project-id
```

### Backend Configuration (backend/.env)

```bash
# CIRX Hot Wallet (CRITICAL - Replace with real credentials)
CIRX_WALLET_PRIVATE_KEY=your-cirx-wallet-private-key-here
CIRX_WALLET_ADDRESS=your-cirx-wallet-address-here
CIRX_RPC_URL=https://rpc.circular-protocol.com
CIRX_API_KEY=your-circular-protocol-api-key

# Blockchain RPC Endpoints (for payment verification)
ETHEREUM_RPC_URL=https://mainnet.infura.io/v3/YOUR_INFURA_PROJECT_ID
ETHERSCAN_API_KEY=YOUR_ETHERSCAN_API_KEY

# Database Configuration
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=cirx_otc
DB_USERNAME=cirx_user
DB_PASSWORD=your_secure_password
```

## Required Setup Steps

### 1. Hot Wallet Setup
- **Create a CIRX wallet** on Circular Protocol
- **Fund it with CIRX tokens** for OTC transfers
- **Store private key securely** in environment variables
- **Never commit private keys** to version control

### 2. Deposit Wallet Setup
- **Create separate wallets** for each supported token/chain
- **Use different addresses** for ETH, USDC, USDT, etc.
- **Monitor these addresses** for incoming payments
- **Set up automated sweeping** to main treasury (optional)

### 3. RPC Provider Setup
- **Sign up for Infura/Alchemy** for Ethereum access
- **Get API keys** for blockchain explorers (Etherscan, etc.)
- **Configure backup RPC URLs** for redundancy

### 4. Database Setup
- **Create MySQL database** for production
- **Run migrations** to set up tables
- **Configure connection pooling** for performance

## Testing the Integration

### Local Testing

1. **Start the backend server:**
   ```bash
   cd backend
   php -S localhost:8080 public/index.php
   ```

2. **Start the frontend:**
   ```bash
   cd ui
   npm run dev
   ```

3. **Test the flow:**
   - Visit http://localhost:3000
   - Select a token and amount
   - Enter a Circular Protocol address
   - Click the swap button
   - Follow the deposit instructions

### API Testing

Test the backend endpoints directly:

```bash
# Test health endpoint
curl http://localhost:8080/v1/health

# Test swap initiation
curl -X POST http://localhost:8080/v1/transactions/initiate-swap \
  -H "Content-Type: application/json" \
  -d '{
    "txId": "0x1234567890123456789012345678901234567890123456789012345678901234",
    "paymentChain": "ethereum",
    "cirxRecipientAddress": "0x1234567890123456789012345678901234567890123456789012345678901234",
    "amountPaid": "1.0",
    "paymentToken": "ETH"
  }'

# Test transaction status
curl http://localhost:8080/v1/transactions/{swapId}/status
```

## Production Deployment Checklist

### Security
- [ ] Replace all placeholder private keys
- [ ] Use secure key management (HashiCorp Vault, AWS KMS)
- [ ] Enable API key authentication
- [ ] Configure rate limiting
- [ ] Use HTTPS for all communications
- [ ] Set up proper CORS policies

### Infrastructure
- [ ] Deploy backend on secure server
- [ ] Set up MySQL database with backups
- [ ] Configure Redis for queue processing
- [ ] Set up monitoring and alerting
- [ ] Configure log rotation
- [ ] Set up SSL certificates

### Testing
- [ ] Test payment verification on all chains
- [ ] Test CIRX transfers to Circular addresses
- [ ] Test error handling and recovery
- [ ] Load test the API endpoints
- [ ] Test failover scenarios

## Current System Architecture

```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────────┐
│   Frontend      │    │   Backend API    │    │  Circular Protocol │
│   (Nuxt.js)     │    │   (PHP/MySQL)    │    │   (CIRX Transfers)  │
├─────────────────┤    ├──────────────────┤    ├─────────────────────┤
│ • Wallet UI     │───▶│ • Payment Verify │───▶│ • Hot Wallet        │
│ • Quote Calc    │    │ • CIRX Transfer  │    │ • CIRX Token        │
│ • Instructions  │    │ • Status Track   │    │ • Vesting Logic     │
│ • Status Check  │    │ • Error Handle   │    │                     │
└─────────────────┘    └──────────────────┘    └─────────────────────┘
         │                        │                        │
         │                        ▼                        │
         │              ┌──────────────────┐               │
         │              │  External APIs   │               │
         │              ├──────────────────┤               │
         └──────────────│ • Etherscan      │──────────────┘
                        │ • Infura/Alchemy │
                        │ • CoinGecko      │
                        │ • Block Explorers│
                        └──────────────────┘
```

## Flow Diagram

1. **User initiates swap** on frontend
2. **Frontend shows deposit instructions** with address and amount
3. **User sends payment** to deposit address
4. **User provides transaction hash** to frontend
5. **Frontend submits swap request** to backend API
6. **Backend verifies payment** on blockchain
7. **Backend transfers CIRX** to user's Circular address
8. **User receives CIRX** (liquid or vested)

## Support and Troubleshooting

### Common Issues

1. **"Invalid Circular address"** - Ensure 64-character hex format with 0x prefix
2. **"Backend API not responding"** - Check backend server is running on port 8080
3. **"Payment verification failed"** - Verify transaction hash and blockchain
4. **"CIRX transfer failed"** - Check hot wallet balance and Circular RPC

### Logs and Debugging

- **Backend logs:** `/tmp/cirx-otc-dev/application.log`
- **Frontend logs:** Browser console
- **API responses:** Include error details and request IDs

## Next Steps

1. **Set up real wallets and addresses**
2. **Configure blockchain RPC providers**
3. **Test with small amounts first**
4. **Set up monitoring and alerts**
5. **Plan production deployment strategy**

The system is now ready for testing with real wallet addresses and blockchain connections!