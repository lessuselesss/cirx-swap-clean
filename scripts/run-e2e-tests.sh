#!/bin/bash

# CIRX Swap E2E Test Runner
# This script orchestrates the complete E2E testing pipeline

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
COMPOSE_FILE="docker-compose.e2e.yml"
BACKEND_HEALTH_URL="http://localhost:8081/api/v1/health"
FRONTEND_HEALTH_URL="http://localhost:3001/"
TIMEOUT=300 # 5 minutes timeout

# Functions
log() {
    echo -e "${BLUE}[$(date '+%Y-%m-%d %H:%M:%S')] $1${NC}"
}

success() {
    echo -e "${GREEN}[$(date '+%Y-%m-%d %H:%M:%S')] ✓ $1${NC}"
}

warning() {
    echo -e "${YELLOW}[$(date '+%Y-%m-%d %H:%M:%S')] ⚠ $1${NC}"
}

error() {
    echo -e "${RED}[$(date '+%Y-%m-%d %H:%M:%S')] ✗ $1${NC}"
}

# Cleanup function
cleanup() {
    log "Cleaning up E2E test environment..."
    docker-compose -f $COMPOSE_FILE down --volumes --remove-orphans
    success "Cleanup completed"
}

# Setup trap for cleanup
trap cleanup EXIT

# Check prerequisites
check_prerequisites() {
    log "Checking prerequisites..."
    
    if ! command -v docker-compose &> /dev/null; then
        error "docker-compose is required but not installed"
        exit 1
    fi
    
    if ! command -v curl &> /dev/null; then
        error "curl is required but not installed"
        exit 1
    fi
    
    # Check for required environment variables
    if [ -z "$SEPOLIA_RPC_URL" ]; then
        warning "SEPOLIA_RPC_URL not set - some blockchain tests may be skipped"
    fi
    
    if [ -z "$E2E_TEST_SEED_PHRASE" ]; then
        warning "E2E_TEST_SEED_PHRASE not set - using default test phrase"
        export E2E_TEST_SEED_PHRASE="test test test test test test test test test test test junk"
    fi
    
    success "Prerequisites check completed"
}

# Wait for service to be healthy
wait_for_service() {
    local service_name=$1
    local health_url=$2
    local timeout=$3
    
    log "Waiting for $service_name to be healthy..."
    
    local counter=0
    while [ $counter -lt $timeout ]; do
        if curl -f -s "$health_url" > /dev/null 2>&1; then
            success "$service_name is healthy"
            return 0
        fi
        
        echo -n "."
        sleep 2
        counter=$((counter + 2))
    done
    
    error "$service_name failed to become healthy within $timeout seconds"
    return 1
}

# Start E2E environment
start_environment() {
    log "Starting E2E test environment..."
    
    # Pull latest images
    docker-compose -f $COMPOSE_FILE pull
    
    # Start core services
    docker-compose -f $COMPOSE_FILE up -d postgres-e2e redis-e2e
    
    # Wait for database to be ready
    log "Waiting for database to initialize..."
    sleep 10
    
    # Start backend
    docker-compose -f $COMPOSE_FILE up -d backend-e2e
    wait_for_service "Backend API" "$BACKEND_HEALTH_URL" 120
    
    # Start frontend
    docker-compose -f $COMPOSE_FILE up -d frontend-e2e
    wait_for_service "Frontend" "$FRONTEND_HEALTH_URL" 120
    
    # Start nginx proxy
    docker-compose -f $COMPOSE_FILE up -d nginx-e2e
    
    success "E2E environment started successfully"
}

# Run backend E2E tests
run_backend_tests() {
    log "Running backend E2E tests..."
    
    docker-compose -f $COMPOSE_FILE --profile testing run --rm phpunit-e2e
    
    local exit_code=$?
    if [ $exit_code -eq 0 ]; then
        success "Backend E2E tests passed"
    else
        error "Backend E2E tests failed with exit code $exit_code"
        return $exit_code
    fi
}

# Run frontend E2E tests
run_frontend_tests() {
    log "Running frontend E2E tests..."
    
    docker-compose -f $COMPOSE_FILE --profile testing run --rm playwright-e2e
    
    local exit_code=$?
    if [ $exit_code -eq 0 ]; then
        success "Frontend E2E tests passed"
    else
        error "Frontend E2E tests failed with exit code $exit_code"
        return $exit_code
    fi
}

