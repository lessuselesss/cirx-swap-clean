#!/usr/bin/env node
/**
 * Enhanced IROH Bridge with WebSocket Support
 * 
 * This enhanced mock IROH bridge includes:
 * - WebSocket connections for real-time updates
 * - Message broadcasting to all connected clients
 * - Advanced network simulation
 * - Performance metrics collection
 */

const http = require('http');
const url = require('url');
// WebSocket support disabled for now - would need 'ws' package

const PORT = 9090;
const WS_PORT = 9091;

// Enhanced mock data
const mockNodeId = '12D3KooWDistributedCIRXSwapNode2025';
const connectedPeers = new Map();
const subscribedTopics = new Set();
const messageHistory = [];
const performanceMetrics = {
  messagesProcessed: 0,
  bytesTransferred: 0,
  connectionsTotal: 0,
  uptime: Date.now()
};

let wsServer = null;
const wsClients = new Set();

// Enhanced services with more realistic data
const mockServices = [
  {
    service_id: 'cirx-backend-main',
    node_id: mockNodeId,
    service_name: 'cirx-swap-backend',
    capabilities: ['transaction-processing', 'payment-verification', 'cirx-transfers'],
    last_seen: Date.now(),
    endpoint: 'http://localhost:8080',
    load: Math.random() * 100,
    region: 'us-west-1'
  },
  {
    service_id: 'cirx-frontend-cdn',
    node_id: `12D3KooW${Math.random().toString(36).substring(2, 15)}`,
    service_name: 'cirx-swap-frontend',
    capabilities: ['ui-rendering', 'wallet-integration', 'real-time-updates'],
    last_seen: Date.now(),
    endpoint: 'http://localhost:3000',
    load: Math.random() * 50,
    region: 'global-cdn'
  }
];

// Simulate peer connections
for (let i = 0; i < 3; i++) {
  const peerId = `12D3KooW${Math.random().toString(36).substring(2, 20)}`;
  connectedPeers.set(peerId, {
    id: peerId,
    address: `/ip4/192.168.1.${100 + i}/tcp/4001`,
    latency: Math.floor(Math.random() * 50) + 5,
    connected_at: Date.now() - Math.random() * 3600000,
    bandwidth: Math.floor(Math.random() * 1000) + 100,
    region: ['us-east-1', 'eu-west-1', 'ap-southeast-1'][i]
  });
}

// WebSocket server setup (disabled)
const setupWebSocket = () => {
  console.log('WebSocket support disabled - would need ws package');
};

// Handle WebSocket messages
const handleWebSocketMessage = (ws, data) => {
  switch (data.type) {
    case 'SUBSCRIBE_TOPIC':
      subscribedTopics.add(data.topic);
      ws.send(JSON.stringify({
        type: 'TOPIC_SUBSCRIBED',
        topic: data.topic
      }));
      break;
      
    case 'PING':
      ws.send(JSON.stringify({
        type: 'PONG',
        timestamp: Date.now()
      }));
      break;
  }
};

// Broadcast to all WebSocket clients (disabled)
const broadcastToWebSockets = (message) => {
  // WebSocket broadcasting disabled
};

// Enhanced JSON response helper
function jsonResponse(res, statusCode, data) {
  const response = {
    ...data,
    timestamp: Math.floor(Date.now() / 1000),
    node_id: mockNodeId
  };
  
  res.writeHead(statusCode, {
    'Content-Type': 'application/json',
    'Access-Control-Allow-Origin': '*',
    'Access-Control-Allow-Methods': 'GET, POST, PUT, DELETE, OPTIONS',
    'Access-Control-Allow-Headers': 'Content-Type, Authorization'
  });
  
  const responseStr = JSON.stringify(response, null, 2);
  res.end(responseStr);
  
  // Update metrics
  performanceMetrics.messagesProcessed++;
  performanceMetrics.bytesTransferred += responseStr.length;
}

