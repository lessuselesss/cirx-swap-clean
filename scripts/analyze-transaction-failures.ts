#!/usr/bin/env deno run --allow-read --allow-ffi

/**
 * Transaction Failure Pattern Analysis Script
 * Analyzes SQLite database for transaction failure patterns and stuck transactions
 */

import { DB } from "https://deno.land/x/sqlite@v3.8/mod.ts";

interface Transaction {
  id: string;
  payment_tx_id?: string;
  payment_chain?: string;
  cirx_recipient_address?: string;
  amount_paid?: string;
  payment_token?: string;
  swap_status: string;
  cirx_transfer_tx_id?: string;
  failure_reason?: string;
  retry_count?: number;
  last_retry_at?: string;
  is_test_transaction?: boolean;
  created_at?: string;
  updated_at?: string;
}

const DATABASE_PATH = "/home/lessuseless/Projects/Orgs/Circular-Protocol/Autoswap/cirx-swap-clean-indexed-new/backend/storage/database.sqlite";

// Transaction status constants (from the Transaction model)
const STATUS = {
  INITIATED: 'initiated',
  PAYMENT_PENDING: 'payment_pending',
  PENDING_PAYMENT_VERIFICATION: 'pending_payment_verification',
  PAYMENT_VERIFIED: 'payment_verified',
  TRANSFER_PENDING: 'transfer_pending',
  CIRX_TRANSFER_PENDING: 'cirx_transfer_pending',
  CIRX_TRANSFER_INITIATED: 'cirx_transfer_initiated',
  TRANSFER_INITIATED: 'transfer_initiated',
  COMPLETED: 'completed',
  FAILED_PAYMENT_VERIFICATION: 'failed_payment_verification',
  FAILED_CIRX_TRANSFER: 'failed_cirx_transfer'
} as const;

function formatNumber(num: number): string {
  return num.toLocaleString();
}

function formatPercentage(value: number, total: number): string {
  return total > 0 ? `${((value / total) * 100).toFixed(2)}%` : '0.00%';
}

function truncateString(str: string, maxLength: number): string {
  return str.length > maxLength ? str.substring(0, maxLength - 3) + '...' : str;
}

