#!/usr/bin/env node

/**
 * Test IROH Real-time Transaction Updates
 * 
 * This script simulates a complete transaction flow with IROH broadcasting
 */

const BACKEND_URL = 'http://localhost:8080';
const IROH_BRIDGE_URL = 'http://localhost:9090';

// Helper function to make HTTP requests using built-in fetch (Node 18+)
async function makeRequest(url, options = {}) {
  const response = await fetch(url, {
    headers: {
      'Content-Type': 'application/json',
      ...options.headers
    },
    ...options
  });
  return response.json();
}

// Simulate creating a transaction
async function createTransaction() {
  console.log('📝 Creating new transaction...');
  
  const txData = {
    from_token: 'ETH',
    to_token: 'CIRX',
    amount: '1.5',
    recipient_address: '0xe184d1a551b4c0a5a21a90c72e238692c1bb84b5c06b832c37cc0f397ab28443',
    transaction_type: 'liquid',
    payment_hash: `0x${Math.random().toString(16).substr(2, 64)}`,
    network: 'ethereum'
  };

  try {
    const response = await makeRequest(`${BACKEND_URL}/api/v1/swap/create`, {
      method: 'POST',
      body: JSON.stringify(txData)
    });

    console.log('✅ Transaction created:', response);
    return response.data?.transaction_id || response.transaction_id;
  } catch (error) {
    console.error('❌ Failed to create transaction:', error);
    return null;
  }
}

// Broadcast transaction update via IROH
async function broadcastTransactionUpdate(transactionId, status) {
  console.log(`📡 Broadcasting ${status} status for transaction ${transactionId}...`);
  
  const updateData = {
    topic: 'transaction-updates',
    message: {
      type: 'TRANSACTION_STATUS_UPDATE',
      transaction_id: transactionId,
      status: status,
      metadata: {
        amount: '1.5 ETH',
        cirx_amount: '1500 CIRX',
        discount: '0%',
        timestamp: Date.now()
      }
    }
  };

  try {
    const response = await makeRequest(`${IROH_BRIDGE_URL}/broadcast`, {
      method: 'POST',
      body: JSON.stringify(updateData)
    });

    console.log(`✅ Broadcast successful to ${response.recipients || 0} recipients`);
    return true;
  } catch (error) {
    console.error('❌ Broadcast failed:', error);
    return false;
  }
}

// Check transaction status via enhanced endpoint
async function checkTransactionStatus(transactionId) {
  console.log(`🔍 Checking real-time status for transaction ${transactionId}...`);
  
  try {
    const response = await makeRequest(
      `${BACKEND_URL}/api/v1/transactions/${transactionId}/status/realtime`
    );

    console.log('📊 Transaction status:', response);
    return response;
  } catch (error) {
    console.error('❌ Failed to check status:', error);
    return null;
  }
}

// Monitor network activity
async function monitorNetworkActivity() {
  console.log('👁️ Monitoring IROH network activity...');
  
  try {
    // Check IROH status
    const irohStatus = await makeRequest(`${BACKEND_URL}/api/v1/iroh/status`);
    console.log('🌐 IROH Network Status:', irohStatus);

    // Discover services
    const services = await makeRequest(`${BACKEND_URL}/api/v1/iroh/discover`, {
      method: 'POST'
    });
    console.log('🔍 Discovered Services:', services.data);

    // Get node info from bridge
    const nodeInfo = await makeRequest(`${IROH_BRIDGE_URL}/node/info`);
    console.log('📊 Node Information:', nodeInfo.data);

  } catch (error) {
    console.error('❌ Monitoring failed:', error);
  }
}

// Main test flow
async function runTest() {
  console.log('🚀 Starting IROH Transaction Test\n');
  console.log('=' .repeat(50));
  
  // Step 1: Check network status
  await monitorNetworkActivity();
  console.log('\n' + '=' .repeat(50) + '\n');

  // Step 2: Create a transaction
  const transactionId = await createTransaction();
  if (!transactionId) {
    console.log('⚠️ Using mock transaction ID for testing');
    const mockTxId = `test_tx_${Date.now()}`;
    
    // Broadcast with mock ID
    await broadcastTransactionUpdate(mockTxId, 'pending');
    await new Promise(resolve => setTimeout(resolve, 2000));
    
    await broadcastTransactionUpdate(mockTxId, 'payment_verified');
    await new Promise(resolve => setTimeout(resolve, 2000));
    
    await broadcastTransactionUpdate(mockTxId, 'cirx_transfer_initiated');
    await new Promise(resolve => setTimeout(resolve, 2000));
    
    await broadcastTransactionUpdate(mockTxId, 'completed');
    
    console.log('\n' + '=' .repeat(50));
    console.log('✅ Test completed with mock transaction');
    return;
  }

  console.log('\n' + '=' .repeat(50) + '\n');

  // Step 3: Simulate transaction lifecycle with broadcasts
  const statuses = [
    'payment_detected',
    'payment_verified',
    'cirx_transfer_initiated',
    'cirx_transfer_completed',
    'completed'
  ];

  for (const status of statuses) {
    await broadcastTransactionUpdate(transactionId, status);
    await checkTransactionStatus(transactionId);
    
    // Wait 3 seconds between status updates
    await new Promise(resolve => setTimeout(resolve, 3000));
  }

  console.log('\n' + '=' .repeat(50));
  console.log('🎉 IROH Transaction Test Complete!');
  console.log('=' .repeat(50));
  
  // Final network check
  console.log('\n📊 Final Network Status:');
  await monitorNetworkActivity();
}

// Run the test
runTest().catch(console.error);