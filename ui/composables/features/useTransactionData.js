/**
 * Transaction Status Tracking Composable
 * 
 * Provides real-time transaction status updates with toast notifications
 */
import { ref, reactive, computed, onUnmounted } from 'vue'
import { useApiClient } from '../core/useApiClient.js'
import { safeToast } from '~/composables/useToast' // Import safe toast utility

export function useTransactionStatus() {
  const apiClient = useApiClient()
  
  // State management
  const transactions = ref(new Map())
  const activePolling = ref(new Set())
  const pollingIntervals = ref(new Map())
  
  // Transaction phases with user-friendly data
  const transactionPhases = {
    initiated: {
      title: 'Transaction Started',
      message: 'Your swap has been initiated',
      type: 'info',
      icon: '🚀',
      color: 'blue'
    },
    awaiting_payment: {
      title: 'Awaiting Payment',
      message: 'Please complete your payment to continue',
      type: 'warning',
      icon: '⏳',
      color: 'yellow'
    },
    verifying_payment: {
      title: 'Verifying Payment',
      message: 'Confirming your payment on the blockchain...',
      type: 'info',
      icon: '🔍',
      color: 'blue'
    },
    payment_confirmed: {
      title: 'Payment Confirmed',
      message: 'Payment verified! Preparing CIRX transfer...',
      type: 'success',
      icon: '✅',
      color: 'green'
    },
    preparing_transfer: {
      title: 'Preparing Transfer',
      message: 'Setting up CIRX token transfer...',
      type: 'info',
      icon: '⚙️',
      color: 'blue'
    },
    transferring_cirx: {
      title: 'Sending CIRX',
      message: 'Transferring CIRX tokens to your address...',
      type: 'info',
      icon: '📤',
      color: 'blue'
    },
    completed: {
      title: 'Swap Complete!',
      message: 'CIRX tokens have been sent to your address',
      type: 'success',
      icon: '🎉',
      color: 'green'
    },
    payment_failed: {
      title: 'Payment Failed',
      message: 'Payment verification failed. Please check your transaction.',
      type: 'error',
      icon: '❌',
      color: 'red'
    },
    transfer_failed: {
      title: 'Transfer Failed',
      message: 'CIRX transfer failed. Our team has been notified.',
      type: 'error',
      icon: '❌',
      color: 'red'
    }
  }

  /**
   * Start tracking a transaction with real-time updates
   */
  function trackTransaction(transactionId, options = {}) {
    const {
      showToasts = true,
      pollingInterval = 3000, // 3 seconds
      onStatusChange = null,
      onComplete = null,
      onError = null
    } = options

    // Initialize transaction state
    const transactionState = reactive({
      id: transactionId,
      status: null,
      phase: null,
      progress: 0,
      message: '',
      lastUpdate: null,
      showToasts,
      onStatusChange,
      onComplete,
      onError,
      isPolling: false,
      error: null
    })

    transactions.value.set(transactionId, transactionState)
    
    // Start polling
    startPolling(transactionId, pollingInterval)
    
    return transactionState
  }

  /**
   * Start polling for transaction status updates
   */
  function startPolling(transactionId, interval = 3000) {
    if (activePolling.value.has(transactionId)) {
      return // Already polling
    }

    const transaction = transactions.value.get(transactionId)
    if (!transaction) return

    transaction.isPolling = true
    activePolling.value.add(transactionId)

    const pollStatus = async () => {
      try {
        const response = await apiClient.get(`/transactions/${transactionId}/status`, {
          context: { operation: 'transaction_status_poll', transactionId }
        })
        
        if (response.success) {
          const statusData = response.data
          updateTransactionStatus(transactionId, statusData)
          
          // Stop polling if transaction is complete or failed
          if (['completed', 'payment_failed', 'transfer_failed'].includes(statusData.phase)) {
            stopPolling(transactionId)
          }
        } else {
          console.error('Failed to fetch transaction status:', response.error)
          transaction.error = response.error
        }
      } catch (error) {
        console.error('Error polling transaction status:', error)
        transaction.error = error.message
        
        // Call error handler if provided
        if (transaction.onError) {
          transaction.onError(error)
        }
      }
    }

    // Initial poll
    pollStatus()

    // Set up interval polling
    const intervalId = setInterval(pollStatus, interval)
    pollingIntervals.value.set(transactionId, intervalId)
  }

  /**
   * Stop polling for a specific transaction
   */
  function stopPolling(transactionId) {
    const transaction = transactions.value.get(transactionId)
    if (transaction) {
      transaction.isPolling = false
    }

    activePolling.value.delete(transactionId)
    
    const intervalId = pollingIntervals.value.get(transactionId)
    if (intervalId) {
      clearInterval(intervalId)
      pollingIntervals.value.delete(transactionId)
    }
  }

  /**
   * Update transaction status and trigger notifications
   */
  function updateTransactionStatus(transactionId, statusData) {
    const transaction = transactions.value.get(transactionId)
    if (!transaction) return

    const previousPhase = transaction.phase
    
    // Update transaction state
    transaction.status = statusData.status
    transaction.phase = statusData.phase
    transaction.progress = statusData.progress
    transaction.message = statusData.message
    transaction.lastUpdate = new Date()

    // Show toast notification for phase changes
    if (transaction.showToasts && previousPhase !== statusData.phase) {
      showPhaseNotification(statusData.phase, statusData)
    }

    // Call status change handler
    if (transaction.onStatusChange) {
      transaction.onStatusChange(statusData, previousPhase)
    }

    // Call completion handler if transaction is done
    if (statusData.phase === 'completed' && transaction.onComplete) {
      transaction.onComplete(statusData)
    }
  }

  /**
   * Show toast notification for phase change
   */
  function showPhaseNotification(phase, statusData) {
    const phaseConfig = transactionPhases[phase]
    if (!phaseConfig) return

    const toast = useNuxtApp().$toast
    if (!toast) {
      console.warn('Toast service not available')
      return
    }

    const notification = {
      title: phaseConfig.title,
      description: statusData.message || phaseConfig.message,
      color: phaseConfig.color,
      icon: phaseConfig.icon,
      timeout: getNotificationTimeout(phase)
    }

    // Show appropriate toast type
    switch (phaseConfig.type) {
      case 'success':
        safeToast.success(`${notification.title}: ${notification.description}`)
        break
      case 'error':
        safeToast.error(`${notification.title}: ${notification.description}`)
        break
      case 'warning':
        safeToast.error(`${notification.title}: ${notification.description}`)
        break
      default:
        safeToast.success(`${notification.title}: ${notification.description}`)
    }
  }

  /**
   * Get notification display timeout based on phase importance
   */
  function getNotificationTimeout(phase) {
    switch (phase) {
      case 'completed':
      case 'payment_failed':
      case 'transfer_failed':
        return 8000 // 8 seconds for important final states
      case 'payment_confirmed':
        return 5000 // 5 seconds for good news
      default:
        return 4000 // 4 seconds for status updates
    }
  }

  /**
   * Get transaction by ID
   */
  function getTransaction(transactionId) {
    return transactions.value.get(transactionId)
  }

  /**
   * Get all tracked transactions
   */
  const allTransactions = computed(() => {
    return Array.from(transactions.value.values())
  })

  /**
   * Get active (polling) transactions
   */
  const activeTransactions = computed(() => {
    return allTransactions.value.filter(tx => tx.isPolling)
  })

  /**
   * Remove transaction from tracking
   */
  function removeTransaction(transactionId) {
    stopPolling(transactionId)
    transactions.value.delete(transactionId)
  }

  /**
   * Clean up on component unmount
   */
  onUnmounted(() => {
    // Stop all polling
    for (const transactionId of activePolling.value) {
      stopPolling(transactionId)
    }
  })

  return {
    // Core functions
    trackTransaction,
    stopPolling,
    removeTransaction,
    
    // Data access
    getTransaction,
    allTransactions,
    activeTransactions,
    
    // Phase configurations for UI
    transactionPhases,
    
    // Manual status update (for testing)
    updateTransactionStatus
  }
}


