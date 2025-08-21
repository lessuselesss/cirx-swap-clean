#!/bin/bash

set -e

echo "🚀 Testing IROH Distributed Networking Integration"
echo "=================================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Test configuration
IROH_BRIDGE_URL="http://localhost:9090"
BACKEND_URL="http://localhost:8080"
FRONTEND_URL="http://localhost:3000"

# Function to check if a service is running
check_service() {
    local url=$1
    local name=$2
    
    echo -n "Checking $name... "
    if curl -s -o /dev/null -w "%{http_code}" "$url/health" | grep -q "200\|404"; then
        echo -e "${GREEN}✓ Running${NC}"
        return 0
    else
        echo -e "${RED}✗ Not running${NC}"
        return 1
    fi
}

# Function to test API endpoint
test_endpoint() {
    local url=$1
    local name=$2
    local expected_status=${3:-200}
    
    echo -n "Testing $name... "
    local response=$(curl -s -o /dev/null -w "%{http_code}" "$url" 2>/dev/null || echo "000")
    
    if [[ "$response" == "$expected_status" ]]; then
        echo -e "${GREEN}✓ $response${NC}"
        return 0
    else
        echo -e "${RED}✗ $response (expected $expected_status)${NC}"
        return 1
    fi
}

# Function to test JSON response
test_json_endpoint() {
    local url=$1
    local name=$2
    local key=$3
    
    echo -n "Testing $name... "
    local response=$(curl -s "$url" 2>/dev/null || echo "{}")
    local value=$(echo "$response" | jq -r ".$key" 2>/dev/null || echo "null")
    
    if [[ "$value" != "null" && "$value" != "" ]]; then
        echo -e "${GREEN}✓ $key: $value${NC}"
        return 0
    else
        echo -e "${RED}✗ Missing or invalid $key${NC}"
        return 1
    fi
}

echo "Step 1: Check Service Health"
echo "----------------------------"

# Check if Docker services are running
if command -v docker-compose >/dev/null 2>&1; then
    echo "Docker Compose services:"
    docker-compose -f docker-compose.iroh.yml ps 2>/dev/null || echo "Docker services not running"
    echo
fi

# Test individual services
check_service "$IROH_BRIDGE_URL" "IROH Bridge"
check_service "$BACKEND_URL" "Backend API" 
# Frontend health check - just check if it's serving content
echo -n "Checking Frontend... "
if curl -s -o /dev/null -w "%{http_code}" "$FRONTEND_URL" | grep -q "200"; then
    echo -e "${GREEN}✓ Running${NC}"
else
    echo -e "${YELLOW}⚠ May not be running${NC}"
fi

echo ""
echo "Step 2: Test IROH Bridge API"
echo "-----------------------------"

# Test IROH bridge endpoints
test_json_endpoint "$IROH_BRIDGE_URL/health" "Health Check" "success"
test_json_endpoint "$IROH_BRIDGE_URL/node/info" "Node Info" "data.node_id"
test_json_endpoint "$IROH_BRIDGE_URL/node/address" "Node Address" "data.node_id"

# Test service discovery
echo -n "Testing Service Discovery... "
response=$(curl -s -X POST "$IROH_BRIDGE_URL/services/discover" \
    -H "Content-Type: application/json" \
    -d '{"service_name": "cirx-swap-backend", "max_results": 5}' 2>/dev/null || echo "{}")
success=$(echo "$response" | jq -r ".success" 2>/dev/null || echo "false")

if [[ "$success" == "true" ]]; then
    services_count=$(echo "$response" | jq -r ".data | length" 2>/dev/null || echo "0")
    echo -e "${GREEN}✓ Found $services_count services${NC}"
else
    echo -e "${YELLOW}⚠ No services discovered yet${NC}"
fi

echo ""
echo "Step 3: Test Backend IROH Integration" 
echo "------------------------------------"

# Test backend IROH endpoints
test_json_endpoint "$BACKEND_URL/api/v1/iroh/status" "IROH Status" "success"

echo -n "Testing Peer Discovery... "
response=$(curl -s -X POST "$BACKEND_URL/api/v1/iroh/discover" 2>/dev/null || echo "{}")
success=$(echo "$response" | jq -r ".success" 2>/dev/null || echo "false")

if [[ "$success" == "true" ]]; then
    echo -e "${GREEN}✓ Discovery working${NC}"