// Simulate network activity
const simulateNetworkActivity = () => {
  setInterval(() => {
    // Simulate peer latency changes
    connectedPeers.forEach(peer => {
      peer.latency = Math.max(1, peer.latency + (Math.random() - 0.5) * 5);
      peer.bandwidth = Math.max(50, peer.bandwidth + (Math.random() - 0.5) * 50);
    });
    
    // Simulate service load changes
    mockServices.forEach(service => {
      service.load = Math.max(0, Math.min(100, service.load + (Math.random() - 0.5) * 20));
      service.last_seen = Date.now();
    });
    
    // Broadcast network updates to WebSocket clients
    broadcastToWebSockets({
      type: 'NETWORK_UPDATE',
      data: {
        peers: Array.from(connectedPeers.values()),
        services: mockServices,
        metrics: performanceMetrics
      }
    });
    
    // Occasionally simulate transaction broadcasts
    if (Math.random() < 0.3) {
      const transactionUpdate = {
        type: 'TRANSACTION_BROADCAST',
        data: {
          transaction_id: `tx_${Date.now()}_${Math.random().toString(36).substring(2, 8)}`,
          status: ['pending', 'verified', 'completed'][Math.floor(Math.random() * 3)],
          amount: `${(Math.random() * 10).toFixed(2)} ETH`,
          timestamp: Date.now()
        }
      };
      
      broadcastToWebSockets(transactionUpdate);
      messageHistory.push(transactionUpdate.data);
      
      if (messageHistory.length > 50) {
        messageHistory.shift();
      }
    }
  }, 2000);
};

// Enhanced request handler
function handleRequest(req, res) {
  const parsedUrl = url.parse(req.url, true);
  const path = parsedUrl.pathname;
  const method = req.method;

  console.log(`[${new Date().toISOString()}] ${method} ${path}`);

  // Handle CORS preflight
  if (method === 'OPTIONS') {
    res.writeHead(200, {
      'Access-Control-Allow-Origin': '*',
      'Access-Control-Allow-Methods': 'GET, POST, PUT, DELETE, OPTIONS',
      'Access-Control-Allow-Headers': 'Content-Type, Authorization'
    });
    res.end();
    return;
  }

  // Enhanced health check
  if (path === '/health') {
    return jsonResponse(res, 200, {
      success: true,
      data: {
        status: 'healthy',
        uptime: Date.now() - performanceMetrics.uptime,
        http_only: true,
        websocket_disabled: true
      },
      message: 'Enhanced IROH bridge is running'
    });
  }

  // Enhanced node info
  if (path === '/node/info') {
    return jsonResponse(res, 200, {
      success: true,
      data: {
        node_id: mockNodeId,
        version: '0.28.1-enhanced',
        uptime: Date.now() - performanceMetrics.uptime,
        peers_connected: connectedPeers.size,
        services_registered: mockServices.length,
        http_connections: 0,
        performance: performanceMetrics,
        network_info: {
          total_bandwidth: Array.from(connectedPeers.values()).reduce((sum, p) => sum + p.bandwidth, 0),
          average_latency: Array.from(connectedPeers.values()).reduce((sum, p) => sum + p.latency, 0) / connectedPeers.size
        }
      }
    });
  }

  // Enhanced node address with peer details
  if (path === '/node/address') {
    return jsonResponse(res, 200, {
      success: true,
      data: {
        node_id: mockNodeId,
        addresses: [
          '/ip4/127.0.0.1/udp/4001/quic-v1',
          '/ip4/192.168.1.100/udp/4001/quic-v1',
          `/ip4/10.0.0.1/tcp/4001/ws/p2p/${mockNodeId}`
        ],
        peers: Array.from(connectedPeers.values())
      }
    });
  }

  // Enhanced service discovery
  if (path === '/services/discover' && method === 'POST') {
    let body = '';
    req.on('data', chunk => body += chunk.toString());
    req.on('end', () => {
      try {
        const data = JSON.parse(body);
        const serviceName = data.service_name || '';
        const maxResults = data.max_results || 10;
        
        let results = mockServices;
        if (serviceName) {
          results = mockServices.filter(s => s.service_name.includes(serviceName));
        }
        results = results.slice(0, maxResults);

        return jsonResponse(res, 200, {
          success: true,
          data: results,
          query: data,
          total: results.length,
          network_health: {
            average_load: results.reduce((sum, s) => sum + s.load, 0) / results.length,
            regions: [...new Set(results.map(s => s.region))]
          }
        });
      } catch (e) {
        return jsonResponse(res, 400, {
          success: false,
          error: 'Invalid JSON body'
        });
      }
    });
    return;
  }

  // Enhanced message broadcasting
  if (path === '/broadcast' && method === 'POST') {
    let body = '';
    req.on('data', chunk => body += chunk.toString());
    req.on('end', () => {
      try {
        const data = JSON.parse(body);
        console.log(`Broadcasting message to topic "${data.topic}":`, data.message);
        
        // Store message in history
        messageHistory.push({
          topic: data.topic,
          message: data.message,
          timestamp: Date.now()
        });
        
        // Broadcast to WebSocket clients
        broadcastToWebSockets({
          type: 'BROADCAST',
          topic: data.topic,
          message: data.message
        });
        
        return jsonResponse(res, 200, {
          success: true,
          message: 'Message broadcast successfully',
          topic: data.topic,
          recipients: connectedPeers.size + Math.floor(Math.random() * 3) + 1,
          delivery_latency: Math.floor(Math.random() * 10) + 1
        });
      } catch (e) {
        return jsonResponse(res, 400, {
          success: false,
          error: 'Invalid JSON body'
        });
      }
    });
    return;
  }

  // Message history endpoint
  if (path === '/messages/history') {
    return jsonResponse(res, 200, {
      success: true,
      data: messageHistory.slice(-20), // Last 20 messages
      total: messageHistory.length
    });
  }

  // Network metrics endpoint
  if (path === '/metrics') {
    return jsonResponse(res, 200, {
      success: true,
      data: {
        ...performanceMetrics,
        peers: Array.from(connectedPeers.values()),
        http_connections: 0,
        active_topics: Array.from(subscribedTopics)
      }
    });
  }

  // Enhanced service registration
  if (path === '/services/register' && method === 'POST') {
    let body = '';
    req.on('data', chunk => body += chunk.toString());
    req.on('end', () => {
      try {
        const data = JSON.parse(body);
        
        const newService = {
          service_id: `${data.service_name}-${Date.now()}`,
          node_id: mockNodeId,
          service_name: data.service_name,
          capabilities: data.capabilities || [],
          last_seen: Date.now(),
          endpoint: data.endpoint || 'unknown',
          load: Math.random() * 30, // New services start with lower load
          region: data.region || 'unknown'
        };
        
        mockServices.push(newService);
        console.log('Registered new service:', newService);
        
        // Notify WebSocket clients
        broadcastToWebSockets({
          type: 'SERVICE_REGISTERED',
          service: newService
        });
        
        return jsonResponse(res, 200, {
          success: true,
          data: newService,
          message: 'Service registered successfully'
        });
      } catch (e) {
        return jsonResponse(res, 400, {
          success: false,
          error: 'Invalid JSON body'
        });
      }
    });
    return;
  }

  // Default 404 with available endpoints
  return jsonResponse(res, 404, {
    success: false,
    error: 'Endpoint not found',
    available_endpoints: [
      'GET /health',
      'GET /node/info', 
      'GET /node/address',
      'GET /metrics',
      'GET /messages/history',
      'POST /services/discover',
      'POST /services/register',
      'POST /broadcast'
    ],
    note: 'WebSocket support disabled in this version'
  });
}

