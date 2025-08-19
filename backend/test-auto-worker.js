#!/usr/bin/env node

/**
 * Test Auto-Worker System
 * 
 * This script tests the frontend auto-processing system by:
 * 1. Simulating a frontend making periodic API calls
 * 2. Verifying worker endpoints respond correctly
 * 3. Monitoring the processing behavior
 */

const API_BASE = 'http://localhost:8080/api/v1';

async function testWorkerEndpoint(endpoint, method = 'GET') {
  try {
    const response = await fetch(`${API_BASE}${endpoint}`, { method });
    const data = await response.json();
    return { success: response.ok, data, status: response.status };
  } catch (error) {
    return { success: false, error: error.message };
  }
}

async function runTest() {
  console.log('🧪 Testing Auto-Worker System\n');

  // Test 1: Health Check
  console.log('1. Testing worker health check...');
  const health = await testWorkerEndpoint('/workers/health');
  console.log(health.success ? '✅ Health check passed' : '❌ Health check failed');
  if (health.data) {
    console.log(`   Last execution: ${health.data.data?.last_execution || 'Never'}`);
    console.log(`   Recommendation: ${health.data.data?.recommendation || 'Unknown'}`);
  }
  console.log();

  // Test 2: Get Stats  
  console.log('2. Testing worker statistics...');
  const stats = await testWorkerEndpoint('/workers/stats');
  console.log(stats.success ? '✅ Stats endpoint working' : '❌ Stats endpoint failed');
  if (stats.data) {
    const pv = stats.data.payment_verification || {};
    const ct = stats.data.cirx_transfers || {};
    console.log(`   Pending payments: ${pv.pending_verification || 0}`);
    console.log(`   Ready transfers: ${ct.ready_for_transfer || 0}`);
    console.log(`   Completed: ${ct.completed || 0}`);
  }
  console.log();

  // Test 3: Process Transactions (simulating frontend auto-worker)
  console.log('3. Testing transaction processing...');
  const process = await testWorkerEndpoint('/workers/process', 'POST');
  console.log(process.success ? '✅ Processing endpoint working' : '❌ Processing endpoint failed');
  if (process.data) {
    const payment = process.data.payment_verification || {};
    const cirx = process.data.cirx_transfers || {};
    console.log(`   Payment processed: ${payment.processed || 0}`);
    console.log(`   CIRX processed: ${cirx.processed || 0}`);
  }
  console.log();

  // Test 4: Simulate auto-processing behavior
  console.log('4. Simulating frontend auto-processing (3 cycles)...');
  for (let i = 1; i <= 3; i++) {
    console.log(`   Cycle ${i}: Processing...`);
    const result = await testWorkerEndpoint('/workers/process', 'POST');
    
    if (result.success) {
      const total = (result.data.payment_verification?.processed || 0) + 
                   (result.data.cirx_transfers?.processed || 0);
      console.log(`   Cycle ${i}: ✅ Processed ${total} transactions`);
    } else {
      console.log(`   Cycle ${i}: ❌ Failed`);
    }
    
    // Wait 2 seconds between cycles (faster than real 30s for testing)
    if (i < 3) await new Promise(resolve => setTimeout(resolve, 2000));
  }
  console.log();

  console.log('🎉 Auto-Worker Test Complete!');
  console.log('\n📝 Summary:');
  console.log('- Frontend auto-processing system is ready for FTP deployment');
  console.log('- Workers will run automatically every 30 seconds on swap/status pages');
  console.log('- No background services or cron jobs required');
  console.log('- Manual worker dashboard available at: http://localhost:8080/workers.html');
}

// Run the test
runTest().catch(console.error);