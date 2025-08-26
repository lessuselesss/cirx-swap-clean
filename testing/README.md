# Testing Directory

This directory contains all testing-related configurations, scripts, and Docker files for the CIRX Swap platform.

## Structure

```
testing/
├── docker/                    # Docker configurations for testing
│   ├── backend.e2e.Dockerfile # Backend E2E testing container
│   ├── frontend.e2e.Dockerfile # Frontend E2E testing container
│   └── playwright.Dockerfile  # Playwright test runner container
├── docker-compose.e2e.yml     # Complete E2E testing stack
├── e2e/                       # E2E test files
│   └── frontend/              # Frontend E2E tests (Playwright)
├── integration/               # Integration test files
├── scripts/                   # Test scripts and utilities
│   ├── test-api.php          # API testing script
│   ├── test-auto-worker.js   # Worker testing script
│   ├── test_telegram.php     # Telegram notification test
│   ├── run-tests.php         # Test runner
│   └── validate-e2e-setup.sh # E2E setup validation
├── data/                      # Test data and configurations
│   ├── .env.e2e              # E2E environment configuration
│   └── e2e-setup.sql         # E2E database setup
├── phpunit.e2e.xml           # PHPUnit E2E configuration
├── vitest.config.ts          # Vitest configuration
└── playwright.config.ts      # Playwright configuration (if exists)
```

## Running Tests

### E2E Tests with Docker

From the testing directory:
```bash
# Start the complete E2E stack
docker-compose -f docker-compose.e2e.yml up -d

# Run PHPUnit E2E tests
docker-compose -f docker-compose.e2e.yml run phpunit-e2e

# Run Playwright tests
docker-compose -f docker-compose.e2e.yml run playwright-e2e
```

### Backend Tests

From the backend directory:
```bash
# Run all tests
php vendor/bin/phpunit

# Run E2E tests only
php vendor/bin/phpunit --configuration=../testing/phpunit.e2e.xml
```

### Frontend Tests

From the ui directory:
```bash
# Run unit tests with Vitest
npm run test

# Run E2E tests with Playwright
npx playwright test
```

## Test Scripts

- `scripts/test-api.php` - Direct API testing
- `scripts/test-auto-worker.js` - Worker functionality testing
- `scripts/test_telegram.php` - Telegram notification testing
- `scripts/validate-e2e-setup.sh` - Validates E2E environment setup