# Transaction Readiness Health Check System

The CIRX OTC Backend includes a comprehensive transaction readiness health check system that validates ALL components required for successful transaction processing from payment verification to CIRX delivery.

## Endpoint

```
GET /api/v1/health/transaction-ready
```

## Purpose

This endpoint performs a comprehensive assessment of the backend's ability to process transactions end-to-end. If this endpoint returns `transaction_ready: true`, the backend can **guarantee** it can handle transactions from start to finish.

## Response Format

### Success Response (HTTP 200)
```json
{
    "transaction_ready": true,
    "status": "ready",
    "timestamp": "2025-08-28T03:10:27+00:00",
    "duration_ms": 8754.03,
    "critical_issues": [],
    "warnings": [],
    "checks": {
        "database": {
            "status": "healthy",
            "message": "Database fully operational",
            "response_time_ms": 234.56,
            "details": {
                "driver": "sqlite",
                "write_operations": "INSERT, UPDATE, DELETE tested successfully",
                "stuck_transactions": 5
            }
        },
        "circular_protocol_api": {
            "status": "healthy",
            "message": "Circular Protocol API fully operational",
            "response_time_ms": 1234.56,
            "details": {
                "block_number": 1,
                "wallet_balance": "47.7",
                "environment": "development",
                "private_key_configured": true
            }
        },
        // ... other checks
    },
    "summary": {
        "total_checks": 9,
        "healthy_checks": 9,
        "degraded_checks": 0,
        "critical_checks": 0,
        "health_percentage": 100.0
    }
}
```

### Failure Response (HTTP 503)
```json
{
    "transaction_ready": false,
    "status": "not_ready",
    "timestamp": "2025-08-28T03:10:27+00:00",
    "duration_ms": 5432.10,
    "critical_issues": [
        "Database: Connection failed",
        "CIRX Transfer: Insufficient balance"
    ],
    "warnings": [
        "External Dependencies: IROH service unavailable"
    ],
    // ... same structure as success
}
```

## Health Checks Performed

The endpoint performs 9 comprehensive health checks:

### 1. Database Connectivity & Write Capability
- Tests database connection
- Verifies critical tables exist (`transactions`, `project_wallets`)
- Performs actual INSERT, UPDATE, DELETE operations
- Checks for stuck transactions

### 2. Circular Protocol API Connectivity & Authentication
- Tests NAG API connectivity
- Verifies wallet address configuration
- Checks private key availability for signing
- Validates sufficient CIRX balance for transfers
- Tests block number retrieval

### 3. Payment Verification Service Availability
- Tests blockchain client factory
- Verifies Ethereum, BSC, and Polygon RPC connectivity
- Checks indexer service availability (if configured)
- Validates critical payment chains are working

### 4. CIRX Transfer Service Functionality
- Tests CIRX client initialization
- Verifies wallet configuration
- Checks CIRX balance sufficiency
- Monitors pending/failed transfer queue health

### 5. Worker Queue System Status
- Verifies worker class availability:
  - `PaymentVerificationWorker`
  - `CirxTransferWorker`
  - `StuckTransactionRecoveryWorker`
- Tests worker instantiation
- Monitors transaction queue health
- Checks for stuck transactions

### 6. Critical Environment Variables & Configuration
- Validates required environment variables:
  - `APP_ENV`
  - `DB_CONNECTION`, `DB_DATABASE`
  - `CIRX_WALLET_ADDRESS`, `CIRX_WALLET_PRIVATE_KEY`
- Checks recommended configuration
- Verifies PHP settings

### 7. System Resources (Disk Space, Memory)
- Monitors disk space usage (critical >95%, warning >85%)
- Checks memory usage (critical >90%, warning >75%)
- Reports available system resources

### 8. External Dependencies (IROH, etc.)
- Tests IROH service bridge connectivity
- Checks indexer service availability
- Validates external service integrations

