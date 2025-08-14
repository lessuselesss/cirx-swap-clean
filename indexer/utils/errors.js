// Custom Error Classes for CIRX Indexer
export class IndexerError extends Error {
  constructor(message, code = 'INDEXER_ERROR', details = {}) {
    super(message);
    this.name = 'IndexerError';
    this.code = code;
    this.details = details;
    this.timestamp = new Date().toISOString();
  }
}

export class RpcError extends IndexerError {
  constructor(message, details = {}) {
    super(message, 'RPC_ERROR', details);
    this.name = 'RpcError';
  }
}

export class DatabaseError extends IndexerError {
  constructor(message, details = {}) {
    super(message, 'DATABASE_ERROR', details);
    this.name = 'DatabaseError';
  }
}

export class EventProcessingError extends IndexerError {
  constructor(message, details = {}) {
    super(message, 'EVENT_PROCESSING_ERROR', details);
    this.name = 'EventProcessingError';
  }
}

export class ValidationError extends IndexerError {
  constructor(message, details = {}) {
    super(message, 'VALIDATION_ERROR', details);
    this.name = 'ValidationError';
  }
}

export class ConfigurationError extends IndexerError {
  constructor(message, details = {}) {
    super(message, 'CONFIGURATION_ERROR', details);
    this.name = 'ConfigurationError';
  }
}

// Error severity levels
export const ErrorSeverity = {
  LOW: 'low',
  MEDIUM: 'medium',
  HIGH: 'high',
  CRITICAL: 'critical'
};

// Retry configuration for different error types
export const RetryConfig = {
  RPC_ERROR: {
    maxRetries: 5,
    baseDelay: 1000, // 1 second
    backoffMultiplier: 2,
    maxDelay: 30000, // 30 seconds
    jitter: true
  },
  DATABASE_ERROR: {
    maxRetries: 3,
    baseDelay: 500,
    backoffMultiplier: 2,
    maxDelay: 5000,
    jitter: false
  },
  EVENT_PROCESSING_ERROR: {
    maxRetries: 2,
    baseDelay: 2000,
    backoffMultiplier: 1.5,
    maxDelay: 10000,
    jitter: true
  },
  DEFAULT: {
    maxRetries: 3,
    baseDelay: 1000,
    backoffMultiplier: 2,
    maxDelay: 10000,
    jitter: true
  }
};

// Exponential backoff with jitter
export function calculateDelay(attempt, config) {
  const { baseDelay, backoffMultiplier, maxDelay, jitter } = config;
  
  let delay = baseDelay * Math.pow(backoffMultiplier, attempt);
  delay = Math.min(delay, maxDelay);
  
  if (jitter) {
    // Add random jitter (±25%)
    const jitterAmount = delay * 0.25;
    delay += (Math.random() - 0.5) * 2 * jitterAmount;
  }
  
  return Math.max(delay, 100); // Minimum 100ms delay
}

// Retry wrapper function
export async function withRetry(operation, errorType = 'DEFAULT', context = {}) {
  const config = RetryConfig[errorType] || RetryConfig.DEFAULT;
  let lastError;
  
  for (let attempt = 0; attempt <= config.maxRetries; attempt++) {
    try {
      return await operation();
    } catch (error) {
      lastError = error;
      
      // Don't retry certain types of errors
      if (isNonRetryableError(error)) {
        throw error;
      }
      
      // If this was the last attempt, throw the error
      if (attempt === config.maxRetries) {
        throw new IndexerError(
          `Operation failed after ${config.maxRetries + 1} attempts: ${error.message}`,
          'RETRY_EXHAUSTED',
          {
            originalError: error,
            attempts: attempt + 1,
            context
          }
        );
      }
      
      // Calculate delay and wait
      const delay = calculateDelay(attempt, config);
      console.warn(
        `Attempt ${attempt + 1} failed, retrying in ${delay}ms: ${error.message}`,
        { context, error: error.code || error.name }
      );
      
      await new Promise(resolve => setTimeout(resolve, delay));
    }
  }
  
  throw lastError;
}

