/**
 * Wallet Connection Throttling Composable
 * 
 * Prevents duplicate wallet connection requests that cause MetaMask RPC errors:
 * "Request of type 'wallet_requestPermissions' already pending for origin"
 */
import { ref, readonly, computed } from 'vue'

// Global connection state to prevent duplicate requests across components
const connectionInProgress = ref(false)
const lastConnectionAttempt = ref(0)
const CONNECTION_THROTTLE_MS = 3000 // 3 seconds between attempts

export function useWalletConnectionThrottle() {
  
  /**
   * Throttled wallet connection wrapper
   * Prevents duplicate permission requests to MetaMask
   */
  const throttledConnect = async (connectFunction) => {
    const now = Date.now()
    
    // Check if connection is already in progress
    if (connectionInProgress.value) {
      console.log('🚫 Wallet connection already in progress, ignoring duplicate request')
      return false
    }
    
    // Check throttle timing
    if (now - lastConnectionAttempt.value < CONNECTION_THROTTLE_MS) {
      const waitTime = Math.ceil((CONNECTION_THROTTLE_MS - (now - lastConnectionAttempt.value)) / 1000)
      console.log(`🚫 Connection throttled - please wait ${waitTime}s to prevent duplicate permission requests`)
      return false
    }
    
    try {
      connectionInProgress.value = true
      lastConnectionAttempt.value = now
      
      console.log('🔄 Opening wallet connection (throttled)')
      
      // Execute the connection function
      await connectFunction()
      
      return true
      
    } catch (error) {
      console.error('❌ Throttled connection failed:', error)
      return false
    } finally {
      // Reset connection flag after delay to allow modal processing
      setTimeout(() => {
        connectionInProgress.value = false
      }, 2000)
    }
  }
  
  return {
    throttledConnect,
    connectionInProgress: readonly(connectionInProgress),
    isThrottled: computed(() => {
      const now = Date.now()
      return now - lastConnectionAttempt.value < CONNECTION_THROTTLE_MS
    })
  }
}