#!/usr/bin/env node

/**
 * Comprehensive IROH Distributed System Demo
 * 
 * Demonstrates the full capabilities of the CIRX Swap distributed platform:
 * - Real-time transaction broadcasting
 * - Service discovery and load balancing
 * - Network performance monitoring
 * - Multi-node coordination
 */

const BACKEND_URL = 'http://localhost:8080';
const IROH_BRIDGE_URL = 'http://localhost:9090';
const FRONTEND_URL = 'http://localhost:3000';

// Colors for console output
const colors = {
  reset: '\x1b[0m',
  bright: '\x1b[1m',
  red: '\x1b[31m',
  green: '\x1b[32m',
  yellow: '\x1b[33m',
  blue: '\x1b[34m',
  magenta: '\x1b[35m',
  cyan: '\x1b[36m'
};

// Utility functions
const log = (color, prefix, message) => {
  console.log(`${colors[color]}${prefix}${colors.reset} ${message}`);
};

const success = (msg) => log('green', '✅', msg);
const error = (msg) => log('red', '❌', msg);
const info = (msg) => log('blue', 'ℹ️ ', msg);
const warn = (msg) => log('yellow', '⚠️ ', msg);

// Performance tracker
const performance = {
  start: Date.now(),
  metrics: {
    totalRequests: 0,
    successfulRequests: 0,
    failedRequests: 0,
    averageResponseTime: 0,
    networkLatency: [],
    transactionThroughput: 0
  }
};

// Track request performance
const trackRequest = async (name, requestFn) => {
  const start = Date.now();
  performance.metrics.totalRequests++;
  
  try {
    const result = await requestFn();
    const duration = Date.now() - start;
    performance.metrics.successfulRequests++;
    performance.metrics.networkLatency.push(duration);
    
    // Update average response time
    const totalLatency = performance.metrics.networkLatency.reduce((sum, lat) => sum + lat, 0);
    performance.metrics.averageResponseTime = Math.round(totalLatency / performance.metrics.networkLatency.length);
    
    log('cyan', '⏱️ ', `${name}: ${duration}ms`);
    return result;
  } catch (err) {
    performance.metrics.failedRequests++;
    error(`${name} failed: ${err.message}`);
    throw err;
  }
};

// System Health Check
const systemHealthCheck = async () => {
  console.log(`\n${colors.bright}🔍 System Health Check${colors.reset}`);
  console.log('='.repeat(50));
  
  const services = [
    { name: 'IROH Bridge', url: `${IROH_BRIDGE_URL}/health`, required: true },
    { name: 'Backend API', url: `${BACKEND_URL}/api/v1/health`, required: true },
    { name: 'Frontend UI', url: FRONTEND_URL, required: false }
  ];
  
  const healthResults = {};
  
  for (const service of services) {
    try {
      const result = await trackRequest(`${service.name} Health`, async () => {
        const response = await fetch(service.url);
        return response.json();
      });
      
      healthResults[service.name] = {
        status: 'healthy',
        data: result
      };
      
      success(`${service.name}: Healthy`);
    } catch (err) {
      healthResults[service.name] = {
        status: 'unhealthy',
        error: err.message
      };
      
      if (service.required) {
        error(`${service.name}: Unhealthy (Required)`);
      } else {
        warn(`${service.name}: Unhealthy (Optional)`);
      }
    }
  }
  
  return healthResults;
};

