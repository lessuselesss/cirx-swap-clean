// Event Listener Service for CIRX OTC Platform
import { createPublicClient, http, parseAbiItem, getContract } from 'viem';
import { config } from './config.js';
import { SimpleOTCSwapABI } from './abis/SimpleOTCSwap.js';
import { VestingContractABI } from './abis/VestingContract.js';
import { initDatabase } from './scripts/initDatabase.js';
import { 
  RpcError, 
  DatabaseError, 
  EventProcessingError, 
  ValidationError,
  withRetry,
  CircuitBreaker 
} from './utils/errors.js';
import { 
  eventLogger, 
  rpcLogger, 
  dbLogger, 
  PerformanceLogger,
  healthMetrics 
} from './utils/logger.js';

export class EventListener {
  constructor() {
    try {
      // Validate configuration
      this.validateConfig();
      
      // Initialize RPC client with circuit breaker
      this.rpcCircuitBreaker = new CircuitBreaker(5, 60000, 300000);
      this.client = createPublicClient({
        transport: http(config.rpc.url),
      });
      
      // Initialize database with error handling
      this.db = this.initializeDatabaseSafely();
      this.isRunning = false;
      this.lastProcessedBlock = this.getLastProcessedBlock();
      this.consecutiveErrors = 0;
      this.maxConsecutiveErrors = 10;
      
      // Prepare database statements for performance
      this.prepareDatabaseStatements();
      
      eventLogger.info('EventListener initialized successfully', {
        rpcUrl: config.rpc.url,
        startBlock: this.lastProcessedBlock
      });
      
    } catch (error) {
      eventLogger.critical('Failed to initialize EventListener', error);
      throw error;
    }
  }

  validateConfig() {
    const requiredFields = ['rpc.url', 'contracts.SimpleOTCSwap', 'contracts.VestingContract'];
    
    for (const field of requiredFields) {
      const value = field.split('.').reduce((obj, key) => obj?.[key], config);
      if (!value) {
        throw new ValidationError(`Missing required configuration: ${field}`);
      }
    }
    
    // Validate Ethereum addresses
    const addressFields = ['contracts.SimpleOTCSwap', 'contracts.VestingContract'];
    for (const field of addressFields) {
      const address = field.split('.').reduce((obj, key) => obj?.[key], config);
      if (address && address !== '0x0000000000000000000000000000000000000000' && !/^0x[a-fA-F0-9]{40}$/.test(address)) {
        throw new ValidationError(`Invalid Ethereum address for ${field}: ${address}`);
      }
    }
  }

  initializeDatabaseSafely() {
    try {
      return initDatabase();
    } catch (error) {
      throw new DatabaseError('Failed to initialize database', { 
        originalError: error.message,
        dbPath: config.database.path 
      });
    }
  }

  prepareDatabaseStatements() {
    try {
      this.insertSwap = this.db.prepare(`
        INSERT OR REPLACE INTO ${config.database.tables.swaps} 
        (tx_hash, block_number, block_timestamp, user_address, input_token, 
         input_amount, cirx_amount, swap_type, discount_bps, gas_used, gas_price)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
      `);
      
      this.insertVestingPosition = this.db.prepare(`
        INSERT OR REPLACE INTO ${config.database.tables.vestingPositions}
        (tx_hash, block_number, block_timestamp, user_address, total_amount, start_time, end_time)
        VALUES (?, ?, ?, ?, ?, ?, ?)
      `);
      
      this.insertClaim = this.db.prepare(`
        INSERT OR REPLACE INTO ${config.database.tables.claims}
        (tx_hash, block_number, block_timestamp, user_address, claimed_amount)
        VALUES (?, ?, ?, ?, ?)
      `);
      
      this.updateMetadata = this.db.prepare(`
        INSERT OR REPLACE INTO indexer_metadata (key, value, updated_at) 
        VALUES (?, ?, CURRENT_TIMESTAMP)
      `);

      dbLogger.info('Database statements prepared successfully');
    } catch (error) {
      throw new DatabaseError('Failed to prepare database statements', {
        originalError: error.message
      });
    }
  }

  getLastProcessedBlock() {
    try {
      const result = this.db.prepare('SELECT value FROM indexer_metadata WHERE key = ?')
        .get('last_processed_block');
      return result ? parseInt(result.value) : config.startBlock;
    } catch (error) {
      dbLogger.error('Failed to get last processed block', error);
      return config.startBlock;
    }
  }

