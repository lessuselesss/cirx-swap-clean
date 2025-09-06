# Production Database Setup Commands

Based on the production health check error showing path `./storage/database.sqlite`, here are the exact commands to run on the production server:

## Current Production Issue
```
Database file at path [./storage/database.sqlite] does not exist.
```

## Production Server Setup Commands

**Run these commands on the production server (circularprotocol.io):**

### 1. Navigate to Backend Directory
```bash
# Find the backend directory (likely where migrate.php exists)
cd /path/to/backend  # This is where migrate.php should be located
```

### 2. Create Storage Directory (if needed)
```bash
mkdir -p storage
chmod 755 storage
```

### 3. Create Testnet Production Database
```bash
# Run migrations to create fresh database
php migrate.php
```

### 4. Rename Database for Environment Separation
```bash
# Rename the default database to testnet-specific
mv storage/database.sqlite storage/database.testnet.sqlite
```

### 5. Set Proper Permissions
```bash
# Set database file permissions
chmod 664 storage/database.testnet.sqlite

# Ensure web server can access (adjust user as needed)
chown www-data:www-data storage/database.testnet.sqlite
# OR depending on your server setup:
# chown apache:apache storage/database.testnet.sqlite
```

### 6. Update Production Environment Configuration
Update the production `.env` file:
```bash
# Production .env changes needed
DB_CONNECTION=sqlite
DB_DATABASE=./storage/database.testnet.sqlite

# OR use absolute path (more reliable):
# DB_DATABASE=/absolute/path/to/backend/storage/database.testnet.sqlite

# Ensure testnet mode is set
APP_ENV=production
TESTNET_MODE=true
```

### 7. Verify Setup
```bash
# Check database file exists and has proper permissions
ls -la storage/database.testnet.sqlite

# Test the health check endpoint
curl -s "https://circularprotocol.io/buy/api/v1/health" | head -c 200
# Should show "healthy" database status instead of "critical"
```

## Future Mainnet Setup

When ready to switch to mainnet (set `TESTNET_MODE=false`):

```bash
# Create separate mainnet database
php migrate.php
mv storage/database.sqlite storage/database.mainnet.sqlite
chmod 664 storage/database.mainnet.sqlite

# Update .env for mainnet
DB_DATABASE=./storage/database.mainnet.sqlite
TESTNET_MODE=false
```

## Expected Results

**Before fix:**
- Health check shows: `"Database: Database connection failed"`
- Status: `"not_ready"`

**After fix:**
- Health check shows: `"Database: Database status: healthy"`
- Status: `"ready"`
- Database contains only testnet transactions

## Path Structure (Inferred from API responses)

```
/path/to/backend/
├── migrate.php                    # Migration script
├── storage/
│   ├── database.testnet.sqlite   # Testnet transactions (current need)
│   └── database.mainnet.sqlite   # Future mainnet transactions
├── public/
│   └── index.php                 # API entry point
└── .env                          # Environment configuration
```

## Verification Steps

1. **Database exists**: `ls -la storage/database.testnet.sqlite`
2. **Permissions correct**: File should be readable/writable by web server
3. **Health check passes**: API returns healthy database status
4. **Environment separation**: Testnet and mainnet databases remain separate

This approach maintains the three-tier environment strategy:
- **Local**: `database.dev.sqlite` (development)  
- **Testnet Production**: `database.testnet.sqlite` (current server)
- **Mainnet Production**: `database.mainnet.sqlite` (future)