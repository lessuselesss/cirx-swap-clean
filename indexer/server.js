// CIRX Indexer API Server
import Fastify from 'fastify';
import { EventListener } from './eventListener.js';
import { config } from './config.js';
import { 
  ValidationError, 
  DatabaseError, 
  IndexerError 
} from './utils/errors.js';
import { 
  apiLogger, 
  mainLogger, 
  PerformanceLogger,
  healthMetrics 
} from './utils/logger.js';

const fastify = Fastify({ 
  logger: {
    level: process.env.LOG_LEVEL || 'info',
    serializers: {
      req: (req) => ({
        method: req.method,
        url: req.url,
        hostname: req.hostname,
        remoteAddress: req.ip,
        userAgent: req.headers['user-agent']
      }),
      res: (res) => ({
        statusCode: res.statusCode,
        responseTime: res.getResponseTime ? res.getResponseTime() : undefined
      })
    }
  },
  disableRequestLogging: false,
  requestIdHeader: 'x-request-id',
  requestIdLogLabel: 'requestId'
});

// Enhanced request logging middleware
fastify.addHook('onRequest', async (request, reply) => {
  const startTime = performance.now();
  request.startTime = startTime;
  
  apiLogger.info('Incoming request', {
    method: request.method,
    url: request.url,
    ip: request.ip,
    userAgent: request.headers['user-agent'],
    requestId: request.id
  });
});

fastify.addHook('onResponse', async (request, reply) => {
  const responseTime = performance.now() - request.startTime;
  healthMetrics.recordApiRequest(responseTime);
  
  const logLevel = reply.statusCode >= 400 ? 'warn' : 'info';
  apiLogger[logLevel]('Request completed', {
    method: request.method,
    url: request.url,
    statusCode: reply.statusCode,
    responseTime: `${responseTime.toFixed(2)}ms`,
    requestId: request.id
  });
});

// Register CORS plugin
await fastify.register(import('@fastify/cors'), {
  origin: (origin, cb) => {
    // Allow localhost in development
    if (!origin || origin.includes('localhost') || origin.includes('127.0.0.1')) {
      cb(null, true);
      return;
    }
    
    // In production, add your allowed origins here
    const allowedOrigins = process.env.ALLOWED_ORIGINS?.split(',') || [];
    
    if (allowedOrigins.includes(origin)) {
      cb(null, true);
    } else {
      cb(new Error("CORS policy violation"), false);
    }
  },
  methods: ['GET', 'POST', 'PUT', 'DELETE'],
  allowedHeaders: ['Content-Type', 'Authorization', 'x-request-id']
});

// Initialize event listener with error handling
let eventListener;
try {
  eventListener = new EventListener();
  mainLogger.info('EventListener initialized successfully');
} catch (error) {
  mainLogger.critical('Failed to initialize EventListener', error);
  process.exit(1);
}

// Validation helper functions
function validateEthereumAddress(address) {
  if (!address || typeof address !== 'string') {
    throw new ValidationError('Address is required and must be a string');
  }
  
  if (!/^0x[a-fA-F0-9]{40}$/.test(address)) {
    throw new ValidationError(`Invalid Ethereum address format: ${address}`);
  }
  
  return address.toLowerCase();
}

function validatePaginationParams(query) {
  const limit = Math.min(parseInt(query.limit) || 50, 1000); // Max 1000 items
  const offset = Math.max(parseInt(query.offset) || 0, 0);
  
  if (limit < 1) {
    throw new ValidationError('Limit must be at least 1');
  }
  
  return { limit, offset };
}

// Error handling helper
function handleApiError(error, request, reply) {
  const requestId = request.id;
  const context = {
    method: request.method,
    url: request.url,
    requestId
  };

  healthMetrics.recordError(error, context);

  // Log the error
  if (error instanceof ValidationError) {
    apiLogger.warn('Validation error', error, context);
    return reply.code(400).send({
      error: 'Validation Error',
      message: error.message,
      code: error.code,
      requestId
    });
  } else if (error instanceof DatabaseError) {
    apiLogger.error('Database error', error, context);
    return reply.code(500).send({
      error: 'Database Error',
      message: 'Failed to query database',
      code: error.code,
      requestId
    });
  } else if (error instanceof IndexerError) {
    apiLogger.error('Indexer error', error, context);
    return reply.code(500).send({
      error: 'Indexer Error',
      message: error.message,
      code: error.code,
      requestId
    });
  } else {
    // Unknown error
    apiLogger.error('Unknown error', error, context);
    return reply.code(500).send({
      error: 'Internal Server Error',
      message: 'An unexpected error occurred',
      code: 'INTERNAL_ERROR',
      requestId
    });
  }
}

