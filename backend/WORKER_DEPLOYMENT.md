# CIRX OTC Platform - Worker Deployment Guide

## Overview

The CIRX OTC platform requires background workers to process transactions:

1. **PaymentVerificationWorker** - Verifies blockchain payments
2. **CirxTransferWorker** - Transfers CIRX tokens to users

## Production Deployment Options

### Option 1: Docker Container with Supervisor

Create a `Dockerfile` with supervisor to manage workers:

```dockerfile
FROM php:8.2-cli

# Install supervisor
RUN apt-get update && apt-get install -y supervisor

# Copy supervisor config
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copy application
COPY . /app
WORKDIR /app

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
```

**supervisord.conf:**
```ini
[supervisord]
nodaemon=true

[program:payment_verification]
command=php /app/worker.php payment-verification
autostart=true
autorestart=true
stderr_logfile=/var/log/payment_worker.log
stdout_logfile=/var/log/payment_worker.log

[program:cirx_transfer]
command=php /app/worker.php cirx-transfer  
autostart=true
autorestart=true
stderr_logfile=/var/log/cirx_worker.log
stdout_logfile=/var/log/cirx_worker.log
```

### Option 2: Systemd Services (Linux)

Create systemd service files:

**payment-worker.service:**
```ini
[Unit]
Description=CIRX Payment Verification Worker
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/cirx-backend
ExecStart=/usr/bin/php worker.php loop payment-verification
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

**cirx-transfer-worker.service:**
```ini
[Unit]
Description=CIRX Transfer Worker
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/cirx-backend
ExecStart=/usr/bin/php worker.php loop cirx-transfer
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

### Option 3: Platform-Specific Solutions

**Cloudflare Workers/Functions:**
```javascript
// Cloudflare Worker to trigger PHP workers via HTTP
export default {
  async scheduled(event, env, ctx) {
    // Call your backend worker endpoints
    await fetch('https://your-domain.com/api/workers/run', {
      method: 'POST',
      headers: { 'Authorization': 'Bearer ' + env.WORKER_TOKEN }
    });
  }
}
```

**Vercel Cron Jobs (vercel.json):**
```json
{
  "crons": [
    {
      "path": "/api/workers/payment-verification",
      "schedule": "*/2 * * * *"
    },
    {
      "path": "/api/workers/cirx-transfer", 
      "schedule": "1-59/3 * * * *"
    }
  ]
}
```

### Option 4: Queue-Based System

**Laravel Queues with Redis:**
```php
// Add to your existing Laravel/PHP setup
use Illuminate\Queue\Queue;

// Dispatch jobs to queue
Queue::push(new PaymentVerificationJob());
Queue::push(new CirxTransferJob());
```

## Manual Execution

For immediate deployment, you can run workers manually or via simple cron:

```bash
# Every 2 minutes - Payment verification
*/2 * * * * cd /path/to/backend && php worker.php payment-verification

# Every 3 minutes - CIRX transfers  
1-59/3 * * * * cd /path/to/backend && php worker.php cirx-transfer

# Every 10 minutes - Comprehensive backup
*/10 * * * * cd /path/to/backend && php worker.php both
```

## Health Monitoring

Add these endpoints to your API for monitoring:

```php
// GET /api/workers/health
public function workerHealth() {
    $stats = [
        'payment_verification' => (new PaymentVerificationWorker())->getStatistics(),
        'cirx_transfer' => (new CirxTransferWorker())->getStatistics(),
        'last_run' => filemtime(__DIR__ . '/../logs/worker.log'),
        'status' => 'healthy'
    ];
    
    // Alert if stuck transactions > 5 minutes
    if ($stats['payment_verification']['pending_verification'] > 0) {
        $stats['alerts'][] = 'Transactions pending payment verification';
    }
    
    return json_response($stats);
}

// POST /api/workers/trigger
public function triggerWorkers() {
    // Manual trigger for debugging
    $paymentResults = (new PaymentVerificationWorker())->processPendingTransactions();
    $cirxResults = (new CirxTransferWorker())->processReadyTransactions();
    
    return json_response([
        'payment_worker' => $paymentResults,
        'cirx_worker' => $cirxResults,
        'triggered_at' => now()
    ]);
}
```

## Current Worker Status

Run this command to check current status:

```bash
php worker.php stats
```

## Troubleshooting

**Common Issues:**
1. **Stuck transactions** - Run `php worker.php both` manually
2. **Payment verification fails** - Check indexer URL in .env
3. **CIRX transfers timeout** - Increase timeout or wait for blockchain
4. **Memory issues** - Add `memory_limit=512M` to php.ini

**Log Monitoring:**
```bash
# View worker logs
tail -f logs/*.log

# Check transaction status
php -r "
use App\Models\Transaction;
require 'vendor/autoload.php';
// Database setup...
echo 'Pending: ' . Transaction::where('swap_status', 'pending_payment_verification')->count() . \"\n\";
echo 'Transfer Pending: ' . Transaction::where('swap_status', 'cirx_transfer_pending')->count() . \"\n\";
"
```

## Recommended Production Setup

1. **Use Option 1 (Docker + Supervisor)** for containerized deployments
2. **Use Option 2 (Systemd)** for traditional Linux servers  
3. **Add health monitoring endpoint** for alerting
4. **Set up log aggregation** (ELK stack, etc.)
5. **Configure alerting** for stuck transactions

The workers will automatically handle retries and stuck transaction recovery.