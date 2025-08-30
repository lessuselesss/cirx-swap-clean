# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview


This is a **Circular CIRX OTC Trading Platform** with API-first architecture:
- **Backend API** integrating with Circular Protocol for blockchain operations
- **Nuxt.js frontend** with dual-tab interface (liquid/vested purchases)
- **Phase 1**: Complete OTC purchase platform with wallet integration and transaction tracking

**Current Status**: Production-ready platform with comprehensive E2E testing framework.

**AI Context**: For comprehensive project understanding, refer to `@PROJECT_INDEX.json` which contains:
- Complete file structure with tracked files across PHP, Vue, JavaScript, and configuration files
- Function signatures and relationships across the entire codebase
- Architecture patterns and component dependencies
- Testing structure mapping between backend PHPUnit and frontend E2E tests

## ✅ RECENT PROGRESS SUMMARY (2025-08-29)

### **Critical AppKit/WalletConnect Integration RESOLVED**

**Problem**: Users encountered critical errors and Vue warnings when interacting with wallet connection functionality.

**Root Causes**:
1. Hardcoded project IDs in codebase (security risk)
2. Vue lifecycle warnings from composables called outside component context
3. Invalid watch sources during SSR/hydration

**Solution Implemented**:
1. **Security Fix**: Moved all project IDs to environment variables
   - Updated `nuxt.config.ts` to use `process.env.NUXT_PUBLIC_REOWN_PROJECT_ID`
   - Removed hardcoded project IDs from all files
   - Ensured proper environment variable configuration

2. **Vue Lifecycle Warnings Fixed**: 
   - Enhanced `useAggregatePriceFeed.js` with proper component context detection
   - Added manual lifecycle management in components (`index.vue`, `CirxPriceChart.vue`)
   - Fixed SSR compatibility by preventing lifecycle hooks during server-side rendering

3. **Watch Source Warnings Resolved**:
   - Converted direct reactive references to getter functions in watch()
   - Prevents "Invalid watch source: undefined" during AppKit initialization
   - Added defensive fallbacks for undefined values during hydration

**Key Commits**:
- `7f26fe2e2` - Fix Vue lifecycle warnings in useAggregatePriceFeed composable
- `4754ab077` - Resolve Vue watch source warning for AppKit composables
- Previous security and error handling improvements

### **Current System Status**
- ✅ **Security**: All API keys moved to environment variables, no hardcoded credentials
- ✅ **Vue Warnings**: Eliminated all Vue lifecycle and watch source warnings
- ✅ **AppKit Integration**: Proper Reown AppKit configuration with environment-based project ID
- ✅ **SSR Compatibility**: Full server-side rendering support without client-side conflicts
- ✅ **Error Handling**: Comprehensive error boundaries with toast notifications
- ✅ **Development Environment**: Clean console output, no warnings during development

### **What's Working**
- **Secure Configuration**: Environment-based API key management
- **Vue 3 Composition API**: Proper lifecycle management and reactivity
- **Reown AppKit**: Professional wallet connection UI with multi-wallet support
- **SSR/SPA Compatibility**: Works in both server-side and client-side rendering modes
- **Development Experience**: Clean development server with no warnings or errors
- **Production Ready**: Builds successfully for Cloudflare Pages deployment

### **Next Steps for Development**
1. **Implement Real Wallet Connection**: Connect AppKit to actual transaction flows
2. **Contract Integration**: Implement actual CIRX token smart contract interactions
3. **Transaction Processing**: Connect frontend wallet integration to backend API
4. **OTC Functionality**: Implement vesting contracts and discount calculations
5. **User Testing**: Test wallet connection flows across different providers

### **Files Modified in Recent Sessions**
- `ui/composables/useAggregatePriceFeed.js` - Fixed Vue lifecycle warnings with proper context detection
- `ui/pages/index.vue` - Added manual lifecycle management and fixed watch sources
- `ui/components/CirxPriceChart.vue` - Added lifecycle hooks for price feed management
- `ui/components/WalletButton.vue` - Simplified to use AppKit composables directly
- `ui/components/TestWallet.vue` - Updated to use proper AppKit integration
- `ui/nuxt.config.ts` - Moved to environment-based configuration
- `ui/plugins/1.appkit.client.js` - Enhanced error handling and project ID validation
- `ui/config/appkit.js` - Removed hardcoded project IDs

