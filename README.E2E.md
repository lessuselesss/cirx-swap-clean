# E2E Testing Guide for CIRX Swap Platform

## Overview

This document provides comprehensive guidance for running End-to-End (E2E) tests for the CIRX OTC swap platform. The E2E testing framework validates the complete user journey from frontend interaction through backend API processing to blockchain integration.

## Architecture

### Testing Layers

```
┌─────────────────────────────────────────────────────────┐
│                    E2E Test Layers                     │
├─────────────────────────────────────────────────────────┤
│ Layer 1: Frontend User Journey (Playwright)            │
│ ├─ Wallet connection flows                            │
│ ├─ Swap form interactions                             │
│ ├─ Transaction status tracking                        │
│ └─ Error handling UX                                  │
├─────────────────────────────────────────────────────────┤
│ Layer 2: Backend API Integration (PHPUnit)             │
│ ├─ Complete swap transactions                          │
│ ├─ Payment verification flows                          │
│ ├─ Worker pipeline testing                            │
│ └─ Blockchain integration                              │
├─────────────────────────────────────────────────────────┤
│ Layer 3: Cross-Stack Integration (Docker Compose)      │
│ ├─ Frontend → API → Blockchain flows                   │
│ ├─ Real wallet integration testing                    │
│ ├─ End-to-end transaction completion                  │
│ └─ Performance under load                             │
└─────────────────────────────────────────────────────────┘
```

### Components

- **Frontend Tests**: Playwright-based browser automation
- **Backend Tests**: PHPUnit-based API and blockchain integration tests
- **Infrastructure**: Docker Compose orchestration with PostgreSQL, Redis, and Nginx
- **Monitoring**: Prometheus and Grafana for test observability
- **Reporting**: Comprehensive HTML and JSON test reports

## Quick Start

### Prerequisites

1. **Docker & Docker Compose**: Latest versions installed
2. **Environment Variables**: Set up required configuration
3. **Test Wallets**: Funded Sepolia testnet wallets (optional for basic tests)

### Basic Test Execution

```bash
# Run all E2E tests
./scripts/run-e2e-tests.sh

# Run only backend tests
./scripts/run-e2e-tests.sh --backend-only

# Run only frontend tests
./scripts/run-e2e-tests.sh --frontend-only

# Run tests with service logs
./scripts/run-e2e-tests.sh --logs
```

## Environment Setup

### Required Environment Variables

```bash
# Blockchain configuration (optional for basic tests)
export SEPOLIA_RPC_URL="https://sepolia.infura.io/v3/YOUR_INFURA_KEY"

# Test wallet configuration (optional)
export E2E_TEST_SEED_PHRASE="test test test test test test test test test test test junk"
```

### Optional Configuration

```bash
# Advanced testing features
export E2E_ENABLE_REAL_BLOCKCHAIN=true
export E2E_ENABLE_PERFORMANCE_TESTS=true
export E2E_ENABLE_MONITORING=true
```

## Test Categories

### 1. Frontend E2E Tests

Located in `ui/e2e/`, these tests validate the complete user experience:

#### Wallet Integration Tests (`wallet-integration.spec.ts`)
- MetaMask connection flows
- Phantom wallet integration
- Network switching
- Error handling for missing wallets

#### Swap Flow Tests (`swap-flow.spec.ts`)
- Complete USDC to CIRX OTC swaps
- ETH to CIRX liquid swaps
- Form validation and error handling
- API integration testing

#### Cross-Stack Integration (`frontend-backend-integration.spec.ts`)
- Real API communication
- Error handling and retries
- Rate limiting behavior
- Transaction status polling

#### Error Scenarios (`error-scenarios.spec.ts`)
- Network failure simulation
- API error handling
- Browser compatibility issues
- Concurrent user actions

#### Performance Tests (`performance.spec.ts`)
- Page load performance
- Form interaction response times
- Memory usage monitoring
- Network performance testing

### 2. Backend E2E Tests

Located in `backend/tests/E2E/`, these tests validate API and blockchain integration:

#### Complete Swap Flow Tests (`CompleteOTCSwapFlowTest.php`)
- Full USDC to CIRX swap pipeline
- ETH to CIRX with discount calculation
- Liquid vs OTC swap processing
- Concurrent swap handling

#### Error Scenario Tests (`ErrorScenarioTest.php`)
- Invalid input validation
- Blockchain RPC failures
- Worker timeout and retry logic
- Edge case payment amounts

#### Real Blockchain Tests (`RealBlockchainTest.php`)
- Actual Sepolia testnet transactions
- Gas estimation and fee calculation
- Network performance monitoring
- Wallet balance verification

## Configuration Files

### Docker Configuration

- `docker-compose.e2e.yml`: Complete E2E environment orchestration
- `backend/Dockerfile.e2e`: Backend service container
- `ui/Dockerfile.e2e`: Frontend service container
- `ui/Dockerfile.playwright`: Playwright test runner

### Test Configuration

- `ui/playwright.config.ts`: Playwright test configuration
- `backend/phpunit.e2e.xml`: PHPUnit E2E test configuration
- `backend/.env.e2e`: Backend environment variables
- `backend/database/e2e-setup.sql`: Database initialization

## Running Tests

### Development Workflow

