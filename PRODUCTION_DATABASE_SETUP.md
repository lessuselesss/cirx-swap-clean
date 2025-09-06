# Database Environment Setup Guide

## Issue Identified  
Testnet production environment shows "no transactions" because the SQLite database file `./storage/database.sqlite` does not exist on the server.

**Current Health Check Error:**
```
Database file at path [./storage/database.sqlite] does not exist. 
Ensure this is an absolute path to the database.
```

## Environment Architecture

This project has **three environment levels:**

### 1. Local Development  
- **Database**: SQLite (`backend/storage/database.dev.sqlite`)  
- **Purpose**: Local testing with seed/mock data
- **TestMode**: N/A (local only)
- **Configuration**: `APP_ENV=development`

### 2. Testnet Production (CURRENT "Production")
- **Database**: SQLite (`./storage/database.testnet.sqlite`)
- **Purpose**: Live server using testnets (Sepolia, etc.)
- **TestMode**: `true` (testnet transactions only)
- **URL**: `https://circularprotocol.io/buy`
- **Configuration**: `APP_ENV=production` + `TESTNET_MODE=true`

### 3. Mainnet Production (FUTURE True Production)
- **Database**: SQLite (`./storage/database.mainnet.sqlite`)
- **Purpose**: Real money transactions on mainnets
- **TestMode**: `false` (real transactions)
- **Configuration**: `APP_ENV=production` + `TESTNET_MODE=false`

## Testnet Production Setup (Current Issue)

**⚠️ CRITICAL: Environment-Specific Database Separation**

Each environment needs its own database to prevent data mixing:

### 1. Initialize Fresh Testnet Production Database

**The current server needs a fresh database for testnet transactions:**

```bash
# On testnet production server (circularprotocol.io)
mkdir -p /path/to/backend/storage
chmod 755 /path/to/backend/storage

# Create fresh testnet production database
cd /path/to/backend
php migrate.php

# Rename to be environment-specific
mv storage/database.sqlite storage/database.testnet.sqlite
```

**Expected result**: Empty database ready for testnet transactions (Sepolia, etc.)

### 2. Environment Configuration Strategy

**Environment-Specific Database Files:**

```bash
# Local Development Environment
APP_ENV=development
TESTNET_MODE=true
DB_CONNECTION=sqlite  
DB_DATABASE=/path/to/backend/storage/database.dev.sqlite    # Local dev/test data

# Testnet Production Environment (CURRENT)
APP_ENV=production  
TESTNET_MODE=true                                           # Using testnets
DB_CONNECTION=sqlite
DB_DATABASE=/path/to/backend/storage/database.testnet.sqlite # Testnet transactions

# Mainnet Production Environment (FUTURE)
APP_ENV=production
TESTNET_MODE=false                                          # Real money
DB_CONNECTION=sqlite  
DB_DATABASE=/path/to/backend/storage/database.mainnet.sqlite # Real transactions
```

**Current Testnet Production Configuration:**

```bash
# Testnet production .env file (circularprotocol.io)
APP_ENV=production
TESTNET_MODE=true
DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/backend/storage/database.testnet.sqlite

# This allows testnet transactions while keeping them separate from future mainnet
```

### 3. Verify Database File Permissions

```bash
# On production server, check PRODUCTION database file
ls -la storage/database.prod.sqlite
# Should show: -rw-r--r-- with proper owner

# Set proper permissions for production database
chmod 664 storage/database.prod.sqlite
chown www-data:www-data storage/database.prod.sqlite  # Adjust user as needed

# Verify storage directory permissions
chmod 755 storage/

# Ensure development database is NOT on production server
ls -la storage/database.sqlite 2>/dev/null && echo "WARNING: Dev database found on prod server!"
```

### 4. Test Database Connection

```bash
# Run migration script to verify database is working
cd /path/to/backend
php migrate.php
```

Expected output:
```
🗄️  Running Database Migrations
==============================

1. Creating/updating transactions table...
   ✅ Table 'transactions' created successfully (or already exists)

2. Creating project_wallets table...
   ✅ Table 'project_wallets' created successfully (or already exists)

🎉 All migrations completed successfully!
✅ Database is ready for use
```

### 5. Verify Tables Created

```bash
# Use SQLite CLI to check tables
sqlite3 storage/database.sqlite ".tables"
# Should show: project_wallets  transactions

# Check table structure
sqlite3 storage/database.sqlite ".schema transactions"
# Should show CREATE TABLE statement with all columns
```

### 6. Test Production Health Check

```bash
# Test production health endpoint
curl -s https://circularprotocol.io/buy/api/v1/health | jq '.checks.database'
# Should show "healthy" status instead of "critical"
```

### 7. Test Transaction Creation

