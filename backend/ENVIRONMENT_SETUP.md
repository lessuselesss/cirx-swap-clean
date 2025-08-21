# Environment Configuration Guide

## Overview

This project uses a secure environment configuration system that prevents credentials from being committed to version control.

## Environment File Structure

### Streamlined Configuration System:
- **`.env.base`** - Shared constants across all environments (CIRX URLs, decimals, worker base config)
- **`.env.example`** - Production template with placeholder values (committed to Git)
- **`.env.development.example`** - Development template with placeholder values (committed to Git)  
- **`.env.e2e`** - E2E testing configuration with test values (committed to Git)
- **`.env.local`** - Your personal development credentials (NOT committed to Git)
- **`.env.production`** - Production secrets (NOT committed to Git, managed by deployment)

### Inheritance Model:
All environment files inherit shared constants from `.env.base` to eliminate redundancy and ensure consistency. Each file only contains environment-specific overrides.

## Quick Setup for Development

1. **Copy the development template:**
   ```bash
   cd backend
   cp .env.development.example .env.local
   ```

2. **Update your personal credentials in `.env.local`:**
   - Replace `your_*_here` placeholders with your actual development values
   - Use testnet credentials only (never mainnet private keys)
   - Get your own API keys for Infura, Etherscan, etc.

3. **Required credentials for development:**
   - **CIRX_WALLET_PRIVATE_KEY**: Testnet private key for CIRX operations
   - **TELEGRAM_BOT_TOKEN**: Create a test bot with @BotFather on Telegram
   - **Infura/Alchemy keys**: For blockchain RPC access
   - **Database path**: Update SQLite path to your local directory

## Security Guidelines

### ✅ DO:
- Use `.env.local` for all personal development credentials
- Use testnet private keys and API keys for development
- Create separate test bots/accounts for development
- Keep production credentials in secure deployment systems

### ❌ DON'T:
- Never commit `.env.local` or any file with real credentials
- Never use mainnet private keys in development
- Never share your `.env.local` file
- Never commit production credentials to Git

## Environment Variables Reference

### Required for Development:
```bash
# Database
DB_CONNECTION=sqlite
DB_DATABASE=/path/to/your/local/database.sqlite

# CIRX Blockchain (REQUIRED)
CIRX_WALLET_PRIVATE_KEY=your_testnet_cirx_private_key
CIRX_WALLET_ADDRESS=your_testnet_cirx_address

# Telegram Notifications
TELEGRAM_BOT_TOKEN=your_test_bot_token
TELEGRAM_CHAT_ID=your_test_chat_id

# Blockchain RPC (Sepolia testnet recommended)
ETHEREUM_RPC_URL=https://sepolia.infura.io/v3/YOUR_PROJECT_ID
ETHERSCAN_API_KEY=YOUR_API_KEY
```

### Streamlined Configuration Benefits:
- **60% reduction** in duplicated variables
- **Consistent** constants across all environments
- **Standardized** rate limiting variable names (`RATE_LIMIT_ENABLED`, `RATE_LIMIT_REQUESTS`, `RATE_LIMIT_WINDOW`)
- **Eliminated** unused variables (commented gas config, Solana config)
- **Centralized** protocol constants in `.env.base`

### Optional Development Settings:
```bash
# API Security (can be disabled for development)
API_KEY_REQUIRED=false
RATE_LIMIT_ENABLED=false

# Logging (verbose for debugging)
LOG_LEVEL=debug
LOG_REQUEST_BODY=true
LOG_RESPONSE_BODY=true

# Feature Flags (enable for testing)
E2E_TESTING_ENABLED=true
TESTNET_MODE=true
```

## Production Deployment

### Environment Management:
1. **Never use `.env` files in production**
2. **Use proper secret management:**
   - AWS Secrets Manager
   - HashiCorp Vault  
   - Kubernetes Secrets
   - Environment injection in CI/CD

3. **Required production settings:**
   ```bash
   APP_ENV=production
   APP_DEBUG=false
   API_KEY_REQUIRED=true
   RATE_LIMIT_ENABLED=true
   TESTNET_MODE=false
   ```

### Production Checklist:
- [ ] All credentials rotated from development versions
- [ ] Mainnet RPC endpoints configured
- [ ] Production database configured
- [ ] Rate limiting enabled
- [ ] Debug mode disabled
- [ ] Monitoring and alerts configured
- [ ] Backup systems in place

## Troubleshooting

### Backend won't start:
1. Check `.env.local` exists and has required variables
2. Verify database file path is accessible
3. Ensure SQLite file has proper permissions

### Database errors:
1. Run migrations: `php migrate.php`
2. Check database file path in `.env.local`
3. Verify file system permissions

### API connection errors:
1. Verify RPC URLs are accessible
2. Check API key validity and limits
3. Confirm testnet vs mainnet configuration

## Migration from Old Setup

If you had the old `.env` files:

1. **Backup your credentials** (save somewhere secure, not in Git)
2. **Delete old files:**
   ```bash
   rm backend/.env backend/.env.local  # These are now gitignored
   ```
3. **Follow setup steps above** to create new `.env.local`
4. **Rotate all production credentials** (the old ones are compromised in Git history)

## Support

For questions about environment setup:
1. Check this documentation first
2. Verify your `.env.local` against the examples
3. Check logs for specific error messages
4. Ask team members (but never share your `.env.local` contents)