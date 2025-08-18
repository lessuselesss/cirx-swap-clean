FROM node:18-alpine

# Set working directory
WORKDIR /app

# Install dependencies for Playwright
RUN apk add --no-cache \
    chromium \
    firefox \
    webkit2gtk \
    curl

# Copy package files
COPY package*.json ./

# Install dependencies
RUN npm ci --only=production

# Copy application code
COPY . .

# Build the application
RUN npm run build

# Create directories for test results
RUN mkdir -p test-results playwright-report

# Set environment variables
ENV NODE_ENV=test
ENV NUXT_HOST=0.0.0.0
ENV NUXT_PORT=3000

# Expose port
EXPOSE 3000

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD curl -f http://localhost:3000/ || exit 1

# Start the application
CMD ["npm", "run", "preview"]