1. **Start Development Environment**:
   ```bash
   # Start services for development
   docker-compose -f docker-compose.e2e.yml up -d postgres-e2e redis-e2e
   
   # Run backend locally
   cd backend && php -S localhost:8080 public/index.php
   
   # Run frontend locally
   cd ui && npm run dev
   ```

2. **Run Individual Test Suites**:
   ```bash
   # Frontend tests only
   cd ui && npx playwright test
   
   # Backend tests only
   cd backend && php vendor/bin/phpunit --configuration=phpunit.e2e.xml
   ```

### CI/CD Integration

The E2E tests integrate with GitHub Actions and other CI/CD platforms:

```yaml
# Example GitHub Actions workflow
- name: Run E2E Tests
  run: |
    ./scripts/run-e2e-tests.sh
  env:
    SEPOLIA_RPC_URL: ${{ secrets.SEPOLIA_RPC_URL }}
    E2E_TEST_SEED_PHRASE: ${{ secrets.E2E_TEST_SEED_PHRASE }}
```

## Test Data Management

### Database Setup

The E2E environment uses a dedicated PostgreSQL database with:
- Pre-configured test transactions
- Proper indexing for performance
- Cleanup procedures for test isolation

### Test Wallets

Test wallets are generated from a seed phrase:
- **Payment Wallet**: Used to send test payments
- **Recipient Wallet**: Receives CIRX tokens
- **Project Wallet**: Receives payment transactions

### Blockchain Integration

- **Sepolia Testnet**: Primary testing network
- **Test Tokens**: USDC and USDT test contracts
- **Gas Management**: Optimized for test execution

## Monitoring and Observability

### Metrics Collection

- **Test Execution Time**: Per test and overall suite timing
- **API Response Times**: Backend performance monitoring
- **Blockchain Interaction Time**: RPC call latency tracking
- **Error Rates**: Failure rate monitoring across components

### Reporting

Test reports are generated in multiple formats:
- **HTML Reports**: Visual test results with screenshots
- **JSON Reports**: Machine-readable test data
- **JUnit XML**: CI/CD integration format
- **Coverage Reports**: Code coverage analysis

### Grafana Dashboards

Monitor test execution with pre-configured dashboards:
- Test execution trends
- Performance metrics
- Error rate analysis
- Resource utilization

## Troubleshooting

### Common Issues

1. **Service Startup Failures**:
   ```bash
   # Check service logs
   docker-compose -f docker-compose.e2e.yml logs backend-e2e
   
   # Verify health checks
   curl -f http://localhost:8081/api/v1/health
   ```

2. **Test Timeouts**:
   ```bash
   # Increase timeout in playwright.config.ts
   timeout: 120000, // 2 minutes
   
   # Check for resource constraints
   docker stats
   ```

3. **Database Connection Issues**:
   ```bash
   # Reset database
   docker-compose -f docker-compose.e2e.yml down -v
   docker-compose -f docker-compose.e2e.yml up -d postgres-e2e
   ```

### Debug Mode

Enable verbose logging for debugging:

```bash
# Enable debug logging
export APP_DEBUG=true
export LOG_LEVEL=debug

# Run tests with detailed output
./scripts/run-e2e-tests.sh --logs
```

## Performance Benchmarks

### Expected Performance Thresholds

- **Page Load Time**: < 3 seconds
- **API Response Time**: < 2 seconds
- **Payment Verification**: < 30 seconds
- **CIRX Transfer**: < 60 seconds
- **End-to-End Flow**: < 120 seconds

### Load Testing

Simulate multiple concurrent users:

```bash
# Run performance tests
npx playwright test performance.spec.ts

# Monitor resource usage
docker stats --format "table {{.Container}}\t{{.CPUPerc}}\t{{.MemUsage}}"
```

## Security Considerations

### Test Data Isolation

- Dedicated test database with isolated schemas
- Test-only API keys and JWT secrets
- Sandboxed blockchain interactions

### Wallet Security

- Test wallets use separate seed phrases
- No mainnet credentials in test environment
- Automated cleanup of test data

## Contributing

### Adding New Tests

1. **Frontend Tests**:
   ```typescript
   // ui/e2e/new-feature.spec.ts
   import { test, expect } from '@playwright/test';
   
   test.describe('New Feature', () => {
     test('should work correctly', async ({ page }) => {
       // Test implementation
     });
   });
   ```

2. **Backend Tests**:
   ```php
   // backend/tests/E2E/NewFeatureTest.php
   <?php
   namespace Tests\E2E;
   
   class NewFeatureTest extends E2ETestCase
   {
       public function testNewFeature(): void
       {
           // Test implementation
       }
   }
   ```

### Test Guidelines

- **Descriptive Names**: Use clear, descriptive test names
- **Independent Tests**: Each test should be self-contained
- **Cleanup**: Always clean up test data
- **Documentation**: Comment complex test logic
- **Performance**: Consider test execution time

## Support

For issues with E2E testing:

1. Check the troubleshooting section above
2. Review service logs: `./scripts/run-e2e-tests.sh --logs`
3. Verify environment configuration
4. Consult the test reports in `reports/e2e/`

## Future Enhancements

Planned improvements to the E2E testing framework:

- **Visual Regression Testing**: Screenshot comparison
- **Mobile Device Testing**: Extended device coverage
- **API Load Testing**: High-volume transaction processing
- **Chaos Engineering**: Failure injection testing
- **Multi-Chain Testing**: Extended blockchain support