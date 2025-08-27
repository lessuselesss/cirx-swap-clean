// Circular chain and CIRX address management composable
import { ref, computed, watch, onMounted } from 'vue'

export const useCircularChain = (toastCallback = null) => {
  // Reactive state
  const cirxAddress = ref('')
  const isCircularChainConnected = ref(false)
  const cirxBalance = ref('0')
  const isLoadingBalance = ref(false)
  const chainConnectionError = ref('')
  const isDetectingChain = ref(true)

  // Circular chain configuration
  const CIRCULAR_CHAIN_CONFIG = {
    chainId: '0x7E4', // 2020 in decimal - placeholder for Circular Protocol chain ID
    chainName: 'Circular Protocol Testnet',
    nativeCurrency: {
      name: 'CIRX',
      symbol: 'CIRX',
      decimals: 18
    },
    rpcUrls: ['https://rpc-testnet.circular.protocol'], // Placeholder RPC URL
    blockExplorerUrls: ['https://explorer-testnet.circular.protocol'] // Placeholder explorer URL
  }

  // Check if Circular chain is available
  const isCircularChainAvailable = computed(() => {
    return !!(cirxAddress.value && isCircularChainConnected.value)
  })

  // Helper to show toast notifications
  const showToast = (type, title, message) => {
    if (toastCallback) {
      toastCallback({ type, title, message })
    }
  }

  // Manual Circular chain connection (Saturn logic removed)
  const detectCircularChain = async () => {
    try {
      isDetectingChain.value = true
      chainConnectionError.value = ''

      // Show toast notification for detection start
      showToast('info', 'Checking Connection', 'Checking for Circular chain connection...')

      // No automatic wallet detection - require manual address input
      if (!cirxAddress.value) {
        showToast('info', 'Manual Setup Required', 'Please enter your Circular address manually below')
      } else {
        // Try to fetch balance for existing address
        await fetchCirxBalance()
        showToast('success', 'Connected', `Using address ${cirxAddress.value.slice(0, 6)}...${cirxAddress.value.slice(-4)}`)
      }
      
    } catch (error) {
      console.error('❌ Error with Circular chain:', error)
      chainConnectionError.value = error.message
      showToast('error', 'Connection Error', error.message)
    } finally {
      isDetectingChain.value = false
    }
  }


  // Fetch CIRX balance for the connected address
  const fetchCirxBalance = async () => {
    if (!cirxAddress.value) return

    try {
      isLoadingBalance.value = true
      
      // Fetch balance via backend NAG API
      const balance = await fetchBalanceFromNAG(cirxAddress.value)
      cirxBalance.value = balance
      
      console.log(`💰 CIRX Balance: ${cirxBalance.value} CIRX`)
      
    } catch (error) {
      console.error('❌ Error fetching CIRX balance:', error)
      cirxBalance.value = '0'
    } finally {
      isLoadingBalance.value = false
    }
  }

  // Fetch balance from backend NAG API
  const fetchBalanceFromNAG = async (address) => {
    try {
      const { $fetch } = useNuxtApp()
      const response = await $fetch('/debug/nag-balance', {
        method: 'POST',
        body: {
          nagUrl: 'https://nag.circularlabs.io/NAG.php?cep=',
          endpoint: 'GetWalletBalance_',
          address: address
        }
      })
      
      if (response.success && response.nag_response?.body?.Result === 200) {
        return response.nag_response.body.Response.Balance.toString()
      } else {
        console.warn('NAG balance fetch failed:', response.nag_response?.body || response.error)
        return '0'
      }
    } catch (error) {
      console.error('Error fetching balance from NAG:', error)
      return '0'
    }
  }


  // Format CIRX balance for display
  const formatCirxBalance = computed(() => {
    const balance = parseFloat(cirxBalance.value) || 0
    if (balance === 0) return '0.0000'
    
    // Format with appropriate decimal places - enhanced precision for debugging
    if (balance < 0.000000001) return balance.toExponential(2) // Scientific notation for tiny amounts
    if (balance < 0.0001) return balance.toFixed(8) // Show 8 decimals for small amounts
    if (balance < 1) return balance.toFixed(6)
    if (balance < 1000) return balance.toFixed(4)
    if (balance < 1000000) return (balance / 1000).toFixed(2) + 'K'
    return (balance / 1000000).toFixed(2) + 'M'
  })

  // UX guidance based on Circular chain availability
  const getUxGuidance = computed(() => {
    if (isDetectingChain.value) {
      return {
        status: 'detecting',
        message: 'Checking for Circular chain access...',
        action: null
      }
    }
    
    if (chainConnectionError.value) {
      return {
        status: 'error',
        message: `Connection error: ${chainConnectionError.value}`,
        action: 'retry'
      }
    }
    
    if (isCircularChainAvailable.value) {
      return {
        status: 'connected',
        message: `Connected to Circular chain (${formatCirxBalance.value} CIRX)`,
        action: null
      }
    }
    
    // No automatic wallet detection - manual address input required"
    
    return {
      status: 'no-circular', 
      message: 'Ready for Circular address input',
      action: null
    }
  })

  // Auto-detect on mount
  onMounted(() => {
    // Wait a bit for extensions to load
    setTimeout(detectCircularChain, 2000)
  })

  // Manual address management only (wallet watching removed)"

  return {
    // State
    cirxAddress,
    isCircularChainConnected,
    cirxBalance,
    isLoadingBalance,
    chainConnectionError,
    isDetectingChain,
    
    // Computed
    isCircularChainAvailable,
    formatCirxBalance,
    getUxGuidance,
    
    // Methods
    detectCircularChain,
    fetchCirxBalance,
    
    // Config
    CIRCULAR_CHAIN_CONFIG
  }
}