### 9. End-to-End Transaction Flow Test
- Simulates complete transaction flow
- Tests database operations
- Verifies blockchain client initialization
- Validates CIRX client functionality
- Tests worker and service instantiation

## Integration Examples

### Load Balancer Health Check
```bash
# Configure your load balancer to check this endpoint
curl -f http://backend:8080/api/v1/health/transaction-ready
# Returns HTTP 200 if ready, HTTP 503 if not ready
```

### Kubernetes Readiness Probe
```yaml
readinessProbe:
  httpGet:
    path: /api/v1/health/transaction-ready
    port: 8080
  initialDelaySeconds: 30
  periodSeconds: 60
  timeoutSeconds: 30
  failureThreshold: 3
```

### Monitoring & Alerting
```bash
# Prometheus-style monitoring
curl -s http://backend:8080/api/v1/health/transaction-ready | jq '.transaction_ready'
# Returns: true or false

# Get health percentage
curl -s http://backend:8080/api/v1/health/transaction-ready | jq '.summary.health_percentage'
# Returns: 100.0 (if fully healthy)

# Get critical issues count
curl -s http://backend:8080/api/v1/health/transaction-ready | jq '.critical_issues | length'
# Returns: 0 (if no critical issues)
```

### CI/CD Pipeline Integration
```bash
#!/bin/bash
# deployment-health-check.sh

echo "Checking transaction readiness..."
RESPONSE=$(curl -s -w "HTTPSTATUS:%{http_code}" http://your-backend:8080/api/v1/health/transaction-ready)
HTTP_CODE=$(echo $RESPONSE | tr -d '\n' | sed -e 's/.*HTTPSTATUS://')
BODY=$(echo $RESPONSE | sed -e 's/HTTPSTATUS:.*//g')

if [ "$HTTP_CODE" -eq 200 ]; then
    echo "✅ Backend is transaction-ready"
    echo "Health: $(echo $BODY | jq -r '.summary.health_percentage')%"
else
    echo "❌ Backend not ready for transactions (HTTP $HTTP_CODE)"
    echo "Critical issues: $(echo $BODY | jq -r '.critical_issues[]')"
    exit 1
fi
```

## Performance Characteristics

- **Average Response Time**: 3-10 seconds (includes external API calls)
- **Timeout**: 30 seconds maximum
- **Caching**: No caching (real-time validation)
- **Rate Limiting**: Subject to standard API rate limits

## Troubleshooting

### Common Issues

1. **HTTP 503 - Database Issues**
   ```
   Critical Issues: ["Database: Connection failed"]
   ```
   - Check database connectivity
   - Verify environment variables
   - Ensure required tables exist

2. **HTTP 503 - CIRX Wallet Issues**
   ```
   Critical Issues: ["CIRX Transfer: Insufficient balance"]
   ```
   - Check CIRX wallet balance
   - Verify wallet private key configuration
   - Ensure NAG API connectivity

3. **HTTP 503 - Blockchain Connectivity**
   ```
   Critical Issues: ["Payment Verification: Critical blockchain clients failed: ethereum"]
   ```
   - Check RPC endpoint configuration
   - Verify network connectivity
   - Test RPC endpoints manually

### Debug Mode
```bash
# Get detailed check results
curl -s http://backend:8080/api/v1/health/transaction-ready | jq '.checks'

# Check specific component
curl -s http://backend:8080/api/v1/health/transaction-ready | jq '.checks.database'
```

## Testing

Run the included test script to validate the health check system:

```bash
cd backend
php test_transaction_ready.php
```

This script tests the health check service directly and provides detailed output about each check component.

## Related Endpoints

- `/api/v1/health` - Basic health check (lightweight)
- `/api/v1/health/detailed` - Comprehensive health check (legacy)
- `/api/v1/monitoring/metrics` - Prometheus metrics
- `/api/v1/monitoring/wallet-config` - Wallet configuration status