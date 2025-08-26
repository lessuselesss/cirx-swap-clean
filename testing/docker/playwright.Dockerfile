FROM mcr.microsoft.com/playwright:v1.40.0-focal

# Set working directory
WORKDIR /app

# Copy package files
COPY package*.json ./

# Install dependencies
RUN npm ci

# Copy test files and configuration
COPY playwright.config.ts ./
COPY e2e/ ./e2e/
COPY tsconfig.json ./

# Create directories for test results
RUN mkdir -p test-results playwright-report

# Set environment variables for Playwright
ENV PLAYWRIGHT_BROWSERS_PATH=/ms-playwright
ENV NODE_ENV=test

# Install browsers if not already available
RUN npx playwright install --with-deps

# Health check to ensure Playwright is ready
HEALTHCHECK --interval=30s --timeout=10s --start-period=30s --retries=3 \
    CMD npx playwright --version || exit 1

# Default command to run tests
CMD ["npx", "playwright", "test", "--reporter=html"]