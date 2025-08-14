// Enhanced logging system for CIRX Indexer
import { formatError, ErrorSeverity } from './errors.js';

const LogLevel = {
  DEBUG: 0,
  INFO: 1,
  WARN: 2,
  ERROR: 3,
  CRITICAL: 4
};

class Logger {
  constructor(options = {}) {
    this.level = LogLevel[options.level?.toUpperCase()] ?? LogLevel.INFO;
    this.enableConsole = options.enableConsole ?? true;
    this.enableFile = options.enableFile ?? false;
    this.component = options.component || 'INDEXER';
    this.colors = {
      DEBUG: '\x1b[36m',   // Cyan
      INFO: '\x1b[32m',    // Green
      WARN: '\x1b[33m',    // Yellow
      ERROR: '\x1b[31m',   // Red
      CRITICAL: '\x1b[35m', // Magenta
      RESET: '\x1b[0m'
    };
  }

  debug(message, data = {}) {
    this.log('DEBUG', message, data);
  }

  info(message, data = {}) {
    this.log('INFO', message, data);
  }

  warn(message, data = {}) {
    this.log('WARN', message, data);
  }

  error(message, error = null, data = {}) {
    const logData = { ...data };
    
    if (error) {
      logData.error = formatError(error, data.context);
    }
    
    this.log('ERROR', message, logData);
  }

  critical(message, error = null, data = {}) {
    const logData = { ...data };
    
    if (error) {
      logData.error = formatError(error, data.context);
    }
    
    this.log('CRITICAL', message, logData);
  }

  log(level, message, data = {}) {
    const levelNum = LogLevel[level];
    
    if (levelNum < this.level) {
      return;
    }

    const timestamp = new Date().toISOString();
    const logEntry = {
      timestamp,
      level,
      component: this.component,
      message,
      ...data
    };

    if (this.enableConsole) {
      this.logToConsole(level, logEntry);
    }

    if (this.enableFile) {
      this.logToFile(logEntry);
    }
  }

  logToConsole(level, entry) {
    const color = this.colors[level] || '';
    const reset = this.colors.RESET;
    const prefix = `${color}[${entry.timestamp}] ${level.padEnd(8)} [${entry.component}]${reset}`;
    
    switch (level) {
      case 'DEBUG':
      case 'INFO':
        console.log(`${prefix} ${entry.message}`, entry.error ? entry.error : '');
        break;
      case 'WARN':
        console.warn(`${prefix} ${entry.message}`, entry.error ? entry.error : '');
        break;
      case 'ERROR':
      case 'CRITICAL':
        console.error(`${prefix} ${entry.message}`, entry.error ? entry.error : '');
        break;
    }

    // Pretty print additional data (excluding standard fields)
    const additionalData = { ...entry };
    delete additionalData.timestamp;
    delete additionalData.level;
    delete additionalData.component;
    delete additionalData.message;
    delete additionalData.error;

    if (Object.keys(additionalData).length > 0) {
      console.log('  Data:', JSON.stringify(additionalData, null, 2));
    }
  }

  logToFile(entry) {
    // Implement file logging if needed
    // For now, we'll keep it simple with console logging
  }

  // Create child logger with additional context
  child(context = {}) {
    return new ContextLogger(this, context);
  }
}

// Context logger that adds persistent context to all log entries
class ContextLogger {
  constructor(parentLogger, context) {
    this.parent = parentLogger;
    this.context = context;
  }

  debug(message, data = {}) {
    this.parent.debug(message, { ...this.context, ...data });
  }

  info(message, data = {}) {
    this.parent.info(message, { ...this.context, ...data });
  }

  warn(message, data = {}) {
    this.parent.warn(message, { ...this.context, ...data });
  }

  error(message, error = null, data = {}) {
    this.parent.error(message, error, { ...this.context, ...data });
  }

  critical(message, error = null, data = {}) {
    this.parent.critical(message, error, { ...this.context, ...data });
  }

  child(additionalContext = {}) {
    return new ContextLogger(this.parent, { ...this.context, ...additionalContext });
  }
}

// Performance logger for timing operations
export class PerformanceLogger {
  constructor(logger, operation) {
    this.logger = logger;
    this.operation = operation;
    this.startTime = performance.now();
    this.checkpoints = [];
  }

  checkpoint(name) {
    const now = performance.now();
    this.checkpoints.push({
      name,
      time: now,
      elapsed: now - this.startTime
    });
  }