### **Technical Improvements Completed**
- **Security Hardening**: Eliminated all hardcoded API keys and credentials
- **Vue 3 Best Practices**: Proper composable patterns and lifecycle management
- **SSR/Hydration**: Fixed all server-side rendering compatibility issues
- **Error Boundaries**: Comprehensive error handling without disrupting user experience
- **Development Quality**: Clean console output, proper TypeScript integration
- **Modern Web3**: Latest Reown AppKit with multi-chain wallet support

## How This Project Works

### **Architecture Overview**

The **Circular CIRX OTC Trading Platform** is a modern web application that enables users to purchase CIRX tokens with discounts through an Over-The-Counter (OTC) trading interface.

### **Frontend Application (Nuxt.js)**

**Port**: `localhost:3000` (development)
**Framework**: Nuxt.js 3 with Vue.js Composition API

**Key Components**:
- **`ui/pages/index.vue`**: Main trading interface with dual-tab layout (liquid vs. OTC)
- **`ui/components/WalletButton.vue`**: Reown AppKit wallet connection component
- **`ui/components/CirxPriceChart.vue`**: Real-time CIRX price chart with TradingView integration
- **`ui/composables/useAggregatePriceFeed.js`**: Multi-exchange price aggregation composable

**Wallet Integration**:
- **Reown AppKit**: Professional multi-wallet connection (MetaMask, WalletConnect, etc.)
- **Environment Configuration**: Project ID managed via `NUXT_PUBLIC_REOWN_PROJECT_ID`
- **Multi-Chain Support**: Ethereum mainnet, Arbitrum, Base, Optimism, Polygon
- **SSR Compatible**: Works in both server-side and client-side rendering

**Price Data**:
- **Multi-Exchange Aggregation**: BitMart, XT, LBank price feeds
- **Real-Time Updates**: 30-second intervals with fallback mechanisms
- **TradingView Charts**: Professional charting with historical data

### **Backend API (PHP)**

**Port**: `localhost:18423` (development)
**Framework**: PHP 8.2 with Slim framework (Laravel-style architecture)

**Key Endpoints**:
- **`/api/v1/health`**: Comprehensive health check with blockchain connectivity
- **`/api/v1/health/quick`**: Fast connectivity check for frontend
- **`/api/v1/ping`**: Ultra-fast ping endpoint for basic connectivity
- **`/api/v1/swap/execute`**: Execute CIRX token swaps via Circular Protocol
- **`/api/v1/otc/config`**: OTC discount tier configuration
- **`/api/v1/proxy/circulating-supply`**: CORS-free proxy to Circular Labs APIs

**Blockchain Integration**:
- **Circular Protocol APIs**: All blockchain operations via official APIs
- **No Direct Smart Contracts**: Uses Circular Protocol's backend for reliability
- **Transaction Verification**: Real-time payment and transfer verification
- **Multi-Network Support**: Ethereum, Solana, and Circular Protocol native

**Database**: SQLite for development, PostgreSQL for production

### **Development Workflow**

**1. Start Development Environment**:
```bash
# Terminal 1: Backend API
cd backend
nix run nixpkgs#php -- -S localhost:18423 public/index.php

# Terminal 2: Frontend App
cd ui
npm run dev
```

**2. Environment Configuration**:
```bash
# ui/.env (create this file)
NUXT_PUBLIC_REOWN_PROJECT_ID=your_reown_project_id
```

**3. Live Development**:
- **Frontend**: `http://localhost:3000` - Hot module replacement enabled
- **Backend API**: `http://localhost:18423/api/v1/health` - Auto-restart on changes
- **Clean Console**: No warnings or errors during development

### **User Flow**

**1. Landing Page**: User sees swap interface with CIRX price chart
**2. Token Selection**: Choose input token (ETH, USDC, USDT) and amount
**3. Wallet Connection**: Optional - can paste address or connect wallet via AppKit
**4. Quote Generation**: Real-time pricing with OTC discount calculations
**5. Transaction Execution**: Backend handles blockchain operations via Circular Protocol
**6. Status Tracking**: Real-time transaction status updates

### **Key Features Working Now**

✅ **Wallet Connection**: Reown AppKit with multi-wallet support
✅ **Price Feeds**: Real-time CIRX pricing from multiple exchanges  
✅ **OTC Calculator**: Discount tier calculations based on trade size
✅ **Backend API**: Full REST API with Circular Protocol integration
✅ **Error Handling**: Comprehensive error boundaries and user feedback
✅ **SSR/SPA**: Works in both server-side and single-page app modes
✅ **Development Environment**: Clean, warning-free development experience
✅ **Security**: Environment-based configuration, no hardcoded secrets