// Enhanced health check endpoint
fastify.get('/health', async (request, reply) => {
  try {
    const health = healthMetrics.getHealthStatus();
    const circuitBreakerStatus = eventListener.rpcCircuitBreaker?.getStatus();
    
    const response = {
      status: health.status,
      timestamp: new Date().toISOString(),
      indexer: {
        running: eventListener.isRunning,
        lastProcessedBlock: eventListener.lastProcessedBlock,
        consecutiveErrors: eventListener.consecutiveErrors
      },
      rpc: {
        circuitBreaker: circuitBreakerStatus,
        failureRate: health.rpcFailureRate
      },
      metrics: health.metrics,
      uptime: health.uptime
    };

    // Set appropriate HTTP status based on health
    const statusCode = health.status === 'healthy' ? 200 : 
                      health.status === 'degraded' ? 200 : 503;
    
    return reply.code(statusCode).send(response);
  } catch (error) {
    return handleApiError(error, request, reply);
  }
});

// Get transaction history for a user
fastify.get('/api/transactions/:address', async (request, reply) => {
  const perfLogger = new PerformanceLogger(apiLogger, 'get_transactions');
  
  try {
    // Validate and normalize address
    const normalizedAddress = validateEthereumAddress(request.params.address);
    perfLogger.checkpoint('address_validated');
    
    // Validate pagination parameters
    const { limit, offset } = validatePaginationParams(request.query);
    const { type } = request.query;
    
    // Validate transaction type filter
    if (type && !['liquid', 'otc'].includes(type)) {
      throw new ValidationError(`Invalid transaction type: ${type}. Must be 'liquid' or 'otc'`);
    }
    perfLogger.checkpoint('params_validated');

    // Query database with error handling
    let transactions;
    try {
      transactions = eventListener.getTransactionHistory(normalizedAddress, limit, offset);
      perfLogger.checkpoint('database_queried');
    } catch (dbError) {
      throw new DatabaseError('Failed to query transaction history', {
        address: normalizedAddress,
        limit,
        offset,
        originalError: dbError.message
      });
    }

    // Filter by swap type if requested
    if (type) {
      transactions = transactions.filter(tx => tx.swap_type === type);
      perfLogger.checkpoint('type_filtered');
    }

    // Format response with additional computed fields and error handling
    const formattedTransactions = transactions.map(tx => {
      try {
        return {
          ...tx,
          timestamp: new Date(tx.block_timestamp * 1000).toISOString(),
          input_amount_formatted: (BigInt(tx.input_amount) / BigInt(10 ** 18)).toString(),
          cirx_amount_formatted: (BigInt(tx.cirx_amount) / BigInt(10 ** 18)).toString(),
          discount_percentage: tx.discount_bps / 100,
          etherscan_url: `https://etherscan.io/tx/${tx.tx_hash}`
        };
      } catch (formatError) {
        apiLogger.warn('Failed to format transaction', formatError, {
          txHash: tx.tx_hash,
          address: normalizedAddress
        });
        // Return original transaction data if formatting fails
        return tx;
      }
    });
    perfLogger.checkpoint('transactions_formatted');

    const response = {
      success: true,
      data: {
        transactions: formattedTransactions,
        pagination: {
          limit,
          offset,
          total: formattedTransactions.length,
          hasMore: transactions.length === limit // Indicates there might be more data
        },
        filters: {
          address: normalizedAddress,
          type: type || 'all'
        }
      },
      requestId: request.id
    };

    perfLogger.complete(true, {
      transactionCount: formattedTransactions.length,
      address: normalizedAddress
    });

    return reply.send(response);
    
  } catch (error) {
    perfLogger.complete(false, { error: error.message });
    return handleApiError(error, request, reply);
  }
});