# Generate test reports
generate_reports() {
    log "Generating test reports..."
    
    # Create reports directory
    mkdir -p reports/e2e
    
    # Copy backend test reports
    if [ -d "backend/reports" ]; then
        cp -r backend/reports/* reports/e2e/
        success "Backend test reports copied"
    fi
    
    # Copy frontend test reports
    if [ -d "ui/test-results" ]; then
        cp -r ui/test-results/* reports/e2e/
        success "Frontend test results copied"
    fi
    
    if [ -d "ui/playwright-report" ]; then
        cp -r ui/playwright-report/* reports/e2e/
        success "Playwright HTML report copied"
    fi
    
    # Generate summary report
    cat > reports/e2e/summary.md << EOF
# E2E Test Summary

Generated: $(date)

## Environment Information
- Backend URL: $BACKEND_HEALTH_URL
- Frontend URL: $FRONTEND_HEALTH_URL
- Compose File: $COMPOSE_FILE

## Test Results
- Backend Tests: $([ -f "reports/e2e/junit.xml" ] && echo "✓ Passed" || echo "✗ Failed")
- Frontend Tests: $([ -f "reports/e2e/results.json" ] && echo "✓ Passed" || echo "✗ Failed")

## Reports Available
- Backend PHPUnit Report: \`reports/e2e/junit.xml\`
- Frontend Playwright Report: \`reports/e2e/index.html\`
- Test Coverage: \`reports/e2e/coverage/\`

EOF
    
    success "Test reports generated in reports/e2e/"
}

# Show service logs
show_logs() {
    log "Showing service logs for debugging..."
    
    echo -e "\n${BLUE}=== Backend Logs ===${NC}"
    docker-compose -f $COMPOSE_FILE logs --tail=50 backend-e2e
    
    echo -e "\n${BLUE}=== Frontend Logs ===${NC}"
    docker-compose -f $COMPOSE_FILE logs --tail=50 frontend-e2e
    
    echo -e "\n${BLUE}=== Database Logs ===${NC}"
    docker-compose -f $COMPOSE_FILE logs --tail=20 postgres-e2e
}

# Main execution
main() {
    local backend_only=false
    local frontend_only=false
    local show_logs_flag=false
    local generate_reports_flag=true
    
    # Parse command line arguments
    while [[ $# -gt 0 ]]; do
        case $1 in
            --backend-only)
                backend_only=true
                shift
                ;;
            --frontend-only)
                frontend_only=true
                shift
                ;;
            --logs)
                show_logs_flag=true
                shift
                ;;
            --no-reports)
                generate_reports_flag=false
                shift
                ;;
            --help)
                echo "Usage: $0 [OPTIONS]"
                echo "Options:"
                echo "  --backend-only    Run only backend E2E tests"
                echo "  --frontend-only   Run only frontend E2E tests"
                echo "  --logs           Show service logs after tests"
                echo "  --no-reports     Skip generating test reports"
                echo "  --help           Show this help message"
                exit 0
                ;;
            *)
                error "Unknown option: $1"
                exit 1
                ;;
        esac
    done
    
    log "Starting CIRX Swap E2E Test Suite"
    
    check_prerequisites
    start_environment
    
    local overall_exit_code=0
    
    # Run tests based on flags
    if [ "$frontend_only" = false ]; then
        if ! run_backend_tests; then
            overall_exit_code=1
        fi
    fi
    
    if [ "$backend_only" = false ]; then
        if ! run_frontend_tests; then
            overall_exit_code=1
        fi
    fi
    
    # Generate reports if requested
    if [ "$generate_reports_flag" = true ]; then
        generate_reports
    fi
    
    # Show logs if requested or if tests failed
    if [ "$show_logs_flag" = true ] || [ $overall_exit_code -ne 0 ]; then
        show_logs
    fi
    
    # Final result
    if [ $overall_exit_code -eq 0 ]; then
        success "All E2E tests passed successfully!"
    else
        error "Some E2E tests failed. Check the logs and reports for details."
    fi
    
    exit $overall_exit_code
}

# Run main function with all arguments
main "$@"