  async start() {
    if (this.isRunning) {
      eventLogger.warn('Event listener is already running');
      return;
    }

    const perfLogger = new PerformanceLogger(eventLogger, 'start_event_listener');
    
    try {
      this.isRunning = true;
      eventLogger.info('Starting CIRX event listener', {
        rpcUrl: config.rpc.url,
        lastProcessedBlock: this.lastProcessedBlock,
        maxConsecutiveErrors: this.maxConsecutiveErrors
      });

      // Test RPC connectivity
      await this.testRpcConnection();
      perfLogger.checkpoint('rpc_connection_tested');

      // Sync historical events
      await this.syncHistoricalEvents();
      perfLogger.checkpoint('historical_sync_completed');

      // Start real-time monitoring
      this.startRealTimeListening();
      perfLogger.checkpoint('realtime_monitoring_started');

      perfLogger.complete(true);
      eventLogger.info('Event listener started successfully');
      
    } catch (error) {
      this.isRunning = false;
      healthMetrics.recordError(error, { operation: 'start_event_listener' });
      eventLogger.critical('Failed to start event listener', error);
      perfLogger.complete(false, { error: error.message });
      throw error;
    }
  }

  async testRpcConnection() {
    return withRetry(async () => {
      const startTime = performance.now();
      
      try {
        const blockNumber = await this.client.getBlockNumber();
        const responseTime = performance.now() - startTime;
        
        healthMetrics.recordRpcRequest(responseTime, true);
        rpcLogger.info('RPC connection test successful', {
          blockNumber: Number(blockNumber),
          responseTime: `${responseTime.toFixed(2)}ms`
        });
        
        return blockNumber;
      } catch (error) {
        const responseTime = performance.now() - startTime;
        healthMetrics.recordRpcRequest(responseTime, false);
        
        throw new RpcError('RPC connection test failed', {
          url: config.rpc.url,
          responseTime: `${responseTime.toFixed(2)}ms`,
          originalError: error.message
        });
      }
    }, 'RPC_ERROR', { operation: 'test_connection' });
  }

  async syncHistoricalEvents() {
    const perfLogger = new PerformanceLogger(eventLogger, 'sync_historical_events');
    
    try {
      eventLogger.info('Starting historical events sync');
      
      const currentBlock = await this.getRpcDataWithCircuitBreaker(
        () => this.client.getBlockNumber()
      );
      const fromBlock = BigInt(this.lastProcessedBlock);
      
      if (fromBlock >= currentBlock) {
        eventLogger.info('No historical events to sync', {
          fromBlock: Number(fromBlock),
          currentBlock: Number(currentBlock)
        });
        return;
      }

      const totalBlocks = Number(currentBlock - fromBlock);
      eventLogger.info('Syncing historical events', {
        fromBlock: Number(fromBlock),
        currentBlock: Number(currentBlock),
        totalBlocks
      });

      // Sync in chunks to avoid RPC limits
      const chunkSize = 10000n;
      let processedBlock = fromBlock;
      let processedChunks = 0;

      while (processedBlock < currentBlock) {
        const toBlock = processedBlock + chunkSize > currentBlock 
          ? currentBlock 
          : processedBlock + chunkSize;

        try {
          const chunkStartTime = performance.now();
          await this.processBlockRange(processedBlock, toBlock);
          const chunkTime = performance.now() - chunkStartTime;
          
          healthMetrics.recordBlockProcessing(chunkTime, Number(toBlock));
          
          processedBlock = toBlock + 1n;
          processedChunks++;
          
          // Update progress
          const progress = Number((processedBlock - fromBlock) * 100n / (currentBlock - fromBlock));
          eventLogger.info('Sync progress update', {
            progress: `${progress.toFixed(1)}%`,
            currentBlock: Number(processedBlock),
            chunksProcessed: processedChunks,
            chunkTimeMs: chunkTime.toFixed(2)
          });
          
          // Reset consecutive errors on successful chunk
          this.consecutiveErrors = 0;
          
        } catch (error) {
          this.consecutiveErrors++;
          healthMetrics.recordError(error, { 
            operation: 'process_block_range',
            fromBlock: Number(processedBlock),
            toBlock: Number(toBlock)
          });
          
          if (this.consecutiveErrors >= this.maxConsecutiveErrors) {
            throw new EventProcessingError(
              `Too many consecutive errors (${this.consecutiveErrors}), stopping sync`,
              { maxConsecutiveErrors: this.maxConsecutiveErrors }
            );
          }
          
          eventLogger.error('Error processing block range, continuing', error, {
            fromBlock: Number(processedBlock),
            toBlock: Number(toBlock),
            consecutiveErrors: this.consecutiveErrors
          });
          
          // Skip this chunk and continue
          processedBlock = toBlock + 1n;
        }
      }

      // Update last processed block
      await this.updateLastProcessedBlock(Number(currentBlock));
      
      perfLogger.complete(true, {
        totalBlocks,
        chunksProcessed,
        finalBlock: Number(currentBlock)
      });
      
      eventLogger.info('Historical sync completed successfully', {
        finalBlock: Number(currentBlock),
        totalBlocks,
        chunksProcessed
      });
      
    } catch (error) {
      healthMetrics.recordError(error, { operation: 'sync_historical_events' });
      eventLogger.error('Historical sync failed', error);
      perfLogger.complete(false, { error: error.message });
      throw error;
    }
  }

