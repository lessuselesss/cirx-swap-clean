/**
 * Transaction Status Tracking Composable
 * 
 * Provides real-time transaction status updates with toast notifications
 */
import { ref, reactive, computed, onUnmounted } from 'vue'
import { useBackendApi } from './useBackendApi'

export function useTransactionStatus() {
  const { apiCall } = useBackendApi()
  
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
        const response = await apiCall(`/api/v1/transactions/${transactionId}/status`, 'GET')
        
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
        toast.add({ ...notification, color: 'green' })
        break
      case 'error':
        toast.add({ ...notification, color: 'red' })
        break
      case 'warning':
        toast.add({ ...notification, color: 'yellow' })
        break
      default:
        toast.add({ ...notification, color: 'blue' })
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