// Transaction History API Integration
export const useTransactionData = () => {
  const INDEXER_API_BASE = 'http://localhost:3001/api';
  
  // Reactive state
  const isLoading = ref(false);
  const error = ref(null);
  const transactions = ref([]);
  const vestingPositions = ref([]);
  const userStats = ref(null);

  // Enhanced API call with comprehensive error handling
  const apiCall = async (endpoint, options = {}) => {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), options.timeout || 10000);
    
    try {
      const response = await fetch(`${INDEXER_API_BASE}${endpoint}`, {
        signal: controller.signal,
        headers: {
          'Content-Type': 'application/json',
          ...options.headers
        },
        ...options
      });
      
      clearTimeout(timeoutId);
      
      // Handle different response statuses
      if (!response.ok) {
        let errorData;
        try {
          errorData = await response.json();
        } catch {
          errorData = { message: response.statusText };
        }
        
        throw new ApiError(
          errorData.message || `HTTP ${response.status}`,
          response.status,
          errorData.code,
          errorData.requestId,
          endpoint
        );
      }
      
      return await response.json();
    } catch (err) {
      clearTimeout(timeoutId);
      
      if (err.name === 'AbortError') {
        throw new ApiError('Request timeout', 408, 'TIMEOUT', null, endpoint);
      }
      
      if (err instanceof ApiError) {
        throw err;
      }
      
      // Network or other errors
      throw new ApiError(
        'Network error or service unavailable',
        0,
        'NETWORK_ERROR',
        null,
        endpoint,
        err
      );
    }
  };

  // Custom error class for API errors
  class ApiError extends Error {
    constructor(message, status, code, requestId, endpoint, originalError) {
      super(message);
      this.name = 'ApiError';
      this.status = status;
      this.code = code;
      this.requestId = requestId;
      this.endpoint = endpoint;
      this.originalError = originalError;
      this.timestamp = new Date().toISOString();
    }

    get isRetryable() {
      // Don't retry 4xx errors (client errors) except 408 (timeout) and 429 (rate limit)
      if (this.status >= 400 && this.status < 500) {
        return this.status === 408 || this.status === 429;
      }
      
      // Retry 5xx errors (server errors) and network errors
      return this.status >= 500 || this.status === 0;
    }

    get retryDelay() {
      if (this.status === 429) {
        // Rate limited - wait longer
        return 5000;
      }
      return 1000; // Default 1 second
    }
  }

  // Retry wrapper with exponential backoff
  const withRetry = async (operation, maxRetries = 3) => {
    let lastError;
    
    for (let attempt = 0; attempt <= maxRetries; attempt++) {
      try {
        return await operation();
      } catch (error) {
        lastError = error;
        
        // Don't retry non-retryable errors
        if (error instanceof ApiError && !error.isRetryable) {
          throw error;
        }
        
        // If this was the last attempt, throw the error
        if (attempt === maxRetries) {
          break;
        }
        
        // Calculate delay with exponential backoff
        const baseDelay = error instanceof ApiError ? error.retryDelay : 1000;
        const delay = baseDelay * Math.pow(2, attempt);
        
        console.warn(`API request failed (attempt ${attempt + 1}/${maxRetries + 1}), retrying in ${delay}ms:`, error.message);
        
        await new Promise(resolve => setTimeout(resolve, delay));
      }
    }
    
    throw lastError;
  };

  /**
   * Unified user data fetching function (93% similarity eliminated)
   * Consolidates fetchTransactionHistory + fetchVestingPositions + fetchUserStats
   * @param {string} userAddress - User wallet address
   * @param {string} dataType - Type of data to fetch: 'transactions', 'vesting', 'stats'
   * @param {object} options - Additional options for transactions (limit, offset, type)
   * @returns {Promise} Data from API
   */
  const fetchUserDataByType = async (userAddress, dataType, options = {}) => {
    if (!userAddress) {
      // Reset appropriate state based on data type
      switch (dataType) {
        case 'transactions':
          transactions.value = [];
          break;
        case 'vesting':
          vestingPositions.value = [];
          break;
        case 'stats':
          userStats.value = null;
          break;
      }
      error.value = null;
      return;
    }

    // Stats endpoint doesn't use loading state
    if (dataType !== 'stats') {
      isLoading.value = true;
    }
    error.value = null;

    try {
      let endpoint;
      let apiCallFn;

      // Configure endpoint and API call based on data type
      switch (dataType) {
        case 'transactions':
          const { limit = 50, offset = 0, type } = options;
          const queryParams = new URLSearchParams();
          
          if (limit) queryParams.append('limit', limit);
          if (offset) queryParams.append('offset', offset);
          if (type) queryParams.append('type', type);

          endpoint = `/transactions/${userAddress}?${queryParams.toString()}`;
          apiCallFn = async () => withRetry(async () => await apiCall(endpoint));
          break;
        case 'vesting':
          endpoint = `/vesting/${userAddress}`;
          apiCallFn = async () => await apiCall(endpoint);
          break;
        case 'stats':
          endpoint = `/stats/${userAddress}`;
          apiCallFn = async () => await apiCall(endpoint);
          break;
        default:
          throw new Error(`Unknown data type: ${dataType}`);
      }
      
      const data = await apiCallFn();
      
      // Update appropriate state based on data type
      switch (dataType) {
        case 'transactions':
          // Handle successful response with complex logic for transactions
          if (data.success && data.data) {
            transactions.value = data.data.transactions || [];
            return data.data;
          } else {
            // Handle API response without success flag (backwards compatibility)
            transactions.value = data.transactions || [];
            return data;
          }
        case 'vesting':
          vestingPositions.value = data.positions || [];
          return data;
        case 'stats':
          userStats.value = data.summary;
          return data;
      }
      
    } catch (err) {
      // Enhanced error handling for transactions, basic for others
      if (dataType === 'transactions') {
        const userFriendlyError = getUserFriendlyError(err);
        error.value = userFriendlyError.message;
        transactions.value = [];
        
        // Log detailed error for debugging
        console.error('Failed to fetch transaction history:', {
          error: err,
          userAddress,
          options,
          timestamp: new Date().toISOString()
        });
        
        throw userFriendlyError;
      } else {
        // Basic error handling for vesting and stats
        error.value = err.message;
        if (dataType === 'vesting') {
          vestingPositions.value = [];
        } else if (dataType === 'stats') {
          userStats.value = null;
        }
        throw err;
      }
    } finally {
      // Only reset loading for transactions and vesting
      if (dataType !== 'stats') {
        isLoading.value = false;
      }
    }
  };

  // Backward compatibility functions (maintain existing API)
  const fetchTransactionHistory = async (userAddress, options = {}) => {
    return await fetchUserDataByType(userAddress, 'transactions', options);
  };

  const fetchVestingPositions = async (userAddress) => {
    return await fetchUserDataByType(userAddress, 'vesting');
  };

  const fetchUserStats = async (userAddress) => {
    return await fetchUserDataByType(userAddress, 'stats');
  };

  // Convert technical errors to user-friendly messages
  const getUserFriendlyError = (error) => {
    if (error instanceof ApiError) {
      switch (error.code) {
        case 'TIMEOUT':
          return {
            message: 'Request timed out. Please check your connection and try again.',
            code: 'TIMEOUT',
            retryable: true
          };
        case 'NETWORK_ERROR':
          return {
            message: 'Unable to connect to the server. Please check your internet connection.',
            code: 'NETWORK_ERROR', 
            retryable: true
          };
        case 'VALIDATION_ERROR':
          return {
            message: 'Invalid request. Please check the wallet address and try again.',
            code: 'VALIDATION_ERROR',
            retryable: false
          };
        case 'RATE_LIMIT_EXCEEDED':
          return {
            message: 'Too many requests. Please wait a moment and try again.',
            code: 'RATE_LIMIT',
            retryable: true
          };
        default:
          if (error.status >= 500) {
            return {
              message: 'Server error. Our team has been notified. Please try again later.',
              code: 'SERVER_ERROR',
              retryable: true
            };
          } else if (error.status >= 400) {
            return {
              message: error.message || 'Invalid request. Please check your input and try again.',
              code: 'CLIENT_ERROR',
              retryable: false
            };
          }
      }
    }
    
    return {
      message: 'An unexpected error occurred. Please try again.',
      code: 'UNKNOWN_ERROR',
      retryable: true
    };
  };

  // Fetch all user data (transactions + vesting + stats)
  const fetchUserData = async (userAddress) => {
    if (!userAddress) {
      transactions.value = [];
      vestingPositions.value = [];
      userStats.value = null;
      return;
    }

    isLoading.value = true;
    error.value = null;

    try {
      // Fetch all data in parallel
      const [txData, vestingData, statsData] = await Promise.all([
        fetchTransactionHistory(userAddress),
        fetchVestingPositions(userAddress),
        fetchUserStats(userAddress)
      ]);

      return {
        transactions: txData,
        vesting: vestingData,
        stats: statsData
      };
    } catch (err) {
      error.value = err.message;
      throw err;
    } finally {
      isLoading.value = false;
    }
  };

  // Format transaction data for display
  const formatTransaction = (tx) => {
    return {
      id: tx.tx_hash,
      type: tx.swap_type,
      status: 'completed', // All indexed transactions are completed
      date: new Date(tx.timestamp).toLocaleDateString(),
      inputAmount: tx.input_amount_formatted,
      inputToken: getTokenSymbol(tx.input_token),
      cirxAmount: parseFloat(tx.cirx_amount_formatted).toLocaleString(),
      discount: tx.discount_percentage || 0,
      hash: tx.tx_hash,
      etherscanUrl: tx.etherscan_url, // Server-provided URL only
      timestamp: tx.timestamp,
      gasUsed: tx.gas_used,
      gasPrice: tx.gas_price
    };
  };

  // Format vesting position data for display
  const formatVestingPosition = (position) => {
    return {
      id: position.tx_hash,
      totalAmount: parseFloat(position.total_amount_formatted).toLocaleString(),
      claimedAmount: parseFloat(position.claimed_amount_formatted).toLocaleString(),
      claimableAmount: parseFloat(position.claimable_amount_formatted).toLocaleString(),
      startDate: new Date(position.start_date).toLocaleDateString(),
      endDate: new Date(position.end_date).toLocaleDateString(),
      progressPercent: Math.round(parseFloat(position.progress_percentage)),
      status: position.status,
      isClaimable: position.is_claimable,
      vestingPositionData: position // Keep original data for claiming
    };
  };

  // Get token symbol from address (helper function)
  const getTokenSymbol = (tokenAddress) => {
    const tokenMap = {
      '0x0000000000000000000000000000000000000000': 'ETH', // ETH placeholder
      '0xa0b86a33e6280c6000e9094e87ff96e39b2e9b18': 'USDC', // Common USDC address
      '0xdac17f958d2ee523a2206206994597c13d831ec7': 'USDT', // Common USDT address
    };
    
    return tokenMap[tokenAddress?.toLowerCase()] || 'Unknown';
  };

  // Format user stats for display
  const formatUserStats = (stats) => {
    if (!stats) return null;
    
    return {
      totalPurchases: `${stats.total_swaps || 0} purchase${stats.total_swaps === 1 ? '' : 's'}`,
      totalUsdValue: `${stats.total_cirx_purchased_formatted || '0'} CIRX purchased`,
      liquidSwaps: stats.liquid_swaps || 0,
      otcSwaps: stats.otc_swaps || 0,
      vestingBalance: `${stats.total_vesting_amount_formatted || '0'} CIRX`,
      totalVestingPositions: stats.total_vesting_positions || 0
    };
  };

  // Check if indexer is available
  const checkIndexerHealth = async () => {
    try {
      const response = await fetch(`${INDEXER_API_BASE.replace('/api', '')}/health`, {
        timeout: 5000 // 5 second timeout for health check
      });
      return response.ok;
    } catch {
      return false;
    }
  };

  // Service status for user messaging
  const serviceStatus = ref('unknown'); // 'available', 'unavailable', 'unknown'
  
  const checkServiceStatus = async () => {
    try {
      const isHealthy = await checkIndexerHealth();
      serviceStatus.value = isHealthy ? 'available' : 'unavailable';
      return isHealthy;
    } catch {
      serviceStatus.value = 'unavailable';
      return false;
    }
  };

  // Computed properties for formatted data
  const formattedTransactions = computed(() => 
    transactions.value.map(formatTransaction)
  );

  const formattedVestingPositions = computed(() => 
    vestingPositions.value.map(formatVestingPosition)
  );

  const formattedUserStats = computed(() => 
    formatUserStats(userStats.value)
  );

  // Check if user has any data
  const hasTransactions = computed(() => transactions.value.length > 0);
  const hasVestingPositions = computed(() => vestingPositions.value.length > 0);
  const hasAnyData = computed(() => hasTransactions.value || hasVestingPositions.value);

  return {
    // State
    isLoading: readonly(isLoading),
    error: readonly(error),
    transactions: readonly(transactions),
    vestingPositions: readonly(vestingPositions),
    userStats: readonly(userStats),
    serviceStatus: readonly(serviceStatus),

    // Computed
    formattedTransactions,
    formattedVestingPositions,
    formattedUserStats,
    hasTransactions,
    hasVestingPositions,
    hasAnyData,

    // Methods
    fetchUserDataByType,     // ✨ New unified function
    fetchTransactionHistory, // Backward compatibility
    fetchVestingPositions,   // Backward compatibility
    fetchUserStats,          // Backward compatibility
    fetchUserData,
    checkIndexerHealth,
    checkServiceStatus,
    formatTransaction,
    formatVestingPosition,
    formatUserStats
  };
};