  async getRpcDataWithCircuitBreaker(operation) {
    return this.rpcCircuitBreaker.execute(
      () => withRetry(operation, 'RPC_ERROR')
    );
  }

  async updateLastProcessedBlock(blockNumber) {
    try {
      this.updateMetadata.run('last_processed_block', blockNumber.toString());
      this.lastProcessedBlock = blockNumber;
      dbLogger.debug('Updated last processed block', { blockNumber });
    } catch (error) {
      throw new DatabaseError('Failed to update last processed block', {
        blockNumber,
        originalError: error.message
      });
    }
  }

  async processBlockRange(fromBlock, toBlock) {
    const swapFilter = {
      address: config.contracts.SimpleOTCSwap,
      fromBlock,
      toBlock,
      events: [
        parseAbiItem('event LiquidSwap(address indexed user, address indexed inputToken, uint256 inputAmount, uint256 cirxAmount)'),
        parseAbiItem('event OTCSwap(address indexed user, address indexed inputToken, uint256 inputAmount, uint256 cirxAmount, uint256 discountBps)')
      ]
    };

    const vestingFilter = {
      address: config.contracts.VestingContract,
      fromBlock,
      toBlock,
      events: [
        parseAbiItem('event VestingPositionCreated(address indexed user, uint256 amount, uint256 startTime)'),
        parseAbiItem('event TokensClaimed(address indexed user, uint256 amount)')
      ]
    };

    // Get events in parallel
    const [swapLogs, vestingLogs] = await Promise.all([
      this.client.getLogs(swapFilter),
      this.client.getLogs(vestingFilter)
    ]);

    // Process events in transaction for consistency
    const transaction = this.db.transaction(() => {
      swapLogs.forEach(log => this.processSwapEvent(log));
      vestingLogs.forEach(log => this.processVestingEvent(log));
    });

    transaction();
  }

  async processSwapEvent(log) {
    try {
      // Get transaction receipt for gas information
      const receipt = await this.client.getTransactionReceipt({ hash: log.transactionHash });
      const block = await this.client.getBlock({ blockNumber: log.blockNumber });

      const isOTCSwap = log.topics[0] === parseAbiItem('event OTCSwap(address indexed user, address indexed inputToken, uint256 inputAmount, uint256 cirxAmount, uint256 discountBps)').signature;
      
      let decodedLog;
      if (isOTCSwap) {
        decodedLog = decodeEventLog({
          abi: SimpleOTCSwapABI,
          data: log.data,
          topics: log.topics,
          eventName: 'OTCSwap'
        });
      } else {
        decodedLog = decodeEventLog({
          abi: SimpleOTCSwapABI,
          data: log.data,
          topics: log.topics,
          eventName: 'LiquidSwap'
        });
      }

      this.insertSwap.run(
        log.transactionHash,
        Number(log.blockNumber),
        Number(block.timestamp),
        decodedLog.args.user,
        decodedLog.args.inputToken,
        decodedLog.args.inputAmount.toString(),
        decodedLog.args.cirxAmount.toString(),
        isOTCSwap ? 'otc' : 'liquid',
        isOTCSwap ? Number(decodedLog.args.discountBps) : 0,
        Number(receipt.gasUsed),
        receipt.effectiveGasPrice?.toString() || '0'
      );

      console.log(`💱 Indexed ${isOTCSwap ? 'OTC' : 'liquid'} swap: ${decodedLog.args.user}`);
    } catch (error) {
      console.error('❌ Error processing swap event:', error);
    }
  }

