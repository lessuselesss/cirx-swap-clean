// Backend health check composable
import { ref } from 'vue'

export function iuseBackendHealth() {
  const isBackendConnected = ref(true)
  const backendHealthCheckInterval = ref(null)
  
  const checkBackendHealth = async () => {
    let controller = null
    let timeoutId = null
    
    try {
      const config = useRuntimeConfig()
      const apiBaseUrl = config.public.apiBaseUrl || 'http://localhost:18423/v1'
      
      controller = new AbortController()
      timeoutId = setTimeout(() => {
        controller.abort()
      }, 8000) // Increased to 8 seconds for better reliability
      
      const response = await fetch(`${apiBaseUrl}/ping`, {
        signal: controller.signal,
        method: 'GET',
        headers: {
          'Content-Type': 'application/json'
        }
      })
      
      // Clear timeout on successful response
      if (timeoutId) {
        clearTimeout(timeoutId)
        timeoutId = null
      }
      
      if (response.ok) {
        const data = await response.json()
        // Use comprehensive transaction_ready check instead of basic status
        const wasConnected = isBackendConnected.value
        isBackendConnected.value = data.transaction_ready === true
        
        // Log health check details for debugging
        console.log('🏥 Backend health check:', {
          status: data.status,
          transaction_ready: data.transaction_ready,
          health_score: data.health_score,
          was_connected: wasConnected,
          now_connected: isBackendConnected.value
        })
        
        // Log any failed health checks for troubleshooting
        if (!data.transaction_ready && data.checks) {
          const failedChecks = Object.entries(data.checks)
            .filter(([key, check]) => check.status === 'error')
            .map(([key, check]) => `${key}: ${check.error || 'unknown error'}`)
          
          if (failedChecks.length > 0) {
            console.warn('⚠️ Backend not transaction-ready. Failed checks:', failedChecks)
          }
        }
      } else {
        console.error(`❌ Backend health endpoint returned ${response.status}`)
        isBackendConnected.value = false
      }
    } catch (error) {
      // Handle AbortError differently from other errors
      if (error.name === 'AbortError') {
        console.warn('⏱️ Backend health check timed out (8s) - backend may be slow')
      } else {
        console.error('Backend health check failed:', error)
      }
      isBackendConnected.value = false
    } finally {
      // Always clean up timeout
      if (timeoutId) {
        clearTimeout(timeoutId)
      }
    }
  }
  
  const startHealthChecks = (interval = 30000) => {
    if (backendHealthCheckInterval.value) {
      clearInterval(backendHealthCheckInterval.value)
    }
    
    // Initial check
    checkBackendHealth()
    
    // Set up interval
    backendHealthCheckInterval.value = setInterval(checkBackendHealth, interval)
  }
  
  const stopHealthChecks = () => {
    if (backendHealthCheckInterval.value) {
      clearInterval(backendHealthCheckInterval.value)
      backendHealthCheckInterval.value = null
    }
  }
  
  return {
    isBackendConnected,
    backendHealthCheckInterval,
    checkBackendHealth,
    startHealthChecks,
    stopHealthChecks
  }
}