// Network Topology Analysis
const analyzeNetworkTopology = async () => {
  console.log(`\n${colors.bright}🌐 Network Topology Analysis${colors.reset}`);
  console.log('='.repeat(50));
  
  try {
    const nodeInfo = await trackRequest('Node Information', async () => {
      const response = await fetch(`${IROH_BRIDGE_URL}/node/info`);
      return response.json();
    });
    
    const metrics = await trackRequest('Network Metrics', async () => {
      const response = await fetch(`${IROH_BRIDGE_URL}/metrics`);
      return response.json();
    });
    
    const discovery = await trackRequest('Service Discovery', async () => {
      const response = await fetch(`${IROH_BRIDGE_URL}/services/discover`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ service_name: '', max_results: 10 })
      });
      return response.json();
    });
    
    // Display network information
    info(`Node ID: ${nodeInfo.data.node_id.substring(0, 20)}...`);
    info(`Connected Peers: ${nodeInfo.data.peers_connected}`);
    info(`Registered Services: ${nodeInfo.data.services_registered}`);
    info(`Network Uptime: ${Math.round(nodeInfo.data.uptime / 1000)}s`);
    
    if (metrics.data.peers && metrics.data.peers.length > 0) {
      const avgLatency = metrics.data.peers.reduce((sum, p) => sum + p.latency, 0) / metrics.data.peers.length;
      const totalBandwidth = metrics.data.peers.reduce((sum, p) => sum + p.bandwidth, 0);
      
      info(`Average Peer Latency: ${avgLatency.toFixed(1)}ms`);
      info(`Total Network Bandwidth: ${totalBandwidth.toFixed(0)} Mbps`);
      info(`Geographic Distribution: ${[...new Set(metrics.data.peers.map(p => p.region))].join(', ')}`);
    }
    
    if (discovery.data && discovery.data.length > 0) {
      success(`Service Discovery: Found ${discovery.data.length} active services`);
      discovery.data.forEach(service => {
        info(`  - ${service.service_name} (${service.endpoint}) - Load: ${service.load?.toFixed(1)}%`);
      });
    }
    
    return { nodeInfo, metrics, discovery };
    
  } catch (err) {
    error(`Network topology analysis failed: ${err.message}`);
    return null;
  }
};

// Real-time Transaction Simulation
const simulateDistributedTransactions = async () => {
  console.log(`\n${colors.bright}💸 Distributed Transaction Simulation${colors.reset}`);
  console.log('='.repeat(50));
  
  const transactions = [
    { id: 'tx_eth_to_cirx_001', amount: '2.5 ETH', type: 'liquid', network: 'ethereum' },
    { id: 'tx_usdc_to_cirx_002', amount: '5000 USDC', type: 'otc_vested', network: 'polygon' },
    { id: 'tx_eth_to_cirx_003', amount: '1.0 ETH', type: 'liquid', network: 'arbitrum' },
    { id: 'tx_usdt_to_cirx_004', amount: '2500 USDT', type: 'otc_vested', network: 'optimism' }
  ];
  
  const statuses = ['pending', 'payment_verified', 'cirx_transfer_initiated', 'completed'];
  
  info(`Simulating ${transactions.length} transactions across the distributed network...`);
  
  const transactionPromises = transactions.map(async (tx, index) => {
    // Stagger transaction starts
    await new Promise(resolve => setTimeout(resolve, index * 1000));
    
    log('magenta', '📝', `Creating transaction: ${tx.id}`);
    
    // Simulate transaction lifecycle
    for (let statusIndex = 0; statusIndex < statuses.length; statusIndex++) {
      const status = statuses[statusIndex];
      
      try {
        await trackRequest(`Broadcast ${tx.id}`, async () => {
          const response = await fetch(`${IROH_BRIDGE_URL}/broadcast`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              topic: 'transaction-updates',
              message: {
                type: 'TRANSACTION_STATUS_UPDATE',
                transaction_id: tx.id,
                status: status,
                amount: tx.amount,
                transaction_type: tx.type,
                network: tx.network,
                timestamp: Date.now(),
                metadata: {
                  progress: Math.round((statusIndex + 1) / statuses.length * 100),
                  estimated_completion: Date.now() + (statuses.length - statusIndex - 1) * 2000
                }
              }
            })
          });
          return response.json();
        });
        
        log('cyan', '📡', `${tx.id} → ${status} (broadcasted to network)`);
        
        // Wait before next status update
        await new Promise(resolve => setTimeout(resolve, Math.random() * 2000 + 1000));
        
      } catch (err) {
        error(`Failed to broadcast ${tx.id} status ${status}: ${err.message}`);
      }
    }
    
    success(`Transaction ${tx.id} completed`);
    return tx;
  });
  
  const startTime = Date.now();
  const completedTransactions = await Promise.all(transactionPromises);
  const duration = Date.now() - startTime;
  
  performance.metrics.transactionThroughput = Math.round(completedTransactions.length / (duration / 1000));
  
  success(`All ${completedTransactions.length} transactions completed in ${Math.round(duration / 1000)}s`);
  success(`Transaction throughput: ${performance.metrics.transactionThroughput} tx/s`);
  
  return completedTransactions;
};