  async processVestingEvent(log) {
    try {
      const block = await this.client.getBlock({ blockNumber: log.blockNumber });
      
      const isVestingCreated = log.topics[0] === parseAbiItem('event VestingPositionCreated(address indexed user, uint256 amount, uint256 startTime)').signature;
      
      if (isVestingCreated) {
        const decodedLog = decodeEventLog({
          abi: VestingContractABI,
          data: log.data,
          topics: log.topics,
          eventName: 'VestingPositionCreated'
        });

        const VESTING_DURATION = 180 * 24 * 60 * 60; // 6 months in seconds
        const endTime = Number(decodedLog.args.startTime) + VESTING_DURATION;

        this.insertVestingPosition.run(
          log.transactionHash,
          Number(log.blockNumber),
          Number(block.timestamp),
          decodedLog.args.user,
          decodedLog.args.amount.toString(),
          Number(decodedLog.args.startTime),
          endTime
        );

        console.log(`🔒 Indexed vesting position: ${decodedLog.args.user}`);
      } else {
        // TokensClaimed event
        const decodedLog = decodeEventLog({
          abi: VestingContractABI,
          data: log.data,
          topics: log.topics,
          eventName: 'TokensClaimed'
        });

        this.insertClaim.run(
          log.transactionHash,
          Number(log.blockNumber),
          Number(block.timestamp),
          decodedLog.args.user,
          decodedLog.args.amount.toString()
        );

        console.log(`🎁 Indexed claim: ${decodedLog.args.user}`);
      }
    } catch (error) {
      console.error('❌ Error processing vesting event:', error);
    }
  }

  startRealTimeListening() {
    console.log('👂 Starting real-time event listening...');
    
    // Poll for new blocks
    setInterval(async () => {
      if (!this.isRunning) return;

      try {
        const currentBlock = await this.client.getBlockNumber();
        
        if (Number(currentBlock) > this.lastProcessedBlock) {
          const fromBlock = BigInt(this.lastProcessedBlock + 1);
          await this.processBlockRange(fromBlock, currentBlock);
          
          this.lastProcessedBlock = Number(currentBlock);
          this.updateMetadata.run('last_processed_block', this.lastProcessedBlock.toString());
        }
      } catch (error) {
        console.error('❌ Real-time sync error:', error);
      }
    }, config.rpc.pollingInterval);
  }

  stop() {
    console.log('🛑 Stopping event listener...');
    this.isRunning = false;
    this.db.close();
  }

  // Database query methods for API
  getTransactionHistory(userAddress, limit = 50, offset = 0) {
    return this.db.prepare(`
      SELECT * FROM ${config.database.tables.swaps} 
      WHERE user_address = ? 
      ORDER BY block_timestamp DESC 
      LIMIT ? OFFSET ?
    `).all(userAddress, limit, offset);
  }

  getVestingPositions(userAddress) {
    return this.db.prepare(`
      SELECT * FROM ${config.database.tables.vestingPositions} 
      WHERE user_address = ? 
      ORDER BY start_time DESC
    `).all(userAddress);
  }

  getClaimHistory(userAddress) {
    return this.db.prepare(`
      SELECT * FROM ${config.database.tables.claims} 
      WHERE user_address = ? 
      ORDER BY block_timestamp DESC
    `).all(userAddress);
  }

  getUserStats(userAddress) {
    const swapStats = this.db.prepare(`
      SELECT 
        COUNT(*) as total_swaps,
        SUM(CASE WHEN swap_type = 'liquid' THEN 1 ELSE 0 END) as liquid_swaps,
        SUM(CASE WHEN swap_type = 'otc' THEN 1 ELSE 0 END) as otc_swaps,
        SUM(CAST(cirx_amount AS REAL)) as total_cirx_purchased
      FROM ${config.database.tables.swaps} 
      WHERE user_address = ?
    `).get(userAddress);

    const vestingStats = this.db.prepare(`
      SELECT 
        COUNT(*) as total_positions,
        SUM(CAST(total_amount AS REAL)) as total_vesting_amount
      FROM ${config.database.tables.vestingPositions} 
      WHERE user_address = ?
    `).get(userAddress);

    return { ...swapStats, ...vestingStats };
  }
}

// Helper function import
import { decodeEventLog } from 'viem';