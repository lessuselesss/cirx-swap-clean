#!/usr/bin/env node
/**
 * Mock IROH Bridge for Testing
 * 
 * This is a simple Node.js server that mocks the IROH bridge API
 * for testing the integration without building the full Rust service.
 */

const http = require('http');
const url = require('url');

const PORT = 9090;

// Mock IROH node data
const mockNodeId = '12D3KooWExampleNodeId1234567890abcdef';
const mockServices = [
  {
    service_id: 'cirx-backend-1',
    node_id: mockNodeId,
    service_name: 'cirx-swap-backend',
    capabilities: ['transaction-processing', 'payment-verification'],
    last_seen: Date.now(),
    endpoint: 'http://localhost:8080'
  }
];

// Simple JSON response helper
function jsonResponse(res, statusCode, data) {
  res.writeHead(statusCode, {
    'Content-Type': 'application/json',
    'Access-Control-Allow-Origin': '*',
    'Access-Control-Allow-Methods': 'GET, POST, PUT, DELETE, OPTIONS',
    'Access-Control-Allow-Headers': 'Content-Type, Authorization'
  });
  res.end(JSON.stringify(data));
}

// Handle different routes
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

  // Health check
  if (path === '/health') {
    return jsonResponse(res, 200, {
      success: true,
      data: {
        status: 'healthy'
      },
      message: 'Mock IROH bridge is running',
      timestamp: Math.floor(Date.now() / 1000)
    });
  }

  // Node info
  if (path === '/node/info') {
    return jsonResponse(res, 200, {
      success: true,
      data: {
        node_id: mockNodeId,
        version: '0.28.1-mock',
        uptime: Math.floor(Date.now() / 1000),
        peers_connected: 3,
        services_registered: mockServices.length
      }
    });
  }

  // Node address
  if (path === '/node/address') {
    return jsonResponse(res, 200, {
      success: true,
      data: {
        node_id: mockNodeId,
        addresses: [
          '/ip4/127.0.0.1/udp/4001/quic-v1',
          '/ip4/192.168.1.100/udp/4001/quic-v1'
        ]
      }
    });
  }

  // Service discovery
  if (path === '/services/discover' && method === 'POST') {
    let body = '';
    req.on('data', chunk => body += chunk.toString());
    req.on('end', () => {
      try {
        const data = JSON.parse(body);
        const serviceName = data.service_name || '';
        const maxResults = data.max_results || 10;
        
        // Filter services by name if provided
        let results = mockServices;
        if (serviceName) {
          results = mockServices.filter(s => s.service_name.includes(serviceName));
        }
        results = results.slice(0, maxResults);

        return jsonResponse(res, 200, {
          success: true,
          data: results,
          query: data,
          total: results.length
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

  // Message broadcasting
  if (path === '/broadcast' && method === 'POST') {
    let body = '';
    req.on('data', chunk => body += chunk.toString());
    req.on('end', () => {
      try {
        const data = JSON.parse(body);
        console.log(`Broadcasting message to topic "${data.topic}":`, data.message);
        
        return jsonResponse(res, 200, {
          success: true,
          message: 'Message broadcast successfully',
          topic: data.topic,
          recipients: 2, // Mock recipient count
          timestamp: Math.floor(Date.now() / 1000)
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

  // Service registration
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
          endpoint: data.endpoint || 'unknown'
        };
        
        mockServices.push(newService);
        console.log('Registered new service:', newService);
        
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

  // Default 404
  return jsonResponse(res, 404, {
    success: false,
    error: 'Endpoint not found',
    available_endpoints: [
      'GET /health',
      'GET /node/info', 
      'GET /node/address',
      'POST /services/discover',
      'POST /services/register',
      'POST /broadcast'
    ]
  });
}

// Create and start server
const server = http.createServer(handleRequest);

server.listen(PORT, '0.0.0.0', () => {
  console.log(`🚀 Mock IROH Bridge running on http://localhost:${PORT}`);
  console.log(`🔍 Health check: http://localhost:${PORT}/health`);
  console.log(`📊 Node info: http://localhost:${PORT}/node/info`);
  console.log(`💬 Mock node ID: ${mockNodeId}`);
  console.log('');
  console.log('Available endpoints:');
  console.log('  GET  /health           - Health check');
  console.log('  GET  /node/info        - Node information');
  console.log('  GET  /node/address     - Node network addresses');
  console.log('  POST /services/discover - Discover services');
  console.log('  POST /services/register - Register service');
  console.log('  POST /broadcast        - Broadcast message');
});

// Graceful shutdown
process.on('SIGINT', () => {
  console.log('\n🛑 Shutting down Mock IROH Bridge...');
  server.close(() => {
    console.log('✅ Mock IROH Bridge stopped');
    process.exit(0);
  });
});