async function analyzeTransactionFailures(): Promise<void> {
  try {
    // Check if database file exists
    try {
      await Deno.stat(DATABASE_PATH);
    } catch {
      console.error(`❌ Database file not found: ${DATABASE_PATH}`);
      Deno.exit(1);
    }

    // Open SQLite database
    const db = new DB(DATABASE_PATH);

    console.log("📊 TRANSACTION FAILURE PATTERN ANALYSIS");
    console.log("=====================================\n");

    // 1. Overall transaction status summary
    console.log("1. OVERALL TRANSACTION STATUS SUMMARY");
    console.log("------------------------------------");
    
    const statusQuery = `
      SELECT swap_status, COUNT(*) as count 
      FROM transactions 
      GROUP BY swap_status 
      ORDER BY count DESC
    `;
    
    const statusResults = db.query(statusQuery);
    let totalTransactions = 0;
    
    for (const [status, count] of statusResults) {
      totalTransactions += count as number;
      console.log(`  ${String(status).padEnd(35)}: ${formatNumber(count as number)}`);
    }
    console.log(`  ${"-".repeat(50)}`);
    console.log(`  ${"TOTAL TRANSACTIONS".padEnd(35)}: ${formatNumber(totalTransactions)}\n`);

    // 2. Failed transaction analysis
    console.log("2. FAILED TRANSACTION BREAKDOWN");
    console.log("------------------------------");
    
    const failedQuery = `
      SELECT swap_status, failure_reason, COUNT(*) as count
      FROM transactions 
      WHERE swap_status IN ('failed_payment_verification', 'failed_cirx_transfer')
      GROUP BY swap_status, failure_reason
      ORDER BY count DESC
    `;
    
    const failedResults = db.query(failedQuery);
    const failuresByStatus = new Map<string, number>();
    const failuresByReason = new Map<string, number>();
    
    for (const [status, reason, count] of failedResults) {
      const statusStr = String(status);
      const reasonStr = String(reason || 'No reason specified');
      const countNum = count as number;
      
      failuresByStatus.set(statusStr, (failuresByStatus.get(statusStr) || 0) + countNum);
      failuresByReason.set(reasonStr, (failuresByReason.get(reasonStr) || 0) + countNum);
    }
    
    console.log("Failed by Status:");
    for (const [status, count] of failuresByStatus) {
      console.log(`  ${status.padEnd(35)}: ${formatNumber(count)}`);
    }
    
    console.log("\nFailed by Reason (Top 10):");
    const sortedReasons = Array.from(failuresByReason.entries())
      .sort(([,a], [,b]) => b - a)
      .slice(0, 10);
    
    for (const [reason, count] of sortedReasons) {
      const truncatedReason = truncateString(reason, 60);
      console.log(`  ${truncatedReason.padEnd(60)}: ${formatNumber(count)}`);
    }
    console.log();

    // 3. Recent failures (last 24-48 hours)
    console.log("3. RECENT FAILURES ANALYSIS (Last 48 Hours)");
    console.log("------------------------------------------");
    
    const recentFailuresQuery = `
      SELECT * FROM transactions 
      WHERE swap_status IN ('failed_payment_verification', 'failed_cirx_transfer')
        AND updated_at >= datetime('now', '-48 hours')
      ORDER BY updated_at DESC
    `;
    
    const recentFailures = db.query(recentFailuresQuery);
    console.log(`Recent failures count: ${formatNumber(recentFailures.length)}`);
    
    if (recentFailures.length > 0) {
      // Group by hour
      const failuresByHour = new Map<string, number>();
      
      for (const row of recentFailures) {
        const updatedAt = String(row[12]); // updated_at column
        if (updatedAt) {
          const hourKey = updatedAt.substring(0, 13) + ':00'; // Extract YYYY-MM-DD HH:00
          failuresByHour.set(hourKey, (failuresByHour.get(hourKey) || 0) + 1);
        }
      }
      
      console.log("\nFailures by hour (Last 48h):");
      const sortedHours = Array.from(failuresByHour.entries()).sort();
      for (const [hour, count] of sortedHours) {
        console.log(`  ${hour}: ${formatNumber(count)} failures`);
      }
      
      console.log("\nMost recent failure examples:");
      for (let i = 0; i < Math.min(5, recentFailures.length); i++) {
        const row = recentFailures[i];
        const id = String(row[0]);
        const status = String(row[6]);
        const updatedAt = String(row[12]);
        const failureReason = String(row[8] || '');
        
        console.log(`  ID: ${id} | Status: ${status} | Time: ${updatedAt}`);
        if (failureReason) {
          console.log(`     Reason: ${truncateString(failureReason, 80)}`);
        }
      }
    }
    console.log();

    // 4. Stuck transactions analysis
    console.log("4. STUCK TRANSACTIONS ANALYSIS");
    console.log("-----------------------------");
    
    const stuckQuery = `
      SELECT * FROM transactions 
      WHERE swap_status IN ('cirx_transfer_pending', 'cirx_transfer_initiated', 'pending_payment_verification')
        AND updated_at < datetime('now', '-2 hours')
      ORDER BY updated_at ASC
    `;
    
    const stuckTransactions = db.query(stuckQuery);
    console.log(`Transactions stuck in processing (>2 hours): ${formatNumber(stuckTransactions.length)}`);
    
    if (stuckTransactions.length > 0) {
      const stuckByStatus = new Map<string, number>();
      
      for (const row of stuckTransactions) {
        const status = String(row[6]); // swap_status column
        stuckByStatus.set(status, (stuckByStatus.get(status) || 0) + 1);
      }
      
      console.log("Stuck by status:");
      for (const [status, count] of stuckByStatus) {
        console.log(`  ${status.padEnd(35)}: ${formatNumber(count)}`);
      }
      
      console.log("\nOldest stuck transactions:");
      for (let i = 0; i < Math.min(5, stuckTransactions.length); i++) {
        const row = stuckTransactions[i];
        const id = String(row[0]);
        const status = String(row[6]);
        const updatedAt = String(row[12]);
        
        console.log(`  ID: ${id} | Status: ${status} | Last Update: ${updatedAt}`);
      }
    }
    console.log();

    // 5. Retry analysis
    console.log("5. RETRY PATTERNS ANALYSIS");
    console.log("-------------------------");
    
    const retryQuery = `
      SELECT retry_count, COUNT(*) as count
      FROM transactions 
      WHERE retry_count > 0
      GROUP BY retry_count
      ORDER BY retry_count
    `;
    
    const retryResults = db.query(retryQuery);
    
    if (retryResults.length > 0) {
      let totalRetries = 0;
      let totalTransactionsWithRetries = 0;
      let maxRetries = 0;
      
      console.log("Retry count distribution:");
      for (const [retryCount, transactionCount] of retryResults) {
        const retryNum = retryCount as number;
        const transNum = transactionCount as number;
        
        totalRetries += retryNum * transNum;
        totalTransactionsWithRetries += transNum;
        maxRetries = Math.max(maxRetries, retryNum);
        
        console.log(`  ${retryNum} retries: ${formatNumber(transNum)} transactions`);
      }
      
      const avgRetries = totalTransactionsWithRetries > 0 ? totalRetries / totalTransactionsWithRetries : 0;
      
      console.log(`\nTransactions with retries: ${formatNumber(totalTransactionsWithRetries)}`);
      console.log(`  Max retries for single transaction: ${formatNumber(maxRetries)}`);
      console.log(`  Average retries per transaction: ${avgRetries.toFixed(2)}`);
      console.log(`  Total retry attempts: ${formatNumber(totalRetries)}`);
    } else {
      console.log("No transactions found with retries.");
    }
    console.log();

    // 6. Specific error patterns (dechex errors)
    console.log("6. SPECIFIC ERROR PATTERNS (From Cleanup Script)");
    console.log("-----------------------------------------------");
    
    const dechexQuery = `
      SELECT id, swap_status, failure_reason 
      FROM transactions 
      WHERE failure_reason LIKE '%dechex%' 
         OR failure_reason LIKE '%must be of type int%'
    `;
    
    const dechexErrors = db.query(dechexQuery);
    console.log(`Transactions with dechex/type errors: ${formatNumber(dechexErrors.length)}`);
    
    if (dechexErrors.length > 0) {
      console.log("Sample dechex errors:");
      for (let i = 0; i < Math.min(3, dechexErrors.length); i++) {
        const [id, status, reason] = dechexErrors[i];
        console.log(`  ID: ${String(id)} | Status: ${String(status)}`);
        console.log(`     Error: ${truncateString(String(reason || ''), 80)}`);
      }
    }
    console.log();

    // 7. Transaction flow success rates
    console.log("7. TRANSACTION FLOW SUCCESS RATES");
    console.log("--------------------------------");
    
    const completedCount = db.query(`SELECT COUNT(*) FROM transactions WHERE swap_status = 'completed'`)[0][0] as number;
    const failedCount = db.query(`SELECT COUNT(*) FROM transactions WHERE swap_status IN ('failed_payment_verification', 'failed_cirx_transfer')`)[0][0] as number;
    
    const successRate = formatPercentage(completedCount, totalTransactions);
    const failureRate = formatPercentage(failedCount, totalTransactions);
    
    console.log(`Success rate: ${successRate} (${formatNumber(completedCount)} completed out of ${formatNumber(totalTransactions)} total)`);
    console.log(`Failure rate: ${failureRate} (${formatNumber(failedCount)} failed out of ${formatNumber(totalTransactions)} total)`);

    // 8. Transaction age analysis
    console.log("\n8. TRANSACTION AGE ANALYSIS");
    console.log("--------------------------");
    
    const oldestQuery = `SELECT id, created_at FROM transactions ORDER BY created_at ASC LIMIT 1`;
    const newestQuery = `SELECT id, created_at FROM transactions ORDER BY created_at DESC LIMIT 1`;
    
    const oldestResult = db.query(oldestQuery);
    const newestResult = db.query(newestQuery);
    
    if (oldestResult.length > 0 && newestResult.length > 0) {
      const [oldestId, oldestCreatedAt] = oldestResult[0];
      const [newestId, newestCreatedAt] = newestResult[0];
      
      console.log(`Oldest transaction: ${String(oldestId)} (created ${String(oldestCreatedAt)})`);
      console.log(`Newest transaction: ${String(newestId)} (created ${String(newestCreatedAt)})`);
      
      const oldestDate = new Date(String(oldestCreatedAt));
      const newestDate = new Date(String(newestCreatedAt));
      const daysDiff = Math.floor((newestDate.getTime() - oldestDate.getTime()) / (1000 * 60 * 60 * 24));
      
      console.log(`Transaction history spans: ${formatNumber(daysDiff)} days`);
    }

    console.log("\n" + "=".repeat(60));
    console.log("Analysis completed successfully! 📊");

    // Close database connection
    db.close();

  } catch (error) {
    console.error("❌ Error during analysis:", error.message);
    if (error.stack) {
      console.error("Stack trace:", error.stack);
    }
    Deno.exit(1);
  }
}

// Run the analysis
if (import.meta.main) {
  await analyzeTransactionFailures();
}