# CIRX Transaction Monitoring

This document explains the production monitoring solution that addresses the gap between comprehensive test coverage and production observability.

## Problem Statement

**Issue Identified**: "Why has the fact that no CIRX transactions are being processed not been caught?"

While the comprehensive test suite properly validates CIRX transfer failures (including wallet configuration issues), production systems weren't alerting on these same failures occurring in the live environment.

## Root Cause Analysis

The `CirxTransferService.php` properly fails when CIRX wallet credentials aren't configured:

```php
// Line 62-69 in CirxTransferService.php
if (!$this->cirxWalletAddress || !$this->cirxWalletPrivateKey) {
    $transaction->markFailed('CIRX wallet not configured', Transaction::STATUS_FAILED_CIRX_TRANSFER);
    return CirxTransferResult::failure(
        $transaction->cirx_recipient_address ?? '',
        '0',
        'CIRX wallet not configured'
    );
}
```

**The Gap**: Tests catch this failure scenario, but production monitoring didn't alert when it occurred live.

## Monitoring Solution

### 1. Transaction Monitoring Service

**File**: `src/Services/TransactionMonitoringService.php`

**Capabilities**:
- **Stuck Transaction Detection**: Alerts when transactions remain in `payment_verified` state too long
- **Failure Rate Monitoring**: Tracks CIRX transfer failure percentages
- **Wallet Configuration Monitoring**: Detects wallet setup issues in production
- **Configurable Thresholds**: Environment-driven alert sensitivity

### 2. Monitoring Endpoints

**File**: `src/Controllers/MonitoringController.php`

**Endpoints**:
- `GET /api/v1/health` - Load balancer health checks
- `GET /api/v1/monitoring/report` - Comprehensive monitoring dashboard
- `GET /api/v1/monitoring/stuck-transactions` - Specific stuck transaction alerts
- `GET /api/v1/monitoring/wallet-config` - Wallet configuration status
- `GET /api/v1/monitoring/metrics` - Prometheus-compatible metrics

### 3. CLI Monitoring Command

**File**: `monitoring-check.php`

**Usage**:
```bash
# Basic monitoring check
php monitoring-check.php

# JSON output for integrations
php monitoring-check.php --json

# Show only alerts
php monitoring-check.php --alerts-only
```

**Cron Integration**:
```bash
# Check every 5 minutes
*/5 * * * * /path/to/backend/monitoring-check.php --alerts-only >> /var/log/cirx-monitoring.log 2>&1
```

## Alert Scenarios

### Critical Alerts (Exit Code 2)

1. **Stuck Transactions**
   - **Trigger**: Transactions in `payment_verified` state > 30 minutes
   - **Indicates**: CIRX transfers not processing (wallet config issues)
   - **Investigation**: Check `CIRX_WALLET_ADDRESS` and `CIRX_WALLET_PRIVATE_KEY`

2. **Wallet Configuration Failures**
   - **Trigger**: 3+ "CIRX wallet not configured" failures in 15 minutes
   - **Indicates**: Environment variables not set in production
   - **Investigation**: Verify deployment configuration and secret management

### High Alerts (Exit Code 1)

1. **High Failure Rate**
   - **Trigger**: >25% CIRX transfer failure rate in 1 hour
   - **Indicates**: Blockchain connectivity or balance issues
   - **Investigation**: Check CIRX wallet balance and network connectivity

## Configuration

### Environment Variables

```bash
# Alert Thresholds
ALERT_STUCK_PAYMENT_MINUTES=30          # Minutes before stuck transaction alert
ALERT_FAILED_TRANSFER_PERCENT=25.0      # Failure rate threshold
ALERT_WALLET_CONFIG_FAILURES=3          # Config failure count threshold

# Logging
MONITORING_LOG_FILE=/var/log/cirx-otc/monitoring.log

# Security
HEALTH_CHECK_SECRET=your_health_check_secret_for_monitoring_endpoints
```