### **Production Deployment**

- **Frontend**: Cloudflare Pages (static site generation)
- **Backend**: Docker containers with PHP-FPM and Nginx
- **Database**: PostgreSQL with automated migrations
- **Monitoring**: Health checks and error reporting integration

## Technology Stack

- **Backend**: PHP 8.2 with Laravel-style architecture
- **Frontend**: Nuxt.js 3, Vue.js, Nuxt UI (Tailwind CSS)  
- **Web3 Integration**: Viem + Wagmi (Ethereum), Solana Wallet Adapter
- **Blockchain Integration**: Circular Protocol APIs for all blockchain operations
- **Database**: PostgreSQL (production), SQLite (testing)
- **Version Control**: Jujutsu (jj) in colocated mode with Git compatibility
- **Deployment**: Cloudflare Pages (frontend), Docker (backend)
- **Testing**: Playwright (E2E), PHPUnit (backend), comprehensive test suite
- **Package Management**: Nix Flakes for reproducible development environment

## Development Commands

### Backend Development

```bash
# From backend directory  
nix run nixpkgs#composer -- install  # Install PHP dependencies
nix run nixpkgs#php -- -S localhost:18423 public/index.php  # Start development server
nix run nixpkgs#php -- vendor/bin/phpunit        # Run all tests
nix run nixpkgs#php -- vendor/bin/phpunit --group=integration  # Run integration tests
nix run nixpkgs#php -- vendor/bin/phpunit --configuration=phpunit.e2e.xml  # Run E2E tests

# Database operations
nix run nixpkgs#php -- migrate.php               # Run database migrations
```

### Frontend Development

```bash
# From UI directory (/uniswapv3clone/ui/)
npm run dev                   # Start Nuxt development server (localhost:3000)
npm run build                 # Build for production (Cloudflare Pages)
npm run generate              # Generate static site
npm run preview               # Preview production build locally

# Cloudflare deployment
wrangler pages deploy .output/public  # Deploy to Cloudflare Pages
```

## Architecture

### Backend API Layer (`/backend/src/`)
- **Controllers**: API endpoint handlers for swap transactions
- **Services**: 
  - `CirxTransferService.php` - Handles CIRX token transfers via Circular Protocol
  - `PaymentVerificationService.php` - Verifies blockchain payments
  - `BlockchainApiClient.php` - Circular Protocol integration
- **Models**: Database models for transactions and tracking
- **Workers**: Background processing for payment verification and transfers
- **Blockchain Integration**: Via Circular Protocol APIs, not direct smart contracts

### Frontend Layer (`/ui/`)
- **Framework**: Nuxt.js 3 with Vue.js components
- **UI Library**: Nuxt UI (built on Tailwind CSS)
- **Structure**: File-based routing with pages/, components/, layouts/
- **Phase 1 Implementation**: 
  - Dual-tab OTC interface (liquid/vested purchases)
  - Wallet integration (MetaMask, Phantom, WalletConnect)
  - CIRX token swaps with discount calculator
  - Vesting dashboard for OTC positions
  - Matcha/Jupiter inspired layout (form + chart)
- **Deployment**: Configured for Cloudflare Pages with edge computing

### Testing Structure
- **Backend**: `/tests/` - PHPUnit with Unit, Integration, and E2E test suites
- **Frontend**: Playwright E2E testing with comprehensive browser coverage

## NixOS Development Environment

This project runs on **NixOS with Nix Flakes** for reproducible development environments. All development commands use `nix run` as the primary approach:

```bash
# General pattern for any package
nix run nixpkgs#<package> -- <command>

# Common examples
nix run nixpkgs#php -- -v                    # Check PHP version
nix run nixpkgs#php -- -S localhost:8080 .   # Start PHP dev server
nix run nixpkgs#composer -- install          # Install PHP dependencies
nix run nixpkgs#python3 -- script.py         # Run Python scripts
nix run nixpkgs#curl -- -s http://localhost  # Make HTTP requests
nix run nixpkgs#jq -- '.key' data.json       # Parse JSON data

# Testing Telegram notifications
nix run nixpkgs#php -- test_telegram_direct.php  # Test Telegram setup
```