```bash
# Test production endpoint
curl -X POST "https://circularprotocol.io/buy/api/v1/transactions/initiate-swap" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: your-production-api-key" \
  -d '{
    "txId": "0x1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef",
    "paymentChain": "ethereum", 
    "cirxRecipientAddress": "0x5e9784e938a527625dde0c4f88bede4d86f8ab025377c1c5f3624135bbcdc5bb",
    "amountPaid": "100.00",
    "paymentToken": "USDC",
    "senderAddress": "0x11fB73daa15C84c6166BF20e435396c8f08bFEc9"
  }'
```

### 8. Verify Transaction Table

```bash
# Check transactions endpoint
curl -X GET "https://circularprotocol.io/buy/api/v1/transactions/table" \
  -H "X-API-Key: your-production-api-key"

# Should return transaction data instead of empty array
```

## Migration Script Features

The `migrate.php` script automatically:

- ✅ **Creates tables** if they don't exist
- ✅ **Updates existing tables** with missing columns
- ✅ **Adds proper indexes** for performance
- ✅ **Handles both SQLite and MySQL** environments
- ✅ **Safe to run multiple times** (idempotent)

## Tables Created

### `transactions` Table
- Primary transaction records
- Payment verification tracking
- CIRX transfer status
- Retry logic for failed transfers
- Full audit trail with timestamps

### `project_wallets` Table  
- Platform wallet management
- Encrypted private key storage
- Multi-chain support
- Treasury wallet designation

## Troubleshooting

### Database File Not Found Error
- Verify `storage/database.sqlite` exists on production server
- Check file permissions (should be readable/writable by web server)
- Ensure absolute path is used in production `.env` if relative path fails
- Verify storage directory exists and has proper permissions

### Migration Fails
- Check SQLite PHP extension is installed (`php -m | grep sqlite`)
- Verify file write permissions to storage directory
- Ensure sufficient disk space on production server
- Check if SELinux or similar security context is blocking file access

### Still No Transactions After Database Fix
- Test health check endpoint returns database status as "healthy"
- Verify API endpoints are working with proper API keys
- Check application logs for errors
- Confirm production environment detection is correct

## Security Notes

- **Never commit production .env files**
- **Secure database file permissions** (664 recommended)
- **Regular database backups** essential for SQLite
- **Protect storage directory** from direct web access
- **Monitor disk usage** - SQLite grows with transaction volume

## Post-Setup Verification Checklist

- [ ] SQLite database file `storage/database.sqlite` exists on production server
- [ ] Database file has proper permissions (664, owned by web server user)
- [ ] Storage directory has proper permissions (755)
- [ ] Tables `transactions` and `project_wallets` exist
- [ ] Migration script runs without errors
- [ ] Health check endpoint returns database status as "healthy"
- [ ] Test transaction can be created successfully
- [ ] Transaction table endpoint returns created transactions
- [ ] Production frontend shows transactions instead of "no transactions"

## Database Separation Best Practices

### Development Environment
```bash
# Development database (local only)
storage/database.dev.sqlite  # Contains test transactions for development
storage/database.sqlite      # Current dev database (rename to .dev.sqlite)

# Keep development data separate:
- Test transactions with realistic amounts (0.004 ETH, etc.)
- Seed data for local testing
- Safe to reset/rebuild anytime
```

### Production Environment
```bash
# Production database (server only)
storage/database.prod.sqlite  # Contains ONLY real user transactions

# Production data requirements:
- Start completely empty (no test data)
- Populate only through API endpoints
- Regular backups essential
- Never overwrite with dev data
```

### Migration Strategy

**For Current Production Issue:**
1. **Create fresh production database**: Run `php migrate.php` on production server
2. **Use environment-specific filenames**: `database.prod.sqlite` vs `database.dev.sqlite`
3. **Update production .env**: Point to production-specific database file
4. **Verify separation**: Ensure no dev data in production

### Development Database Cleanup

To properly separate your current development environment:

```bash
# In backend directory
mv storage/database.sqlite storage/database.dev.sqlite

# Update development .env
DB_DATABASE=/path/to/backend/storage/database.dev.sqlite
```

## Quick Fix Summary for Testnet Production

**The core issue:** Testnet production server needs a fresh database for testnet transactions (NOT a copy of development data)

**Correct solution for current testnet production:**
1. Create fresh database: `php migrate.php` on `circularprotocol.io` server
2. Name it `database.testnet.sqlite` to indicate testnet environment
3. Update server .env: `DB_DATABASE=./storage/database.testnet.sqlite`
4. Ensure `TESTNET_MODE=true` in server configuration
5. Verify health check passes: `curl https://circularprotocol.io/buy/api/v1/health`

**Database Evolution Path:**
- **Now**: `database.dev.sqlite` (local) + `database.testnet.sqlite` (testnet prod)
- **Future**: When switching to mainnet, add `database.mainnet.sqlite` and set `TESTNET_MODE=false`

**Key Principles:**
- **❌ NEVER DO:** Mix local dev data with any server environment
- **✅ ALWAYS DO:** Separate databases by environment and testnet/mainnet mode  
- **✅ TESTNET MODE:** Keep `testMode=true` until ready for real money transactions# Database path updated to match server structure
# Trigger deployment to apply DB_DATABASE secret update