// Get vesting positions for a user
fastify.get('/api/vesting/:address', async (request, reply) => {
  const { address } = request.params;

  try {
    if (!/^0x[a-fA-F0-9]{40}$/.test(address)) {
      return reply.code(400).send({ error: 'Invalid Ethereum address format' });
    }

    const positions = eventListener.getVestingPositions(address.toLowerCase());
    const claims = eventListener.getClaimHistory(address.toLowerCase());

    // Calculate current vesting status for each position
    const now = Math.floor(Date.now() / 1000);
    const VESTING_DURATION = 180 * 24 * 60 * 60; // 6 months

    const formattedPositions = positions.map(position => {
      const elapsed = now - position.start_time;
      const progress = Math.min(elapsed / VESTING_DURATION, 1);
      
      // Get total claimed for this position
      const totalClaimed = claims
        .filter(claim => claim.user_address === address.toLowerCase())
        .reduce((sum, claim) => sum + BigInt(claim.claimed_amount), 0n);

      const totalAmount = BigInt(position.total_amount);
      const vestedAmount = BigInt(Math.floor(Number(totalAmount) * progress));
      const claimableAmount = vestedAmount - totalClaimed;

      return {
        ...position,
        timestamp: new Date(position.block_timestamp * 1000).toISOString(),
        start_date: new Date(position.start_time * 1000).toISOString(),
        end_date: new Date(position.end_time * 1000).toISOString(),
        total_amount_formatted: (totalAmount / BigInt(10 ** 18)).toString(),
        vested_amount: vestedAmount.toString(),
        vested_amount_formatted: (vestedAmount / BigInt(10 ** 18)).toString(),
        claimed_amount: totalClaimed.toString(),
        claimed_amount_formatted: (totalClaimed / BigInt(10 ** 18)).toString(),
        claimable_amount: claimableAmount > 0n ? claimableAmount.toString() : '0',
        claimable_amount_formatted: claimableAmount > 0n ? (claimableAmount / BigInt(10 ** 18)).toString() : '0',
        progress_percentage: (progress * 100).toFixed(2),
        status: progress >= 1 ? 'completed' : 'active',
        is_claimable: claimableAmount > 0n
      };
    });

    return {
      positions: formattedPositions,
      claims: claims.map(claim => ({
        ...claim,
        timestamp: new Date(claim.block_timestamp * 1000).toISOString(),
        amount_formatted: (BigInt(claim.claimed_amount) / BigInt(10 ** 18)).toString(),
        etherscan_url: `https://etherscan.io/tx/${claim.tx_hash}`
      }))
    };
  } catch (error) {
    fastify.log.error(error);
    return reply.code(500).send({ error: 'Failed to fetch vesting positions' });
  }
});

// Get user statistics summary
fastify.get('/api/stats/:address', async (request, reply) => {
  const { address } = request.params;

  try {
    if (!/^0x[a-fA-F0-9]{40}$/.test(address)) {
      return reply.code(400).send({ error: 'Invalid Ethereum address format' });
    }

    const stats = eventListener.getUserStats(address.toLowerCase());

    return {
      user_address: address.toLowerCase(),
      summary: {
        total_swaps: stats.total_swaps || 0,
        liquid_swaps: stats.liquid_swaps || 0,
        otc_swaps: stats.otc_swaps || 0,
        total_cirx_purchased: stats.total_cirx_purchased || 0,
        total_cirx_purchased_formatted: ((stats.total_cirx_purchased || 0) / 10**18).toFixed(2),
        total_vesting_positions: stats.total_positions || 0,
        total_vesting_amount: stats.total_vesting_amount || 0,
        total_vesting_amount_formatted: ((stats.total_vesting_amount || 0) / 10**18).toFixed(2)
      }
    };
  } catch (error) {
    fastify.log.error(error);
    return reply.code(500).send({ error: 'Failed to fetch user statistics' });
  }
});

// Get all recent transactions (for admin/monitoring)
fastify.get('/api/admin/recent', async (request, reply) => {
  const { limit = 100 } = request.query;

  try {
    const recentTransactions = eventListener.db.prepare(`
      SELECT * FROM ${config.database.tables.swaps} 
      ORDER BY block_timestamp DESC 
      LIMIT ?
    `).all(parseInt(limit));

    return {
      transactions: recentTransactions.map(tx => ({
        ...tx,
        timestamp: new Date(tx.block_timestamp * 1000).toISOString(),
        input_amount_formatted: (BigInt(tx.input_amount) / BigInt(10 ** 18)).toString(),
        cirx_amount_formatted: (BigInt(tx.cirx_amount) / BigInt(10 ** 18)).toString(),
      }))
    };
  } catch (error) {
    fastify.log.error(error);
    return reply.code(500).send({ error: 'Failed to fetch recent transactions' });
  }
});

// Indexer control endpoints
fastify.post('/api/admin/indexer/start', async (request, reply) => {
  try {
    await eventListener.start();
    return { message: 'Indexer started successfully' };
  } catch (error) {
    fastify.log.error(error);
    return reply.code(500).send({ error: 'Failed to start indexer' });
  }
});

