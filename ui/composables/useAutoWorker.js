/**
 * Auto-Worker Composable
 * 
 * Provides frontend-triggered background worker processing with intelligent throttling.
 * Only runs workers if they haven't been triggered recently, preventing spam while
 * ensuring continuous processing when the user is active.
 */

import { ref, onMounted, onUnmounted } from 'vue'
import { useApiClient } from './core/useApiClient'
import { safeToast } from './useToast'

export function useAutoWorker() {
  const apiClient = useApiClient()
  
  // Configuration
  const THROTTLE_DURATION = 30 * 1000 // 30 seconds (configurable)
  const ACTIVITY_RESET_DURATION = 5 * 60 * 1000 // 5 minutes of inactivity before stopping
  
  // State
  const isProcessing = ref(false)
  const lastTriggerTime = ref(0)
  const lastProcessTime = ref(0)
  const activityTimer = ref(null)
  const processingResults = ref(null)
  const isActive = ref(false)
  
  /**
   * Check if workers should run based on throttling rules
   */
  const shouldRunWorkers = () => {
    const now = Date.now()
    const timeSinceLastProcess = now - lastProcessTime.value
    return timeSinceLastProcess >= THROTTLE_DURATION
  }
  
  /**
   * Get throttle status information
   */
  const getThrottleStatus = () => {
    const now = Date.now()
    const timeSinceLastProcess = now - lastProcessTime.value
    const remainingThrottle = Math.max(0, THROTTLE_DURATION - timeSinceLastProcess)
    
    return {
      isThrottled: timeSinceLastProcess < THROTTLE_DURATION,
      remainingMs: remainingThrottle,
      remainingSeconds: Math.ceil(remainingThrottle / 1000),
      timeSinceLastProcess: Math.floor(timeSinceLastProcess / 1000),
      lastProcessTime: lastProcessTime.value > 0 ? new Date(lastProcessTime.value).toLocaleTimeString() : null
    }
  }
  
  /**
   * Trigger worker processing with throttling
   */
  const triggerWorkers = async (force = false) => {
    const now = Date.now()
    lastTriggerTime.value = now
    
    // Reset activity timer
    resetActivityTimer()
    
    // Check throttling unless forced
    if (!force && !shouldRunWorkers()) {
      const throttleStatus = getThrottleStatus()
      console.log(`⏳ Workers throttled, ${throttleStatus.remainingSeconds}s remaining`)
      return {
        success: true,
        action: 'throttled',
        throttleStatus
      }
    }
    
    // Execute workers
    try {
      isProcessing.value = true
      lastProcessTime.value = now
      
      console.log('🔄 Triggering background workers...')
      
      const response = await apiClient.post('/workers/process', {})
      
      if (response.success) {
        processingResults.value = response.data
        
        // Extract summary info
        const payment = response.data.payment_verification || {}
        const cirx = response.data.cirx_transfers || {}
        const totalProcessed = (payment.processed || 0) + (cirx.processed || 0)
        
        if (totalProcessed > 0) {
          console.log(`✅ Workers processed ${totalProcessed} transactions`)
          
          // Show subtle notification for successful processing
          safeToast.info(`Processed ${totalProcessed} pending transactions`, {
            title: 'Background Processing',
            autoTimeoutMs: 3000
          })
        } else {
          console.log('✅ Workers ran successfully (no transactions to process)')
        }
        
        return {
          success: true,
          action: 'executed',
          results: response.data,
          summary: {
            totalProcessed,
            paymentsVerified: (payment.verified || 0),
            cirxCompleted: (cirx.completed || 0)
          }
        }
      } else {
        throw new Error(response.message || 'Worker request failed')
      }
      
    } catch (error) {
      console.error('❌ Auto-worker failed:', error)
      
      // Show error notification only for real failures
      safeToast.error('Background processing failed', {
        title: 'Worker Error',
        autoTimeoutMs: 5000
      })
      
      return {
        success: false,
        action: 'error',
        error: error.message
      }
      
    } finally {
      isProcessing.value = false
    }
  }
  
  /**
   * Get current worker statistics
   */
  const getWorkerStats = async () => {
    try {
      const response = await apiClient.get('/workers/stats')
      return response.success ? response.data : null
    } catch (error) {
      console.warn('Failed to get worker stats:', error.message)
      return null
    }
  }
  
  /**
   * Check if there's pending work that needs processing
   */
  const checkPendingWork = async () => {
    const stats = await getWorkerStats()
    if (!stats) return false
    
    const pendingPayments = stats.payment_verification?.pending_verification || 0
    const readyTransfers = stats.cirx_transfers?.ready_for_transfer || 0
    const pendingTransfers = stats.cirx_transfers?.transfer_pending || 0
    
    return {
      hasPendingWork: pendingPayments > 0 || readyTransfers > 0 || pendingTransfers > 0,
      pendingPayments,
      readyTransfers, 
      pendingTransfers,
      stats
    }
  }
  
  /**
   * Smart trigger - only runs if there's actual work to do
   */
  const smartTrigger = async () => {
    // First check if there's pending work
    const workStatus = await checkPendingWork()
    
    if (!workStatus.hasPendingWork) {
      console.log('🎯 Smart trigger: No pending work, skipping')
      return {
        success: true,
        action: 'skipped',
        reason: 'no_pending_work',
        workStatus
      }
    }
    
    console.log(`🎯 Smart trigger: Found work (${workStatus.pendingPayments} payments, ${workStatus.readyTransfers} ready, ${workStatus.pendingTransfers} pending)`)
    return await triggerWorkers()
  }
  
  /**
   * Reset the activity timer
   */
  const resetActivityTimer = () => {
    if (activityTimer.value) {
      clearTimeout(activityTimer.value)
    }
    
    isActive.value = true
    
    // Set timer to mark as inactive after period of no activity
    activityTimer.value = setTimeout(() => {
      isActive.value = false
      console.log('💤 Auto-worker going inactive due to no user activity')
    }, ACTIVITY_RESET_DURATION)
  }
  
  /**
   * Register user activity to keep auto-worker active
   */
  const registerActivity = () => {
    lastTriggerTime.value = Date.now()
    resetActivityTimer()
  }
  
  /**
   * Start auto-worker system
   */
  const start = () => {
    console.log('🚀 Auto-worker system starting...')
    isActive.value = true
    resetActivityTimer()
    
    // Initial trigger after short delay
    setTimeout(() => {
      if (isActive.value) {
        smartTrigger()
      }
    }, 2000)
  }
  
  /**
   * Stop auto-worker system
   */
  const stop = () => {
    console.log('🛑 Auto-worker system stopping...')
    isActive.value = false
    
    if (activityTimer.value) {
      clearTimeout(activityTimer.value)
      activityTimer.value = null
    }
  }
  
  /**
   * Get current status for debugging
   */
  const getStatus = () => {
    return {
      isActive: isActive.value,
      isProcessing: isProcessing.value,
      throttleStatus: getThrottleStatus(),
      lastTriggerTime: lastTriggerTime.value > 0 ? new Date(lastTriggerTime.value).toLocaleTimeString() : null,
      lastProcessTime: lastProcessTime.value > 0 ? new Date(lastProcessTime.value).toLocaleTimeString() : null,
      processingResults: processingResults.value
    }
  }
  
  // Cleanup on unmount
  onUnmounted(() => {
    stop()
  })
  
  // Auto-start when component mounts
  onMounted(() => {
    start()
  })
  
  return {
    // State
    isProcessing,
    isActive,
    processingResults,
    
    // Methods
    triggerWorkers,
    smartTrigger,
    getWorkerStats,
    checkPendingWork,
    registerActivity,
    getThrottleStatus,
    getStatus,
    start,
    stop,
    
    // Computed
    shouldRunWorkers
  }
}