**Benefits of NixOS + Nix Flakes:**
- ✅ **No global package conflicts** - packages are isolated per-project
- ✅ **Reproducible builds** - identical environment across all machines  
- ✅ **Deterministic versions** - exact package versions specified in flakes
- ✅ **Clean system** - no leftover packages or dependencies after development
- ✅ **Instant availability** - any package from nixpkgs available on-demand

## Critical Development Principles

### 🔍 Look Before You Leap Protocol

**ALWAYS verify existing state before making changes:**

1. **Process Discovery**:
   ```bash
   # Check for running processes before starting new ones
   ps aux | grep -E "(npm|node|nuxt|php)" 
   netstat -tlnp | grep -E ":(3000|3001|8080|5432)" # Check occupied ports
   ```

2. **File System Analysis**:
   ```bash
   # Check if files/configs already exist before creating
   ls -la config/ plugins/ components/
   find . -name "*.config.*" -o -name "*adapter*" -o -name "*wagmi*"
   ```

3. **Code Duplication Detection**:
   ```bash
   # Search for existing logic before implementing
   rg "createAppKit|WagmiAdapter|useAppKit" --type ts --type js --type vue
   rg "function connectWallet|const connectWallet" 
   ```

4. **Dependency Verification**:
   ```bash
   # Check existing imports and configs
   rg "@reown|@wagmi|wagmi" package.json
   rg "import.*wagmi|import.*reown" --type ts --type js
   ```

5. **Service Status Check**:
   ```bash
   # Verify what's already running
   curl -s http://localhost:3000 > /dev/null && echo "Port 3000 occupied"
   curl -s http://localhost:8080/api/v1/health > /dev/null && echo "Backend running"
   ```

**Pre-Action Checklist**:
- [ ] Read existing files in target directory
- [ ] Search for similar logic/patterns already implemented  
- [ ] Check if services/processes are already running
- [ ] Verify imports and dependencies don't conflict
- [ ] Ensure no duplicate configurations exist

**Implementation Rule**: Only create/modify if verification shows it's needed or different from existing implementation.

## Development Workflow

### Jujutsu (jj) Version Control Setup

This project uses **Jujutsu (jj)** in colocated mode for version control, providing a modern change-centric workflow while maintaining Git compatibility.

#### Initial Setup
```bash
# Initialize jj in existing Git repository (colocated mode)
jj git init --colocate

# Or when cloning
jj git clone --colocate <repository-url>

# Configure user information
jj config set --user user.name "Your Name" 
jj config set --user user.email "your.email@example.com"
```

#### Daily Development Commands
```bash
# Check status and view changes
jj st                          # Show working directory status
jj diff                        # Show current changes
jj log                         # View commit history with smart defaults

# Working with changes
jj new                         # Finalize current change, start new one
jj describe -m "Add feature"   # Add description to current change

# Sync with remote
jj git fetch                   # Update from remote
jj git push --allow-new        # Push new changes

# View your work
jj log -r "mine()"            # Show only your changes
jj log -r "::@"               # Show stack leading to current change
```

#### Bookmark Management (Replaces Git Branches)
```bash
# Create and manage bookmarks for features
jj bookmark create feature-name -r @-     # Create bookmark for previous change
jj bookmark create hotfix -r main         # Create bookmark from main
jj bookmark list                          # List all bookmarks
jj bookmark track main@origin             # Track remote bookmarks

# Switch between work
jj edit -r feature-name                   # Switch to bookmark
jj edit -r main                           # Switch to main
```

#### Backend Development Workflow
```bash
# Start new API feature
jj new -r main
jj describe -m "Add transaction status endpoint"
# Edit backend in backend/src/
cd backend && nix run nixpkgs#php -- vendor/bin/phpunit     # Run tests

# Move to frontend work
jj new
jj describe -m "Add status tracking UI"  
# Edit frontend in ui/
cd ui && npm run dev                      # Test frontend

# Create feature bookmark when ready
jj bookmark create transaction-status -r @-
jj git push --allow-new
```

#### Stacked Changes Workflow
```bash
# Create a stack of related changes
jj new -r main                            # Foundation change
jj describe -m "Add liquidity pool model"
# Implement pool model

jj new                                    # Build on previous change  
jj describe -m "Add pool factory"
# Implement factory

jj new                                    # Build on factory
jj describe -m "Add swap router"
# Implement router

# Push entire stack for review
jj bookmark create liquidity-system -r @-
jj git push --allow-new
```

