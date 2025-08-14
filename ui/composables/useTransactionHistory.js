// Transaction History API Integration
export const useTransactionHistory = () => {
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

  // Enhanced fetch with error handling and user feedback
  const fetchTransactionHistory = async (userAddress, options = {}) => {
    if (!userAddress) {
      transactions.value = [];
      error.value = null;
      return;
    }

    isLoading.value = true;
    error.value = null;

    try {
      const { limit = 50, offset = 0, type } = options;
      const queryParams = new URLSearchParams();
      
      if (limit) queryParams.append('limit', limit);
      if (offset) queryParams.append('offset', offset);
      if (type) queryParams.append('type', type);

      const endpoint = `/transactions/${userAddress}?${queryParams.toString()}`;
      
      const data = await withRetry(async () => {
        return await apiCall(endpoint);
      });
      
      // Handle successful response
      if (data.success && data.data) {
        transactions.value = data.data.transactions || [];
        return data.data;
      } else {
        // Handle API response without success flag (backwards compatibility)
        transactions.value = data.transactions || [];
        return data;
      }
    } catch (err) {
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
    } finally {
      isLoading.value = false;
    }
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

  // Fetch vesting positions for a user
  const fetchVestingPositions = async (userAddress) => {
    if (!userAddress) {
      vestingPositions.value = [];
      return;
    }

    isLoading.value = true;
    error.value = null;

    try {
      const data = await apiCall(`/vesting/${userAddress}`);
      vestingPositions.value = data.positions || [];
      return data;
    } catch (err) {
      error.value = err.message;
      vestingPositions.value = [];
      throw err;
    } finally {
      isLoading.value = false;
    }
  };

  // Fetch user statistics
  const fetchUserStats = async (userAddress) => {
    if (!userAddress) {
      userStats.value = null;
      return;
    }

    try {
      const data = await apiCall(`/stats/${userAddress}`);
      userStats.value = data.summary;
      return data;
    } catch (err) {
      error.value = err.message;
      userStats.value = null;
      throw err;
    }
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
      etherscanUrl: tx.etherscan_url || `https://etherscan.io/tx/${tx.tx_hash}`,
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
    fetchTransactionHistory,
    fetchVestingPositions,
    fetchUserStats,
    fetchUserData,
    checkIndexerHealth,
    checkServiceStatus,
    formatTransaction,
    formatVestingPosition,
    formatUserStats
  };
};