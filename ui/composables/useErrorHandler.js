import { ref, computed } from 'vue'

/**
 * Comprehensive error handling composable
 * Provides consistent error processing, user-friendly messages, and recovery actions
 */
export function useErrorHandler() {
  
  // Error state
  const currentError = ref(null)
  const errorHistory = ref([])
  const isHandling = ref(false)

  // Error categorization
  const ERROR_CATEGORIES = {
    WALLET_CONNECTION: 'wallet_connection',
    WALLET_TRANSACTION: 'wallet_transaction',
    CONTRACT_INTERACTION: 'contract_interaction',
    NETWORK_ERROR: 'network_error',
    VALIDATION_ERROR: 'validation_error',
    PERMISSION_ERROR: 'permission_error',
    RATE_LIMIT: 'rate_limit',
    UNKNOWN: 'unknown'
  }

  // Error severity levels
  const ERROR_SEVERITY = {
    LOW: 'low',      // Warning, non-blocking
    MEDIUM: 'medium', // Error but recoverable
    HIGH: 'high',    // Critical error, blocks functionality
    CRITICAL: 'critical' // System failure
  }

  /**
   * Categorize error based on error message and type
   */
  const categorizeError = (error) => {
    const message = error.message?.toLowerCase() || ''
    const code = error.code || error.errorCode || ''

    // Wallet connection errors
    if (message.includes('wallet') && (message.includes('connect') || message.includes('install'))) {
      return ERROR_CATEGORIES.WALLET_CONNECTION
    }

    // Transaction errors
    if (message.includes('transaction') || message.includes('gas') || message.includes('insufficient')) {
      return ERROR_CATEGORIES.WALLET_TRANSACTION
    }

    // Contract interaction errors
    if (message.includes('contract') || message.includes('revert') || message.includes('execution')) {
      return ERROR_CATEGORIES.CONTRACT_INTERACTION
    }

    // Network errors
    if (message.includes('network') || message.includes('rpc') || message.includes('timeout')) {
      return ERROR_CATEGORIES.NETWORK_ERROR
    }

    // Validation errors
    if (message.includes('invalid') || message.includes('validation') || message.includes('format')) {
      return ERROR_CATEGORIES.VALIDATION_ERROR
    }

    // Permission errors
    if (message.includes('permission') || message.includes('unauthorized') || message.includes('access')) {
      return ERROR_CATEGORIES.PERMISSION_ERROR
    }

    // Rate limiting
    if (message.includes('rate') || message.includes('limit') || code === '429') {
      return ERROR_CATEGORIES.RATE_LIMIT
    }

    return ERROR_CATEGORIES.UNKNOWN
  }

  /**
   * Determine error severity
   */
  const getErrorSeverity = (error, category) => {
    const message = error.message?.toLowerCase() || ''

    // Critical errors that break the app
    if (message.includes('system') || message.includes('fatal') || message.includes('crash')) {
      return ERROR_SEVERITY.CRITICAL
    }

    // High severity by category
    if ([ERROR_CATEGORIES.CONTRACT_INTERACTION, ERROR_CATEGORIES.PERMISSION_ERROR].includes(category)) {
      return ERROR_SEVERITY.HIGH
    }

    // Medium severity
    if ([ERROR_CATEGORIES.WALLET_TRANSACTION, ERROR_CATEGORIES.NETWORK_ERROR].includes(category)) {
      return ERROR_SEVERITY.MEDIUM
    }

    // Low severity
    if ([ERROR_CATEGORIES.VALIDATION_ERROR, ERROR_CATEGORIES.RATE_LIMIT].includes(category)) {
      return ERROR_SEVERITY.LOW
    }

    return ERROR_SEVERITY.MEDIUM
  }

  /**
   * Get user-friendly error message
   */
  const getUserFriendlyMessage = (error, category) => {
    const messages = {
      [ERROR_CATEGORIES.WALLET_CONNECTION]: {
        'wallet not found': 'Please install a supported wallet (MetaMask, Phantom, etc.)',
        'wallet locked': 'Please unlock your wallet and try again',
        'connection rejected': 'Wallet connection was rejected. Please try again.',
        'connection timeout': 'Wallet connection timed out. Please try again.',
        'default': 'Unable to connect to wallet. Please check your wallet and try again.'
      },
      [ERROR_CATEGORIES.WALLET_TRANSACTION]: {
        'insufficient': 'Insufficient balance to complete transaction',
        'gas': 'Transaction failed due to gas issues. Try increasing gas limit.',
        'rejected': 'Transaction was rejected. Please try again.',
        'timeout': 'Transaction timed out. Please check your wallet.',
        'default': 'Transaction failed. Please try again.'
      },
      [ERROR_CATEGORIES.CONTRACT_INTERACTION]: {
        'revert': 'Smart contract rejected the transaction. Please check parameters.',
        'not deployed': 'Contract not deployed. Please contact support.',
        'execution': 'Contract execution failed. Please try again.',
        'default': 'Smart contract interaction failed. Please try again.'
      },
      [ERROR_CATEGORIES.NETWORK_ERROR]: {
        'network': 'Network connection issue. Please check your internet.',
        'rpc': 'Blockchain network is temporarily unavailable. Please try again.',
        'timeout': 'Request timed out. Please try again.',
        'default': 'Network error. Please check your connection and try again.'
      },
      [ERROR_CATEGORIES.VALIDATION_ERROR]: {
        'invalid address': 'Please enter a valid wallet address',
        'invalid amount': 'Please enter a valid amount',
        'minimum': 'Amount is below minimum requirement',
        'maximum': 'Amount exceeds maximum limit',
        'default': 'Please check your input and try again.'
      },
      [ERROR_CATEGORIES.PERMISSION_ERROR]: {
        'unauthorized': 'You do not have permission to perform this action',
        'access': 'Access denied. Please check your permissions.',
        'default': 'Permission denied. Please contact support.'
      },
      [ERROR_CATEGORIES.RATE_LIMIT]: {
        'rate': 'Too many requests. Please wait a moment before trying again.',
        'limit': 'Request limit exceeded. Please wait before retrying.',
        'default': 'Please wait a moment before trying again.'
      },
      [ERROR_CATEGORIES.UNKNOWN]: {
        'default': 'An unexpected error occurred. Please try again.'
      }
    }

    const categoryMessages = messages[category] || messages[ERROR_CATEGORIES.UNKNOWN]
    const errorMessage = error.message?.toLowerCase() || ''

    // Find matching message
    for (const [key, message] of Object.entries(categoryMessages)) {
      if (key !== 'default' && errorMessage.includes(key)) {
        return message
      }
    }

    return categoryMessages.default
  }

  /**
   * Get recovery actions for error
   */
  const getRecoveryActions = (error, category, context = {}) => {
    const actions = []

    switch (category) {
      case ERROR_CATEGORIES.WALLET_CONNECTION:
        actions.push({
          label: 'Try Again',
          handler: context.retryConnection || (() => {}),
          primary: true
        })
        if (error.message?.includes('install')) {
          actions.push({
            label: 'Install Wallet',
            handler: () => window.open('https://metamask.io/download/', '_blank'),
            primary: false
          })
        }
        break

      case ERROR_CATEGORIES.WALLET_TRANSACTION:
        actions.push({
          label: 'Retry Transaction',
          handler: context.retryTransaction || (() => {}),
          primary: true
        })
        if (error.message?.includes('gas')) {
          actions.push({
            label: 'Increase Gas',
            handler: context.increaseGas || (() => {}),
            primary: false
          })
        }
        break

      case ERROR_CATEGORIES.CONTRACT_INTERACTION:
        actions.push({
          label: 'Try Again',
          handler: context.retryContract || (() => {}),
          primary: true
        })
        break

      case ERROR_CATEGORIES.NETWORK_ERROR:
        actions.push({
          label: 'Retry',
          handler: context.retryRequest || (() => {}),
          primary: true
        })
        actions.push({
          label: 'Check Status',
          handler: () => window.open('https://status.ethereum.org/', '_blank'),
          primary: false
        })
        break

      case ERROR_CATEGORIES.RATE_LIMIT:
        actions.push({
          label: 'Wait & Retry',
          handler: () => {
            setTimeout(context.retryRequest || (() => {}), 5000)
          },
          primary: true
        })
        break

      default:
        actions.push({
          label: 'Try Again',
          handler: context.retry || (() => {}),
          primary: true
        })
    }

    // Always add support action for critical errors
    if (getErrorSeverity(error, category) === ERROR_SEVERITY.CRITICAL) {
      actions.push({
        label: 'Contact Support',
        handler: () => window.open('mailto:support@circular.io', '_blank'),
        primary: false
      })
    }

    return actions
  }

  /**
   * Process and handle error
   */
  const handleError = (error, context = {}) => {
    if (isHandling.value) return null

    try {
      isHandling.value = true

      // Normalize error object
      const normalizedError = normalizeError(error)
      
      // Categorize error
      const category = categorizeError(normalizedError)
      const severity = getErrorSeverity(normalizedError, category)
      
      // Create processed error object
      const processedError = {
        id: Date.now() + Math.random(),
        original: normalizedError,
        category,
        severity,
        message: normalizedError.message,
        userMessage: getUserFriendlyMessage(normalizedError, category),
        actions: getRecoveryActions(normalizedError, category, context),
        timestamp: new Date(),
        context: context.description || 'Unknown operation'
      }

      // Set current error
      currentError.value = processedError

      // Add to history
      errorHistory.value.unshift(processedError)
      
      // Keep only last 10 errors
      if (errorHistory.value.length > 10) {
        errorHistory.value = errorHistory.value.slice(0, 10)
      }

      // Log error for debugging
      console.error('Error handled:', {
        category,
        severity,
        message: normalizedError.message,
        context: context.description,
        originalError: normalizedError
      })

      return processedError

    } catch (handlingError) {
      console.error('Error in error handler:', handlingError)
      return {
        id: Date.now(),
        category: ERROR_CATEGORIES.UNKNOWN,
        severity: ERROR_SEVERITY.HIGH,
        message: 'Error handling failed',
        userMessage: 'An unexpected error occurred. Please refresh the page.',
        actions: [{
          label: 'Refresh Page',
          handler: () => window.location.reload(),
          primary: true
        }],
        timestamp: new Date()
      }
    } finally {
      isHandling.value = false
    }
  }

  /**
   * Normalize error to consistent format
   */
  const normalizeError = (error) => {
    if (typeof error === 'string') {
      return new Error(error)
    }

    if (error instanceof Error) {
      return error
    }

    if (error && typeof error === 'object') {
      return {
        message: error.message || error.msg || error.error || 'Unknown error',
        code: error.code || error.errorCode,
        data: error.data,
        ...error
      }
    }

    return new Error('Unknown error occurred')
  }

  /**
   * Clear current error
   */
  const clearError = () => {
    currentError.value = null
  }

  /**
   * Clear all errors
   */
  const clearAllErrors = () => {
    currentError.value = null
    errorHistory.value = []
  }

  /**
   * Check if error should be displayed as toast
   */
  const shouldShowAsToast = (error) => {
    return error && [ERROR_SEVERITY.LOW, ERROR_SEVERITY.MEDIUM].includes(error.severity)
  }

  /**
   * Check if error should be displayed inline
   */
  const shouldShowInline = (error) => {
    return error && [ERROR_SEVERITY.HIGH, ERROR_SEVERITY.CRITICAL].includes(error.severity)
  }

  // Computed properties
  const hasError = computed(() => currentError.value !== null)
  const errorCount = computed(() => errorHistory.value.length)
  const recentErrors = computed(() => errorHistory.value.slice(0, 5))

  return {
    // State
    currentError,
    errorHistory,
    isHandling,
    hasError,
    errorCount,
    recentErrors,

    // Methods
    handleError,
    clearError,
    clearAllErrors,
    
    // Utilities
    categorizeError,
    getErrorSeverity,
    getUserFriendlyMessage,
    getRecoveryActions,
    shouldShowAsToast,
    shouldShowInline,

    // Constants
    ERROR_CATEGORIES,
    ERROR_SEVERITY
  }
}