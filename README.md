# CIRX Swap Platform

A comprehensive OTC (Over-The-Counter) trading platform for CIRX tokens built on UniswapV4 infrastructure with dual-tab interface for liquid and vested purchases.

## 🚀 Quick Start

### Prerequisites
- **Node.js 18+** and **npm**
- **PHP 8.2+** and **Composer** (for backend)
- **Docker & Docker Compose** (for E2E testing)
- **PostgreSQL** (for production backend)

### Development Setup

1. **Clone the repository**:
   ```bash
   git clone <repository-url>
   cd cirx-swap
   ```

2. **Start the frontend**:
   ```bash
   cd ui
   npm install
   npm run dev
   ```
   Frontend will be available at: http://localhost:3000

3. **Start the backend** (optional):
   ```bash
   cd backend
   composer install
   cp .env.example .env
   # Configure your .env file
   php -S localhost:8080 public/index.php
   ```
   Backend API available at: http://localhost:8080

4. **Start the indexer** (optional):
   ```bash
   cd indexer
   npm install
   npm start  # Start blockchain event indexer
   ```

### 🧪 E2E Testing Quick Start

Run the complete E2E test suite:

```bash
# Run all tests (frontend + backend + integration)
./scripts/run-e2e-tests.sh

# Run only frontend tests
./scripts/run-e2e-tests.sh --frontend-only

# Run only backend tests  
./scripts/run-e2e-tests.sh --backend-only

# Run with detailed logs
./scripts/run-e2e-tests.sh --logs
```

**Individual test suites:**
```bash
# Frontend E2E tests
cd ui && npx playwright test

# Backend E2E tests
cd backend && php vendor/bin/phpunit --configuration=phpunit.e2e.xml

# List available tests
cd ui && npx playwright test --list
```

## 🏗️ Architecture

### Technology Stack

- **Frontend**: Nuxt.js 3, Vue.js, Nuxt UI (Tailwind CSS)
- **Backend**: PHP 8.2, Laravel-style architecture  
- **Web3**: Viem + Wagmi (Ethereum), Solana Wallet Adapter
- **Database**: PostgreSQL (production), SQLite (testing)
- **Testing**: Playwright (E2E), PHPUnit (backend)
- **Deployment**: Cloudflare Pages (frontend), Docker (backend)
- **Integration**: Circular Protocol API for blockchain interactions

### Project Structure

```
/
├── ui/                     # Frontend application (Nuxt.js)
├── backend/                # Backend API (PHP)
├── indexer/               # Blockchain event indexer (Node.js)
├── scripts/               # Build and deployment scripts
├── docker-compose.e2e.yml # E2E testing environment
└── README.E2E.md         # Detailed E2E testing guide
```

## 💼 Core Features

### Phase 1 (Current)
- ✅ **Dual Purchase Options**: Liquid (immediate) vs OTC (vested with discount)
- ✅ **Multi-Token Support**: ETH, USDC, USDT → CIRX swaps
- ✅ **Wallet Integration**: MetaMask, Phantom, WalletConnect
- ✅ **Discount Tiers**: 5-15% discounts based on purchase amount
- ✅ **Transaction Tracking**: Real-time status updates
- ✅ **Cross-Platform**: Desktop and mobile responsive

### Phase 2 (Planned)
- 🔄 **Vesting Dashboard**: 6-month linear unlock tracking
- 🔄 **Advanced Charts**: TradingView integration
- 🔄 **Batch Processing**: Multiple swaps optimization
- 🔄 **Advanced Discounting**: Dynamic pricing algorithms

## 🛠️ Development Commands

### Frontend (Nuxt.js)
```bash
cd ui/
npm run dev          # Development server
npm run build        # Production build
npm run generate     # Static site generation
npm run preview      # Preview production build
npm run test         # Run tests
```

### Backend (PHP)
```bash
cd backend/
composer install     # Install dependencies
php -S localhost:8080 public/index.php  # Development server
php vendor/bin/phpunit                   # Run tests
```

### Blockchain Indexer (Node.js)
```bash
cd indexer/
npm install         # Install dependencies
npm start           # Start event listener
npm run init-db     # Initialize database
```

### E2E Testing
```bash
# Complete test suite
./scripts/run-e2e-tests.sh

# Development testing
cd ui/ && npx playwright test              # Frontend E2E
cd backend/ && php vendor/bin/phpunit --configuration=phpunit.e2e.xml  # Backend E2E

# Test environment management
docker-compose -f docker-compose.e2e.yml up    # Start E2E environment
docker-compose -f docker-compose.e2e.yml down  # Stop E2E environment
```

## 📋 Configuration

### Environment Variables