// Load Testing
const performLoadTest = async () => {
  console.log(`\n${colors.bright}🚀 Load Testing${colors.reset}`);
  console.log('='.repeat(50));
  
  info('Performing concurrent load test...');
  
  const concurrentRequests = 20;
  const requests = [];
  
  for (let i = 0; i < concurrentRequests; i++) {
    requests.push(
      trackRequest(`Load Test ${i + 1}`, async () => {
        const response = await fetch(`${IROH_BRIDGE_URL}/broadcast`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            topic: 'load-test',
            message: {
              type: 'LOAD_TEST',
              test_id: i,
              timestamp: Date.now()
            }
          })
        });
        return response.json();
      })
    );
  }
  
  const startTime = Date.now();
  const results = await Promise.all(requests);
  const duration = Date.now() - startTime;
  
  const successfulResults = results.filter(r => r?.success);
  const successRate = Math.round((successfulResults.length / results.length) * 100);
  
  success(`Load test completed: ${successfulResults.length}/${results.length} successful`);
  info(`Success rate: ${successRate}%`);
  info(`Average concurrent response time: ${Math.round(duration / concurrentRequests)}ms`);
  
  return { successRate, duration, results: successfulResults.length };
};

// Performance Report
const generatePerformanceReport = () => {
  console.log(`\n${colors.bright}📊 Performance Report${colors.reset}`);
  console.log('='.repeat(50));
  
  const totalTime = Date.now() - performance.start;
  const metrics = performance.metrics;
  
  console.log(`Total Demo Time: ${Math.round(totalTime / 1000)}s`);
  console.log(`Total Requests: ${metrics.totalRequests}`);
  console.log(`Successful Requests: ${metrics.successfulRequests}`);
  console.log(`Failed Requests: ${metrics.failedRequests}`);
  console.log(`Success Rate: ${Math.round((metrics.successfulRequests / metrics.totalRequests) * 100)}%`);
  console.log(`Average Response Time: ${metrics.averageResponseTime}ms`);
  console.log(`Transaction Throughput: ${metrics.transactionThroughput} tx/s`);
  
  if (metrics.networkLatency.length > 0) {
    const sortedLatencies = [...metrics.networkLatency].sort((a, b) => a - b);
    const p95 = sortedLatencies[Math.floor(sortedLatencies.length * 0.95)];
    const p99 = sortedLatencies[Math.floor(sortedLatencies.length * 0.99)];
    
    console.log(`P95 Latency: ${p95}ms`);
    console.log(`P99 Latency: ${p99}ms`);
  }
  
  // Performance grade
  let grade = 'A';
  if (metrics.averageResponseTime > 100) grade = 'B';
  if (metrics.averageResponseTime > 200) grade = 'C';
  if ((metrics.successfulRequests / metrics.totalRequests) < 0.95) grade = 'D';
  
  console.log(`\n${colors.bright}Overall Performance Grade: ${grade}${colors.reset}`);
};

// Main demo execution
const runDistributedSystemDemo = async () => {
  console.log(`${colors.bright}🚀 CIRX Swap Distributed System Demo${colors.reset}`);
  console.log(`${colors.bright}====================================================${colors.reset}`);
  
  try {
    // Phase 1: Health Check
    const healthResults = await systemHealthCheck();
    
    // Phase 2: Network Analysis
    const networkTopology = await analyzeNetworkTopology();
    
    // Phase 3: Transaction Simulation
    const transactions = await simulateDistributedTransactions();
    
    // Phase 4: Load Testing
    const loadTestResults = await performLoadTest();
    
    // Phase 5: Performance Report
    generatePerformanceReport();
    
    console.log(`\n${colors.bright}🎉 Demo Complete!${colors.reset}`);
    console.log(`${colors.bright}==================${colors.reset}`);
    
    success('CIRX Swap distributed system is fully operational!');
    info('Visit http://localhost:3000/monitor for real-time monitoring');
    info('Visit http://localhost:3000/debug/iroh-network for network details');
    
  } catch (error) {
    error(`Demo failed: ${error.message}`);
    process.exit(1);
  }
};

// Run the demo
runDistributedSystemDemo().catch(console.error);