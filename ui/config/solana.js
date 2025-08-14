import { ref, computed } from 'vue'

export function useSolanaWallet() {
  // Reactive state for Solana wallet
  const isConnected = ref(false)
  const publicKey = ref(null)
  const balance = ref(0)
  const connection = ref(null)

  // Check if Phantom wallet is available
  const isPhantomAvailable = computed(() => {
    if (typeof window === 'undefined') return false
    return typeof window.solana !== 'undefined' && window.solana.isPhantom
  })

  // Connect to Solana wallet (Phantom)
  const connect = async (options = {}) => {
    try {
      if (!isPhantomAvailable.value) {
        throw new Error('Phantom wallet is not installed')
      }

      // Check if already connected silently first
      if (window.solana.isConnected) {
        publicKey.value = window.solana.publicKey?.toString()
        isConnected.value = true
        return { publicKey: window.solana.publicKey }
      }

      // If silent mode requested and not already connected, don't show modal
      if (options.silent) {
        return null
      }

      const response = await window.solana.connect()
      publicKey.value = response.publicKey.toString()
      isConnected.value = true
      
      return response
    } catch (error) {
      console.error('Failed to connect to Solana wallet:', error)
      throw error
    }
  }

  // Disconnect from Solana wallet
  const disconnect = async () => {
    try {
      if (window.solana && window.solana.disconnect) {
        await window.solana.disconnect()
      }
      
      isConnected.value = false
      publicKey.value = null
      balance.value = 0
    } catch (error) {
      console.error('Failed to disconnect from Solana wallet:', error)
      throw error
    }
  }

  // Get balance (placeholder implementation)
  const getBalance = async () => {
    if (!isConnected.value || !publicKey.value) return 0
    
    try {
      // This would typically use Solana web3.js to get actual balance
      // For now, return a placeholder value
      balance.value = 0
      return balance.value
    } catch (error) {
      console.error('Failed to get Solana balance:', error)
      return 0
    }
  }

  return {
    // State
    isConnected,
    publicKey,
    balance,
    connection,
    isPhantomAvailable,
    
    // Methods
    connect,
    disconnect,
    getBalance
  }
}