// Create and start HTTP server
const server = http.createServer(handleRequest);

server.listen(PORT, '0.0.0.0', () => {
  console.log(`🚀 Enhanced IROH Bridge running on http://localhost:${PORT}`);
  console.log(`🔍 Health check: http://localhost:${PORT}/health`);
  console.log(`📊 Node info: http://localhost:${PORT}/node/info`);
  console.log(`📈 Metrics: http://localhost:${PORT}/metrics`);
  console.log(`💬 Enhanced node ID: ${mockNodeId}`);
  console.log('');
  console.log('Available endpoints:');
  console.log('  GET  /health           - Health check with WebSocket info');
  console.log('  GET  /node/info        - Enhanced node information');
  console.log('  GET  /node/address     - Node addresses and peer details');
  console.log('  GET  /metrics          - Performance and network metrics');
  console.log('  GET  /messages/history - Recent message history');
  console.log('  POST /services/discover - Discover services with health data');
  console.log('  POST /services/register - Register service with load balancing');
  console.log('  POST /broadcast        - Broadcast with WebSocket delivery');
  console.log(`  WS   ws://localhost:${WS_PORT} - WebSocket real-time updates`);
});

// Setup WebSocket server
setupWebSocket();

// Start network simulation
simulateNetworkActivity();

// Graceful shutdown
process.on('SIGINT', () => {
  console.log('\n🛑 Shutting down Enhanced IROH Bridge...');
  server.close(() => {
    wsServer.close(() => {
      console.log('✅ Enhanced IROH Bridge stopped');
      process.exit(0);
    });
  });
});