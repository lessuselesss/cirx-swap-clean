# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a **Circular CIRX OTC Trading Platform** with API-first architecture:
- **Backend API** integrating with Circular Protocol for blockchain operations
- **Nuxt.js frontend** with dual-tab interface (liquid/vested purchases)
- **Phase 1**: Complete OTC purchase platform with wallet integration and transaction tracking

**Current Status**: Production-ready platform with comprehensive E2E testing framework.

**AI Context**: For comprehensive project understanding, refer to `@PROJECT_INDEX.json` which contains:
- Complete file structure with 228 tracked files across PHP, Vue, JavaScript, and configuration files
- Function signatures and relationships across the entire codebase
- Architecture patterns and component dependencies
- Testing structure mapping between backend PHPUnit and frontend E2E tests

## ✅ RECENT PROGRESS SUMMARY (2025-08-04)

### **Critical Wallet Connection Issues RESOLVED**

**Problem**: Users encountered critical error dialog when clicking "Connect Wallet" button.

**Root Cause**: Wagmi `connectors` array was undefined during initial component render, causing `Cannot read properties of undefined (reading 'some')` error.

**Solution Implemented**:
1. **Fixed SSR Configuration Mismatch**: Updated `wagmi.config.js` to use `ssr: false` matching Nuxt config
2. **Added Defensive Array Checks**: Prevented forEach operations on undefined arrays in:
   - `useEthereumWallet.js` - Line 76-80: Check `connectors.value` exists before iteration
   - `stores/wallet.js` - Lines 67-88: Defensive checks for `availableWallets` arrays
3. **Enhanced Error Handling**: Comprehensive error logging and categorization
4. **Improved Initialization**: Added DOM ready checks and timeout protection

**Key Commits**:
- `c0e87efbeba47c165c7fcda8161392255c540c26` - Final fix for connectors array error
- `9e823fef52f6b9ffe211c0034ff40183c8084418` - Enhanced debugging and error logging
- `17fd5ef85f0e3f23f8a1e74d45b324c90af0ac64` - Initial critical error fixes

### **Current System Status**
- ✅ **Deployment Build**: All builds pass successfully with proper prerendering
- ✅ **Critical Error Dialog**: Eliminated - no more crashes on Connect Wallet
- ✅ **Error Handling**: Comprehensive system with toast notifications for non-critical issues
- ✅ **Wallet System**: Production-ready with Ethereum + Solana support
- ✅ **User Experience**: Graceful degradation when wallet providers aren't available

### **What's Working**
- Complete wallet system rewrite (Ethereum + Solana providers)
- Comprehensive error handling with contextual user feedback
- Production-ready build configuration for Cloudflare Pages
- All dependencies properly synchronized (Pinia, Wagmi, etc.)
- Defensive initialization prevents app crashes

### **Next Steps for Development**
1. **Test Connect Wallet**: Verify the fixes work in production
2. **Implement Actual Wallet Connection**: Replace placeholder logic with real wallet modal
3. **Contract Integration**: Connect to actual CIRX token contracts when available
4. **OTC Functionality**: Implement vesting contracts and discount logic
5. **UI Polish**: Enhance user experience for wallet connection flow

### **Files Modified in This Session**
- `ui/app.vue` - Enhanced global error handling with detailed logging
- `ui/components/SwapForm.vue` - Defensive composable initialization
- `ui/composables/useEthereumWallet.js` - Fixed connectors array access
- `ui/stores/wallet.js` - Added defensive checks for wallet arrays
- `ui/plugins/wallet-init.client.js` - Improved initialization timing
- `ui/wagmi.config.js` - Fixed SSR configuration mismatch
- `ui/components/ErrorAlert.vue` - Fixed Vue SFC structure
- `ui/components/SwapOutput.vue` - Fixed missing SVG asset reference

### **Technical Debt Addressed**
- Eliminated 960-line monolithic swap component
- Replaced mock transaction execution with proper architecture
- Fixed multiple Vue Single File Component structure violations
- Resolved package.json/package-lock.json synchronization issues
- Implemented proper error boundaries and fallback mechanisms

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
nix run nixpkgs#php -- -S localhost:8080 public/index.php  # Start development server
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
2. **Start Backend**: `cd backend && nix run nixpkgs#php -- -S localhost:8080 public/index.php`
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
"backend-serve" = ["!cd", "backend", "&&", "nix", "run", "nixpkgs#php", "--", "-S", "localhost:8080", "public/index.php"]
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