### Local Development Setup
1. **Initialize jj**: `jj git init --colocate` (if not already done)
2. **Start Backend**: `cd backend && nix run nixpkgs#php -- -S localhost:18423 public/index.php`
3. **Configure Database**: Set up PostgreSQL and run migrations
4. **Start Frontend**: `cd ui && npm run dev`
5. **Run Tests**: `nix run nixpkgs#php -- vendor/bin/phpunit` for backend, `npx playwright test` for E2E

### Testing Strategy
- **Backend API**: Use PHPUnit for unit, integration, and E2E testing
- **Performance**: Monitor API response times and database query optimization  
- **Frontend**: Vitest for unit tests, Playwright for E2E tests
- **Change Isolation**: Each jj change represents a testable unit of work

### Code Organization Conventions  
- **PHP Backend**: Follow PSR standards and Laravel-style architecture
- **Nuxt.js**: File-based routing, component structure, and composables pattern
- **Testing**: Comprehensive E2E testing with Playwright and PHPUnit
- **Changes**: One logical feature/fix per jj change for clean history

### Deployment Strategy
- **Backend**: Deploy via Docker containers to production infrastructure
- **Frontend**: Automatic deployment to Cloudflare Pages via jj bookmark integration
- **Environment**: Separate staging and production environments
- **CI/CD**: Use `jj git push` for pipeline compatibility with E2E testing

## Key Implementation Notes

### Backend API Development
- Uses PHP 8.2 with modern features and strict typing
- Circular Protocol integration provides reliable blockchain operations
- Comprehensive testing with PHPUnit including E2E blockchain testing
- Production-ready with proper error handling and monitoring

### Frontend Development
- **Nuxt.js 3**: Modern Vue.js framework with SSR/SSG capabilities
- **Cloudflare Pages**: Edge deployment with fast global CDN
- **Nuxt UI**: Professional UI components with Tailwind CSS
- **Ready for Web3**: Structured for wallet connection and contract interaction
- **Performance**: Optimized for Core Web Vitals and SEO

### Development Status (Phase 1 OTC Platform)
- ✅ Backend API with Circular Protocol integration
- ✅ Comprehensive E2E testing framework implemented
- ✅ Production-ready deployment configuration
- ✅ Phase 1 OTC platform completed
- ✅ Dual-tab interface (liquid/vested) implemented
- ✅ Complete wallet integration (MetaMask, Phantom)
- ✅ Transaction tracking and status monitoring
- ✅ Real blockchain integration via Circular Protocol

## Circular CIRX OTC Platform Specifications

### Core Requirements (Phase 1)
- **Dual Purchase Options**: 
  - Liquid tokens (immediate delivery at market rate)
  - OTC vested tokens (6-month lockup with 5-15% discount)
- **Supported Tokens**: ETH, USDC, USDT → CIRX swaps
- **Wallet Integration**: MetaMask, Phantom, WalletConnect (no Circular wallet needed)
- **UI Design**: Matcha/Jupiter inspired (form + chart layout)

### Technical Implementation
- **API Integration**: Circular Protocol for all blockchain operations
- **Backend Services**: Transaction processing, payment verification, CIRX transfers
- **Frontend**: Dual-tab interface in `/ui/pages/swap.vue`
- **Transaction Tracking**: Real-time status updates via polling
- **Discount Tiers**: $1K-$10K (5%), $10K-$50K (8%), $50K+ (12%)

### Key Features
- **No Wallet Connection Required**: Users can paste wallet addresses directly
- **Real-time Pricing**: Live quotes with slippage protection
- **Transaction History**: Basic swap and vesting position tracking
- **Error Handling**: Comprehensive edge case management
- **Mobile Responsive**: Optimized for all device sizes

### Development Workflow
1. **Week 1-2**: ✅ Backend API + Circular Protocol integration
2. **Week 3-4**: ✅ Frontend dual-tab interface + wallet integration  
3. **Week 5-6**: ✅ E2E testing framework + comprehensive test coverage
4. **Week 7-8**: ✅ Production deployment + monitoring setup

## Jujutsu Configuration

### Recommended .jjconfig.toml Setup
Create `.jjconfig.toml` in your home directory with smart contract development optimizations:

