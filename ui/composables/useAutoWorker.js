import { ref, readonly, onMounted, onUnmounted } from 'vue'

/**
 * Auto Worker Composable
 * 
 * Automatically processes pending transactions in the background
 * Perfect for FTP deployments where background services aren't available
 */
export const useAutoWorker = () => {
  const isProcessing = ref(false)
  const lastProcessed = ref(null)
  const processInterval = ref(null)
  const errorCount = ref(0)
  const maxErrors = 3

  // Configuration
  const config = {
    intervalMs: 30000, // 30 seconds
    maxRetries: 3,
    backoffMultiplier: 1.5
  }

  /**
   * Process pending transactions via worker endpoint
   */
  const processTransactions = async () => {
    if (isProcessing.value) {
      console.log('🔧 Worker already processing, skipping...')
      return
    }

    try {
      isProcessing.value = true
      const apiConfig = useRuntimeConfig()
      
      console.log('🔧 Auto-processing transactions...')
      
      const response = await fetch(`${apiConfig.public.apiBaseUrl}/v1/workers/process`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        }
      })

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`)
      }

      const result = await response.json()
      
      if (result.success) {
        lastProcessed.value = new Date().toISOString()
        errorCount.value = 0 // Reset error count on success
        
        const totalProcessed = (result.payment_verification?.processed || 0) + 
                             (result.cirx_transfers?.processed || 0)
        
        if (totalProcessed > 0) {
          console.log(`✅ Auto-worker processed ${totalProcessed} transactions`)
        }
      } else {
        console.warn('⚠️ Worker returned error:', result.error)
        errorCount.value++
      }

    } catch (error) {
      console.error('❌ Auto-worker failed:', error)
      errorCount.value++
      
      // If too many errors, increase interval to reduce load
      if (errorCount.value >= maxErrors) {
        console.warn(`🚫 Too many worker errors (${errorCount.value}), backing off...`)
      }
    } finally {
      isProcessing.value = false
    }
  }

  /**
   * Start auto-processing
   */
  const startAutoProcessing = () => {
    if (processInterval.value) {
      console.log('🔧 Auto-worker already running')
      return
    }

    console.log('🚀 Starting auto-worker (every 30 seconds)')
    
    // Process immediately, then on interval
    processTransactions()
    
    processInterval.value = setInterval(() => {
      // Back off if too many errors
      if (errorCount.value >= maxErrors) {
        const backoffInterval = config.intervalMs * Math.pow(config.backoffMultiplier, errorCount.value - maxErrors)
        console.log(`⏳ Backing off for ${backoffInterval/1000}s due to errors`)
        setTimeout(processTransactions, backoffInterval)
      } else {
        processTransactions()
      }
    }, config.intervalMs)
  }

  /**
   * Stop auto-processing
   */
  const stopAutoProcessing = () => {
    if (processInterval.value) {
      clearInterval(processInterval.value)
      processInterval.value = null
      console.log('🛑 Auto-worker stopped')
    }
  }

  /**
   * Get worker status for debugging
   */
  const getWorkerStatus = () => {
    return {
      isRunning: !!processInterval.value,
      isProcessing: isProcessing.value,
      lastProcessed: lastProcessed.value,
      errorCount: errorCount.value,
      intervalMs: config.intervalMs
    }
  }

  /**
   * Manual trigger for testing
   */
  const triggerManualProcess = async () => {
    console.log('🔧 Manual worker trigger')
    await processTransactions()
  }

  // Auto-start when composable is used
  onMounted(() => {
    // Only start auto-processing on swap and status pages
    try {
      const route = useRoute()
      const autoStartPages = ['/swap', '/transactions', '/']
      
      if (autoStartPages.some(page => route.path.startsWith(page))) {
        // Small delay to let page load
        setTimeout(startAutoProcessing, 2000)
      }
    } catch (error) {
      // If route is not available, start anyway (global usage)
      console.log('🔧 Auto-worker starting globally (no route context)')
      setTimeout(startAutoProcessing, 2000)
    }
  })

  // Cleanup on unmount
  onUnmounted(() => {
    stopAutoProcessing()
  })

  return {
    // State
    isProcessing: readonly(isProcessing),
    lastProcessed: readonly(lastProcessed),
    errorCount: readonly(errorCount),
    
    // Methods
    startAutoProcessing,
    stopAutoProcessing,
    triggerManualProcess,
    getWorkerStatus
  }
}