### Production Deployment

1. **Set Environment Variables**:
   ```bash
   CIRX_WALLET_ADDRESS=0x...
   CIRX_WALLET_PRIVATE_KEY=0x...
   ```

2. **Configure Cron Monitoring**:
   ```bash
   # Add to crontab
   */5 * * * * /path/to/backend/monitoring-check.php --json | logger -t cirx-monitoring
   ```

3. **Set up Log Rotation**:
   ```bash
   # /etc/logrotate.d/cirx-monitoring
   /var/log/cirx-otc/monitoring.log {
       daily
       rotate 30
       compress
       delaycompress
       missingok
       notifempty
       create 644 www-data www-data
   }
   ```

## Integration Examples

### Prometheus Monitoring

```bash
# Scrape metrics endpoint
curl http://localhost:8080/api/v1/monitoring/metrics

# Example metrics output:
# cirx_transactions_total{status="completed"} 142
# cirx_transaction_success_rate 95.5
# cirx_stuck_transactions 0
# cirx_wallet_configured 1
# cirx_system_healthy 1
```

### Grafana Dashboard

Key metrics to monitor:
- `cirx_transaction_success_rate` - Target: >95%
- `cirx_stuck_transactions` - Target: 0
- `cirx_wallet_configured` - Target: 1
- `rate(cirx_transactions_total[5m])` - Transaction throughput

### Alertmanager Rules

```yaml
groups:
- name: cirx_alerts
  rules:
  - alert: CirxWalletNotConfigured
    expr: cirx_wallet_configured == 0
    for: 1m
    labels:
      severity: critical
    annotations:
      summary: "CIRX wallet not configured"
      description: "CIRX_WALLET_ADDRESS or CIRX_WALLET_PRIVATE_KEY not set"

  - alert: CirxTransactionsStuck
    expr: cirx_stuck_transactions > 0
    for: 5m
    labels:
      severity: critical
    annotations:
      summary: "CIRX transactions stuck in payment_verified state"
      description: "{{ $value }} transactions stuck for >30 minutes"

  - alert: CirxLowSuccessRate
    expr: cirx_transaction_success_rate < 90
    for: 10m
    labels:
      severity: warning
    annotations:
      summary: "Low CIRX transaction success rate"
      description: "Success rate is {{ $value }}% (target: >95%)"
```

## Testing the Monitoring

### Unit Tests

**File**: `tests/Unit/Services/TransactionMonitoringServiceTest.php`

**Coverage**:
- Stuck transaction detection
- Failure rate calculations
- Wallet configuration alerts
- Summary statistics
- Health status determination

### Manual Testing

```bash
# Test with no wallet configuration
unset CIRX_WALLET_ADDRESS CIRX_WALLET_PRIVATE_KEY
php monitoring-check.php

# Should show critical wallet configuration alert

# Test health endpoint
curl http://localhost:8080/api/v1/health

# Should return 503 status if wallet not configured
```

## Production Readiness Checklist

- [ ] Environment variables configured (`CIRX_WALLET_ADDRESS`, `CIRX_WALLET_PRIVATE_KEY`)
- [ ] Monitoring cron job installed and running
- [ ] Log rotation configured
- [ ] Prometheus scraping configured (if using)
- [ ] Grafana dashboards imported (if using)
- [ ] Alertmanager rules configured (if using)
- [ ] Team notifications configured (Slack/email)
- [ ] Monitoring endpoints tested
- [ ] Alert thresholds validated for environment

## Next Steps

1. **Immediate**: Deploy monitoring to production and verify wallet configuration
2. **Short-term**: Set up automated alerts (Prometheus/Alertmanager or equivalent)
3. **Long-term**: Extend monitoring to include blockchain balance checks and network health

This monitoring solution ensures that the comprehensive test coverage extends into production observability, eliminating the gap that allowed CIRX transfer failures to go unnoticed.