fastify.post('/api/admin/indexer/stop', async (request, reply) => {
  try {
    eventListener.stop();
    return { message: 'Indexer stopped successfully' };
  } catch (error) {
    fastify.log.error(error);
    return reply.code(500).send({ error: 'Failed to stop indexer' });
  }
});

// Get indexer status
fastify.get('/api/admin/indexer/status', async (request, reply) => {
  try {
    const metadata = eventListener.db.prepare('SELECT * FROM indexer_metadata').all();
    const metadataObj = {};
    metadata.forEach(row => {
      metadataObj[row.key] = row.value;
    });

    return {
      running: eventListener.isRunning,
      last_processed_block: metadataObj.last_processed_block,
      status: metadataObj.indexer_status,
      uptime: process.uptime()
    };
  } catch (error) {
    fastify.log.error(error);
    return reply.code(500).send({ error: 'Failed to get indexer status' });
  }
});

// Rate limiting plugin
await fastify.register(import('@fastify/rate-limit'), {
  max: 100, // 100 requests
  timeWindow: '1 minute',
  cache: 10000,
  allowList: ['127.0.0.1'],
  keyGenerator: (request) => request.ip,
  errorResponseBuilder: (request, context) => {
    return {
      code: 429,
      error: 'Rate Limit Exceeded',
      message: `Too many requests. Rate limit: ${context.max} requests per ${context.timeWindow}`,
      retryAfter: context.ttl
    };
  },
  onExceeding: (request) => {
    apiLogger.warn('Rate limit exceeded', {
      ip: request.ip,
      url: request.url,
      userAgent: request.headers['user-agent']
    });
  }
});

// Global error handler with enhanced error handling
fastify.setErrorHandler((error, request, reply) => {
  // If we already handled this error, don't handle it again
  if (reply.sent) {
    return;
  }

  // Check if it's a known error type
  if (error instanceof ValidationError || 
      error instanceof DatabaseError || 
      error instanceof IndexerError) {
    return handleApiError(error, request, reply);
  }

  // Handle Fastify-specific errors
  if (error.statusCode) {
    const logLevel = error.statusCode >= 500 ? 'error' : 'warn';
    apiLogger[logLevel]('Fastify error', error, {
      statusCode: error.statusCode,
      method: request.method,
      url: request.url,
      requestId: request.id
    });

    return reply.code(error.statusCode).send({
      error: error.name || 'Request Error',
      message: error.message,
      statusCode: error.statusCode,
      requestId: request.id
    });
  }

  // Handle validation errors from Fastify schema validation
  if (error.validation) {
    apiLogger.warn('Schema validation error', error, {
      method: request.method,
      url: request.url,
      requestId: request.id
    });

    return reply.code(400).send({
      error: 'Validation Error',
      message: 'Request validation failed',
      details: error.validation,
      requestId: request.id
    });
  }

  // Unknown/unexpected errors
  healthMetrics.recordError(error, {
    method: request.method,
    url: request.url,
    requestId: request.id
  });

  apiLogger.error('Unhandled error', error, {
    method: request.method,
    url: request.url,
    requestId: request.id
  });

  return reply.code(500).send({
    error: 'Internal Server Error',
    message: 'An unexpected error occurred',
    requestId: request.id
  });
});

// Handle uncaught promise rejections
process.on('unhandledRejection', (reason, promise) => {
  mainLogger.critical('Unhandled promise rejection', new Error(reason), {
    promise: promise.toString()
  });
});

// Handle uncaught exceptions
process.on('uncaughtException', (error) => {
  mainLogger.critical('Uncaught exception', error);
  process.exit(1);
});

// Start the server
const start = async () => {
  try {
    // Start the indexer
    console.log('🚀 Starting CIRX Indexer API Server...');
    
    // Auto-start event listener
    await eventListener.start();
    
    // Start API server
    await fastify.listen({ 
      port: config.api.port, 
      host: config.api.host 
    });
    
    console.log(`✅ Server running on http://${config.api.host}:${config.api.port}`);
    console.log(`📊 API endpoints:`);
    console.log(`   GET  /api/transactions/:address - User transaction history`);
    console.log(`   GET  /api/vesting/:address - User vesting positions`);
    console.log(`   GET  /api/stats/:address - User statistics`);
    console.log(`   GET  /health - Health check`);
    
  } catch (error) {
    fastify.log.error(error);
    process.exit(1);
  }
};

// Graceful shutdown
process.on('SIGINT', async () => {
  console.log('\n🛑 Shutting down gracefully...');
  eventListener.stop();
  await fastify.close();
  process.exit(0);
});

start();