// Check if error should not be retried
function isNonRetryableError(error) {
  // Don't retry validation errors, configuration errors, or certain RPC errors
  const nonRetryableCodes = [
    'VALIDATION_ERROR',
    'CONFIGURATION_ERROR',
    'INVALID_ADDRESS',
    'CONTRACT_NOT_FOUND',
    'INSUFFICIENT_FUNDS', // For gas-related operations
    'UNAUTHORIZED',
    'FORBIDDEN'
  ];
  
  if (nonRetryableCodes.includes(error.code)) {
    return true;
  }
  
  // Don't retry 4xx HTTP errors (client errors)
  if (error.status && error.status >= 400 && error.status < 500) {
    return true;
  }
  
  return false;
}

// Error formatter for logging
export function formatError(error, context = {}) {
  const baseInfo = {
    name: error.name,
    message: error.message,
    code: error.code,
    timestamp: error.timestamp || new Date().toISOString(),
    context
  };
  
  if (error instanceof IndexerError) {
    return {
      ...baseInfo,
      details: error.details,
      severity: determineSeverity(error)
    };
  }
  
  return {
    ...baseInfo,
    stack: error.stack,
    severity: ErrorSeverity.HIGH
  };
}

// Determine error severity
function determineSeverity(error) {
  switch (error.code) {
    case 'CONFIGURATION_ERROR':
    case 'DATABASE_CONNECTION_FAILED':
      return ErrorSeverity.CRITICAL;
    
    case 'RPC_ERROR':
    case 'EVENT_PROCESSING_ERROR':
      return ErrorSeverity.HIGH;
    
    case 'VALIDATION_ERROR':
    case 'RETRY_EXHAUSTED':
      return ErrorSeverity.MEDIUM;
    
    default:
      return ErrorSeverity.LOW;
  }
}

// Circuit breaker pattern for repeated failures
export class CircuitBreaker {
  constructor(threshold = 5, timeout = 60000, monitorWindow = 300000) {
    this.threshold = threshold; // Number of failures before opening circuit
    this.timeout = timeout; // Time to wait before trying again (60s)
    this.monitorWindow = monitorWindow; // Window to count failures (5 minutes)
    
    this.state = 'CLOSED'; // CLOSED, OPEN, HALF_OPEN
    this.failures = [];
    this.lastFailureTime = null;
    this.nextAttemptTime = null;
  }
  
  async execute(operation, context = {}) {
    if (this.state === 'OPEN') {
      if (Date.now() < this.nextAttemptTime) {
        throw new IndexerError(
          'Circuit breaker is OPEN - operation blocked',
          'CIRCUIT_BREAKER_OPEN',
          { 
            nextAttemptTime: this.nextAttemptTime,
            context 
          }
        );
      } else {
        this.state = 'HALF_OPEN';
      }
    }
    
    try {
      const result = await operation();
      
      if (this.state === 'HALF_OPEN') {
        this.reset();
      }
      
      return result;
    } catch (error) {
      this.recordFailure();
      
      if (this.state === 'HALF_OPEN') {
        this.open();
      } else if (this.shouldOpen()) {
        this.open();
      }
      
      throw error;
    }
  }
  
  recordFailure() {
    const now = Date.now();
    this.failures.push(now);
    this.lastFailureTime = now;
    
    // Remove old failures outside the monitor window
    this.failures = this.failures.filter(
      time => now - time < this.monitorWindow
    );
  }
  
  shouldOpen() {
    return this.failures.length >= this.threshold;
  }
  
  open() {
    this.state = 'OPEN';
    this.nextAttemptTime = Date.now() + this.timeout;
    console.warn(`Circuit breaker opened - next attempt at ${new Date(this.nextAttemptTime).toISOString()}`);
  }
  
  reset() {
    this.state = 'CLOSED';
    this.failures = [];
    this.lastFailureTime = null;
    this.nextAttemptTime = null;
    console.info('Circuit breaker reset - normal operation resumed');
  }
  
  getStatus() {
    return {
      state: this.state,
      failures: this.failures.length,
      lastFailureTime: this.lastFailureTime,
      nextAttemptTime: this.nextAttemptTime
    };
  }
}