else
    echo -e "${YELLOW}⚠ Discovery may not be working${NC}"
fi

# Test enhanced transaction status endpoint
echo -n "Testing Enhanced Transaction Status... "
# Use a dummy transaction ID for testing
response=$(curl -s "$BACKEND_URL/api/v1/transactions/test-tx-001/status/realtime" 2>/dev/null || echo "{}")
success=$(echo "$response" | jq -r ".success" 2>/dev/null || echo "false")

if [[ "$success" == "true" ]] || [[ $(echo "$response" | jq -r ".error" 2>/dev/null) == *"not found"* ]]; then
    echo -e "${GREEN}✓ Endpoint accessible${NC}"
else
    echo -e "${RED}✗ Endpoint error${NC}"
fi

echo ""
echo "Step 4: Test Network Performance"
echo "--------------------------------"

# Test response times
echo -n "IROH Bridge response time... "
start_time=$(date +%s%N)
curl -s "$IROH_BRIDGE_URL/health" > /dev/null 2>&1
end_time=$(date +%s%N)
duration=$((($end_time - $start_time) / 1000000))
echo -e "${BLUE}${duration}ms${NC}"

echo -n "Backend response time... "
start_time=$(date +%s%N)
curl -s "$BACKEND_URL/api/v1/health" > /dev/null 2>&1
end_time=$(date +%s%N)
duration=$((($end_time - $start_time) / 1000000))
echo -e "${BLUE}${duration}ms${NC}"

echo ""
echo "Step 5: Integration Test"
echo "-----------------------"

# Test message broadcasting
echo -n "Testing Message Broadcasting... "
response=$(curl -s -X POST "$IROH_BRIDGE_URL/broadcast" \
    -H "Content-Type: application/json" \
    -d '{
        "topic": "test-topic",
        "message": {
            "type": "TEST_MESSAGE",
            "timestamp": '$(date +%s)',
            "data": "IROH integration test"
        }
    }' 2>/dev/null || echo "{}")

success=$(echo "$response" | jq -r ".success" 2>/dev/null || echo "false")

if [[ "$success" == "true" ]]; then
    echo -e "${GREEN}✓ Broadcasting works${NC}"
else
    echo -e "${RED}✗ Broadcasting failed${NC}"
fi

echo ""
echo "Step 6: Configuration Validation"
echo "--------------------------------"

# Check environment variables
echo "Environment Configuration:"
echo "IROH_ENABLED: ${IROH_ENABLED:-false}"
echo "IROH_BRIDGE_URL: ${IROH_BRIDGE_URL:-http://localhost:9090}"
echo "NUXT_PUBLIC_IROH_ENABLED: ${NUXT_PUBLIC_IROH_ENABLED:-false}"
echo "NUXT_PUBLIC_IROH_BRIDGE_URL: ${NUXT_PUBLIC_IROH_BRIDGE_URL:-http://localhost:9090}"

echo ""
echo "Step 7: Generate Test Report"
echo "---------------------------"

# Get detailed status information
echo "Detailed Status Report:"
echo "======================="

if curl -s "$IROH_BRIDGE_URL/node/info" > /tmp/iroh_info.json 2>/dev/null; then
    echo "IROH Node Information:"
    cat /tmp/iroh_info.json | jq . 2>/dev/null || cat /tmp/iroh_info.json
    rm -f /tmp/iroh_info.json
fi

echo ""
if curl -s "$BACKEND_URL/api/v1/iroh/status" > /tmp/backend_iroh.json 2>/dev/null; then
    echo "Backend IROH Status:"
    cat /tmp/backend_iroh.json | jq . 2>/dev/null || cat /tmp/backend_iroh.json
    rm -f /tmp/backend_iroh.json
fi

echo ""
echo "🎉 IROH Integration Test Complete!"
echo "================================="
echo ""
echo "Next Steps:"
echo "1. Start all services: docker-compose -f docker-compose.iroh.yml up -d"
echo "2. Enable IROH in environment: export IROH_ENABLED=true"
echo "3. Test real-time updates by creating a transaction"
echo "4. Monitor logs: docker-compose -f docker-compose.iroh.yml logs -f"
echo ""
echo "For development:"
echo "- IROH Bridge: cargo run --manifest-path iroh-bridge/Cargo.toml"
echo "- Backend: cd backend && php -S localhost:8080 public/index.php"
echo "- Frontend: cd ui && npm run dev"