**Frontend (.env)**:
```bash
NUXT_PUBLIC_API_BASE_URL=http://localhost:8080
NUXT_PUBLIC_CHAIN_ID=11155111
```

**Backend (.env)**:
```bash
APP_ENV=local
DB_CONNECTION=pgsql
DB_HOST=localhost
DB_DATABASE=cirx_swap
SEPOLIA_RPC_URL=https://sepolia.infura.io/v3/YOUR_KEY
```

**E2E Testing**:
```bash
export SEPOLIA_RPC_URL="https://sepolia.infura.io/v3/YOUR_INFURA_KEY"
export E2E_TEST_SEED_PHRASE="test test test test test test test test test test test junk"
```

## 🧪 Testing

The project includes comprehensive testing at multiple levels:

### Frontend Testing
- **E2E Tests**: 165+ test cases across 5 browser configurations
- **Wallet Integration**: MetaMask, Phantom connection flows
- **User Journeys**: Complete swap transactions end-to-end
- **Error Scenarios**: Network failures, API errors, edge cases
- **Performance**: Load times, memory usage, concurrent users

### Backend Testing  
- **Unit Tests**: Individual component testing
- **Integration Tests**: API endpoint validation
- **E2E Tests**: Complete blockchain transaction flows
- **Real Blockchain**: Sepolia testnet integration

### Blockchain Integration Testing
- **API Tests**: Circular Protocol integration testing
- **Event Indexing**: Real-time blockchain event processing
- **Transaction Monitoring**: Payment verification and status tracking

## 🚀 Deployment

### Frontend (Cloudflare Pages)
```bash
cd ui/
npm run build
# Deploy dist/ to Cloudflare Pages
```

### Backend (Docker)
```bash
cd backend/
docker build -t cirx-backend .
docker run -p 8080:8080 cirx-backend
```

### Blockchain Indexer
```bash
cd indexer/
npm run build
# Deploy to your preferred hosting platform
```

## 📊 Performance Benchmarks

**Expected Performance:**
- Page Load Time: < 3 seconds
- API Response Time: < 2 seconds  
- Payment Verification: < 30 seconds
- CIRX Transfer: < 60 seconds
- End-to-End Swap: < 120 seconds

## 🔧 Troubleshooting

### Common Issues

1. **Frontend won't start**:
   ```bash
   rm -rf node_modules package-lock.json
   npm install
   ```

2. **Backend database errors**:
   ```bash
   php artisan migrate:fresh --seed
   ```

3. **E2E tests failing**:
   ```bash
   ./scripts/run-e2e-tests.sh --logs
   # Check service logs for details
   ```

4. **Wallet connection issues**:
   - Ensure MetaMask/Phantom is installed
   - Check browser console for errors
   - Verify network configuration

### Debug Mode
```bash
# Enable verbose logging
export APP_DEBUG=true
export LOG_LEVEL=debug

# Frontend debug
npm run dev -- --debug

# Backend debug
php -S localhost:8080 public/index.php (with APP_DEBUG=true)
```

## 📚 Documentation

- **[E2E Testing Guide](README.E2E.md)**: Comprehensive testing documentation
- **[Backend API](backend/docs/)**: API reference and integration guide
- **[Blockchain Integration](backend/BLOCKCHAIN_INTEGRATION.md)**: Circular Protocol integration
- **[Indexer Documentation](indexer/README.md)**: Event indexing and monitoring

## 🛡️ Security

### Development Security
- Test wallets use separate seed phrases
- No mainnet credentials in repositories
- Environment variables for sensitive configuration
- Rate limiting and input validation

### Production Security  
- HTTPS enforcement
- API authentication and authorization
- Database encryption
- Audit logging
- Regular security updates

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/amazing-feature`
3. Run tests: `./scripts/run-e2e-tests.sh`
4. Commit changes: `git commit -m 'Add amazing feature'`
5. Push to branch: `git push origin feature/amazing-feature`
6. Open a Pull Request

### Development Guidelines
- Follow existing code style and patterns
- Write tests for new features
- Update documentation as needed
- Ensure all E2E tests pass before submitting

## 📞 Support

- **Issues**: Open GitHub issues for bugs and feature requests
- **Discussions**: Use GitHub Discussions for questions
- **E2E Testing**: See [README.E2E.md](README.E2E.md) for detailed testing guidance

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.

---

## Architecture Notes

This project uses **API-first integration** with the Circular Protocol rather than direct smart contract deployment:

- **Frontend** communicates with **Backend API**
- **Backend** integrates with **Circular Protocol APIs**
- **Indexer** monitors blockchain events for real-time updates
- **E2E Tests** validate the complete user journey

This architecture provides better reliability, security, and user experience compared to direct smart contract interaction.