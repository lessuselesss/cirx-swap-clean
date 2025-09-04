#!/bin/bash

# CIRX OTC Platform - Automated Worker Setup Script
# Sets up cron jobs to automatically run background workers

BACKEND_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CRON_USER="${USER}"

echo "🔧 Setting up automated CIRX OTC workers..."
echo "Backend directory: $BACKEND_DIR"
echo "Cron user: $CRON_USER"

# Function to add cron job if it doesn't exist
add_cron_job() {
    local schedule="$1"
    local command="$2"
    local description="$3"
    
    # Check if cron job already exists
    if crontab -l 2>/dev/null | grep -Fq "$command"; then
        echo "✅ Cron job already exists: $description"
    else
        # Add new cron job
        (crontab -l 2>/dev/null; echo "$schedule $command # $description") | crontab -
        echo "➕ Added cron job: $description"
        echo "   Schedule: $schedule"
        echo "   Command: $command"
    fi
}

# Create log directory for cron jobs
mkdir -p "$BACKEND_DIR/logs/cron"

# Payment verification worker - every 2 minutes
add_cron_job \
    "*/2 * * * *" \
    "cd $BACKEND_DIR && nix run nixpkgs#php -- worker.php payment-verification >> logs/cron/payment-verification.log 2>&1" \
    "CIRX Payment Verification Worker"

# CIRX transfer worker - every 3 minutes (offset to avoid conflicts)
add_cron_job \
    "1-59/3 * * * *" \
    "cd $BACKEND_DIR && nix run nixpkgs#php -- worker.php cirx-transfer >> logs/cron/cirx-transfer.log 2>&1" \
    "CIRX Transfer Worker"

# Comprehensive worker (both) - every 10 minutes as backup
add_cron_job \
    "*/10 * * * *" \
    "cd $BACKEND_DIR && nix run nixpkgs#php -- worker.php both >> logs/cron/comprehensive.log 2>&1" \
    "CIRX Comprehensive Worker (Backup)"

# Log rotation - daily at 2 AM
add_cron_job \
    "0 2 * * *" \
    "find $BACKEND_DIR/logs/cron -name '*.log' -size +10M -exec truncate -s 1M {} \;" \
    "CIRX Worker Log Rotation"

echo ""
echo "📋 Current cron jobs for $CRON_USER:"
crontab -l | grep -E "(worker\.php|CIRX)" || echo "No CIRX worker cron jobs found."

echo ""
echo "📊 Worker schedule summary:"
echo "• Payment verification: Every 2 minutes"
echo "• CIRX transfers: Every 3 minutes (1,4,7,10...)"
echo "• Comprehensive backup: Every 10 minutes"
echo "• Log rotation: Daily at 2:00 AM"

echo ""
echo "📁 Log files location:"
echo "• Payment verification: $BACKEND_DIR/logs/cron/payment-verification.log"
echo "• CIRX transfers: $BACKEND_DIR/logs/cron/cirx-transfer.log"
echo "• Comprehensive: $BACKEND_DIR/logs/cron/comprehensive.log"

echo ""
echo "🛠️  Manual commands:"
echo "• View logs: tail -f $BACKEND_DIR/logs/cron/*.log"
echo "• Check cron: crontab -l"
echo "• Remove cron: crontab -e (then delete lines)"
echo "• Test worker: cd $BACKEND_DIR && nix run nixpkgs#php -- worker.php stats"

echo ""
echo "✅ Automated worker setup completed!"
echo ""
echo "⚠️  IMPORTANT NOTES:"
echo "1. Cron jobs run in minimal environment - ensure PATH includes nix"
echo "2. Check logs regularly: tail -f logs/cron/*.log"
echo "3. Monitor worker statistics: php worker.php stats"
echo "4. For production, consider systemd services instead of cron"

echo ""
echo "🏃 Starting initial worker run..."
cd "$BACKEND_DIR"
nix run nixpkgs#php -- worker.php both

echo ""
echo "🎉 Setup complete! Workers will now run automatically."