```toml
[user]
name = "Your Name"
email = "your.email@example.com"

[ui]
# Enhanced diff display for Solidity files
diff.tool = ["code", "--wait", "--diff", "$left", "$right"]

[revsets]
# Custom queries for smart contract development
"contracts" = "file_type:sol"
"frontend" = "file_type:js | file_type:ts | file_type:vue"
"tests" = "file_type:test.js | file_type:test.ts | file_type:t.sol"

[aliases]
# Backend development aliases
"backend-test" = ["!cd", "backend", "&&", "nix", "run", "nixpkgs#php", "--", "vendor/bin/phpunit"]
"backend-serve" = ["!cd", "backend", "&&", "nix", "run", "nixpkgs#php", "--", "-S", "localhost:18423", "public/index.php"]
"e2e-test" = ["!./scripts/run-e2e-tests.sh"]
"migrate" = ["!cd", "backend", "&&", "nix", "run", "nixpkgs#php", "--", "migrate.php"]

# Frontend development aliases  
"dev-ui" = ["!cd", "ui", "&&", "npm", "run", "dev"]
"build-ui" = ["!cd", "ui", "&&", "npm", "run", "build"]
"test-ui" = ["!cd", "ui", "&&", "npm", "run", "test"]

# Common jj workflow shortcuts
"sync" = ["git", "fetch"]
"stack" = ["log", "-r", "::@"]
"mine" = ["log", "-r", "mine()"]
"recent" = ["log", "-r", "@---.."]

[git]
# Auto-create local bookmarks for pushed changes
auto-local-bookmark = true

[template]
# Custom log template showing bookmarks and descriptions
log = '''
commit_id.short() ++ " " ++
if(bookmarks, bookmarks.join(" ") ++ " ") ++
if(description, description.first_line()) ++
"\n"
'''
```

### Colocated Workflow Best Practices

1. **Use jj for Changes, Git for Compatibility**:
   - All development work through `jj` commands
   - Use `git` commands only when needed for tool compatibility
   - IDE and CI/CD systems work transparently with colocated setup

2. **Change Granularity**:
   - One logical feature per change (contract + tests + frontend)
   - Use stacked changes for complex features spanning multiple contracts
   - Keep changes focused and reviewable

3. **Bookmark Strategy**:
   - Create bookmarks only when ready to push/review
   - Use descriptive names: `pool-factory-v2`, `swap-optimization`, `frontend-wallet-integration`
   - Track upstream bookmarks: `jj bookmark track main@origin`

4. **Conflict Resolution**:
   - Conflicts can be committed and resolved later
   - Use `jj resolve` for interactive conflict resolution
   - Automatic rebasing reduces merge conflicts

5. **Team Integration**:
   - Teammates can use Git normally while you use jj
   - `jj git push` creates standard Git commits
   - CI/CD pipelines work without modification

## External Resources

- **Jujutsu Documentation**: https://jj-vcs.github.io/jj/latest/
- **PHP Documentation**: https://www.php.net/docs.php
- **Nuxt.js Documentation**: https://nuxt.com/docs
- **Nuxt UI Documentation**: https://ui.nuxt.com/
- **Playwright Testing**: https://playwright.dev/
- **Circular Protocol**: https://circular-protocol.gitbook.io/
- **Cloudflare Pages**: https://pages.cloudflare.com/
## PRP Framework Integration

This project now includes the PRP (Product Requirements and Planning) framework for enhanced AI-assisted development.

### Auto Plan Mode

The project is configured with Auto Plan Mode for safe development:
- **Planning First**: Claude creates detailed plans before implementation
- **Incremental Changes**: Small, testable changes with validation
- **API Safety**: Comprehensive testing and error handling
- **User Approval**: Major changes require explicit approval

### PRP Directory Structure

- `tasks/` - Task management and project planning
  - `completed/` - Finished tasks and features
  - `backlog/` - Planned features and improvements
- `ai_docs/` - AI-generated documentation and patterns
- `quality/` - Code quality metrics and reports
- `security/` - Security audit results and configurations
- `ux/` - User experience research and designs

### AI-Assisted Workflow

1. **Feature Planning**: Ask Claude to create a PRD for new features
2. **Implementation**: Use Auto Plan Mode for safe development
3. **Testing**: Automated testing after each change
4. **Documentation**: AI-generated docs in `ai_docs/`

### Commands

```bash
# Start development with PRP
./start-prp.sh

# Create a new feature plan
claude "Create a PRD for [feature description]"

# Implement with Auto Plan Mode
claude "Implement the user authentication system using Auto Plan Mode"
```

### Integration Benefits

- **Safer Development**: Auto Plan Mode prevents breaking changes
- **Better Documentation**: AI-generated docs and patterns
- **Structured Planning**: Task management and feature tracking
- **Quality Focus**: Built-in quality and security checks
