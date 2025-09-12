// Wallet detection composable
import { computed } from 'vue'

export function iuseWalletDetection() {
  const isSaturnWalletPresent = computed(() => {
    if (typeof window === 'undefined') return false
    
    // Check for Saturn wallet provider
    return !!(window.saturn || 
             (window.ethereum && window.ethereum.isSaturn) ||
             (window.ethereum && window.ethereum.providers && 
              window.ethereum.providers.some(p => p.isSaturn)))
  })
  
  // Enhanced Saturn wallet detection based on comprehensive detection
  const isSaturnWalletDetected = computed(() => {
    // Saturn wallet detection disabled for now
    return false
  })
  
  const handleCircularToast = ({ type, title, message }) => {
    if (typeof window !== 'undefined' && window.$toast) {
      window.$toast.connection[type](message, { title })
    }
  }
  
  // Static values for development
  const isCircularChainAvailable = computed(() => false)
  const isCircularChainConnected = computed(() => false)
  
  return {
    isSaturnWalletPresent,
    isSaturnWalletDetected,
    handleCircularToast,
    isCircularChainAvailable,
    isCircularChainConnected
  }
}