  complete(success = true, data = {}) {
    const endTime = performance.now();
    const totalTime = endTime - this.startTime;

    const perfData = {
      operation: this.operation,
      success,
      totalTime: `${totalTime.toFixed(2)}ms`,
      checkpoints: this.checkpoints,
      ...data
    };

    if (success) {
      this.logger.info(`Operation completed: ${this.operation}`, perfData);
    } else {
      this.logger.warn(`Operation failed: ${this.operation}`, perfData);
    }

    return {
      totalTime,
      checkpoints: this.checkpoints
    };
  }
}

// Global logger instances
export const mainLogger = new Logger({
  level: process.env.LOG_LEVEL || 'INFO',
  component: 'MAIN'
});

export const rpcLogger = new Logger({
  level: process.env.LOG_LEVEL || 'INFO',
  component: 'RPC'
});

export const dbLogger = new Logger({
  level: process.env.LOG_LEVEL || 'INFO',
  component: 'DATABASE'
});

export const apiLogger = new Logger({
  level: process.env.LOG_LEVEL || 'INFO',
  component: 'API'
});

export const eventLogger = new Logger({
  level: process.env.LOG_LEVEL || 'INFO',
  component: 'EVENTS'
});

// Health metrics tracking
export class HealthMetrics {
  constructor() {
    this.metrics = {
      uptime: process.uptime(),
      errors: {
        total: 0,
        byType: {},
        recent: [] // Last 100 errors
      },
      performance: {
        avgBlockProcessingTime: 0,
        totalBlocksProcessed: 0,
        avgApiResponseTime: 0,
        totalApiRequests: 0
      },
      database: {
        totalTransactions: 0,
        totalVestingPositions: 0,
        lastSyncBlock: 0
      },
      rpc: {
        totalRequests: 0,
        failedRequests: 0,
        avgResponseTime: 0
      }
    };
  }

  recordError(error, context = {}) {
    this.metrics.errors.total++;
    
    const errorType = error.code || error.name || 'UNKNOWN';
    this.metrics.errors.byType[errorType] = (this.metrics.errors.byType[errorType] || 0) + 1;
    
    // Keep only last 100 errors
    this.metrics.errors.recent.push({
      timestamp: new Date().toISOString(),
      type: errorType,
      message: error.message,
      context
    });
    
    if (this.metrics.errors.recent.length > 100) {
      this.metrics.errors.recent = this.metrics.errors.recent.slice(-100);
    }
  }

  recordBlockProcessing(processingTime, blockNumber) {
    const { performance } = this.metrics;
    performance.totalBlocksProcessed++;
    performance.avgBlockProcessingTime = 
      (performance.avgBlockProcessingTime * (performance.totalBlocksProcessed - 1) + processingTime) / 
      performance.totalBlocksProcessed;
    
    this.metrics.database.lastSyncBlock = blockNumber;
  }

  recordApiRequest(responseTime) {
    const { performance } = this.metrics;
    performance.totalApiRequests++;
    performance.avgApiResponseTime = 
      (performance.avgApiResponseTime * (performance.totalApiRequests - 1) + responseTime) / 
      performance.totalApiRequests;
  }

  recordRpcRequest(responseTime, success = true) {
    this.metrics.rpc.totalRequests++;
    
    if (!success) {
      this.metrics.rpc.failedRequests++;
    }
    
    this.metrics.rpc.avgResponseTime = 
      (this.metrics.rpc.avgResponseTime * (this.metrics.rpc.totalRequests - 1) + responseTime) / 
      this.metrics.rpc.totalRequests;
  }

  updateDatabaseStats(transactions, vestingPositions) {
    this.metrics.database.totalTransactions = transactions;
    this.metrics.database.totalVestingPositions = vestingPositions;
  }

  getHealthStatus() {
    const now = Date.now();
    const recentErrors = this.metrics.errors.recent.filter(
      error => now - new Date(error.timestamp).getTime() < 300000 // Last 5 minutes
    );

    const rpcFailureRate = this.metrics.rpc.totalRequests > 0 
      ? (this.metrics.rpc.failedRequests / this.metrics.rpc.totalRequests) * 100 
      : 0;

    return {
      status: this.determineOverallHealth(recentErrors.length, rpcFailureRate),
      uptime: process.uptime(),
      metrics: this.metrics,
      recentErrors: recentErrors.length,
      rpcFailureRate: rpcFailureRate.toFixed(2)
    };
  }

  determineOverallHealth(recentErrorCount, rpcFailureRate) {
    if (recentErrorCount > 20 || rpcFailureRate > 50) {
      return 'unhealthy';
    } else if (recentErrorCount > 10 || rpcFailureRate > 25) {
      return 'degraded';
    } else {
      return 'healthy';
    }
  }
}

export const healthMetrics = new HealthMetrics();

export { Logger, LogLevel };