/**
 * Circular Protocol Address Validation Composable
 * 
 * Provides async validation for Circular Protocol addresses using the NAG API
 * Synchronized with backend network/chain configuration
 */

import { ref, computed, watch, onMounted } from 'vue'

export const useCircularAddressValidation = () => {
  const validationCache = ref(new Map())
  const validationPromises = ref(new Map())
  const networkConfig = ref(null)

  // Get backend configuration for network synchronization
  const getBackendConfig = async () => {
    if (networkConfig.value) {
      return networkConfig.value
    }

    try {
      const config = useRuntimeConfig()
      const apiBaseUrl = config.public.apiBaseUrl || 'http://localhost:18423/api/v1'
      
      const response = await fetch(`${apiBaseUrl}/config/circular-network`)
      if (!response.ok) {
        throw new Error(`Config API error: ${response.status}`)
      }
      
      const data = await response.json()
      networkConfig.value = data
      
      console.log('🔗 Backend network config loaded:', {
        network: data.network,
        environment: data.environment, 
        chain: data.chain_name
      })
      
      return data
    } catch (error) {
      console.error('Failed to get backend config, using fallback:', error)
      // Fallback configuration
      return {
        network: 'testnet',
        environment: 'development',
        blockchain_id: '8a20baa40c45dc5055aeb26197c203e576ef389d9acb171bd62da11dc5ad72b2',
        nag_url: '/api/v1/proxy/circular-labs?endpoint=NAG.php&cep=',
        chain_name: 'Circular SandBox'
      }
    }
  }

  /**
   * Check delimiter-based address validation
   * Only green light if: 0x + exactly 64 chars + balance >= 0.0
   * @param {string} address - The address to validate 
   * @returns {Promise<{isValid: boolean, exists: boolean, hasBalance: boolean, balance?: string, error?: string}>}
   */
  const checkAddressExists = async (address) => {
    if (!address || typeof address !== 'string') {
      return { isValid: false, exists: false, hasBalance: false, error: 'Invalid address format' }
    }

    // Get backend config for synchronized validation
    const config = await getBackendConfig()
    const cacheKey = `${address}_${config.blockchain_id}`
    
    if (validationCache.value.has(cacheKey)) {
      return validationCache.value.get(cacheKey)
    }

    if (validationPromises.value.has(cacheKey)) {
      return validationPromises.value.get(cacheKey)
    }

    // Start validation
    const validationPromise = performAddressValidation(address, config)
    validationPromises.value.set(cacheKey, validationPromise)

    try {
      const result = await validationPromise
      validationCache.value.set(cacheKey, result)
      return result
    } finally {
      validationPromises.value.delete(cacheKey)
    }
  }

  /**
   * Perform the actual address validation against NAG API
   * Checks both wallet existence AND balance (green light only if balance >= 0.0)
   * @private
   */
  const performAddressValidation = async (address, config) => {
    try {
      console.log('🔍 Validating Circular address:', {
        address: address.slice(0, 10) + '...',
        blockchain: config.chain_name,
        network: config.network
      })

      // Step 1: Check wallet existence first
      const walletResponse = await fetch(config.nag_url + 'Circular_CheckWallet_', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({
          Blockchain: config.blockchain_id,
          Address: address.replace('0x', ''), // Strip 0x prefix as required by NAG API
          Version: config.version || '1.0.8'
        })
      })

      if (!walletResponse.ok) {
        throw new Error(`HTTP ${walletResponse.status}: ${walletResponse.statusText}`)
      }

      const walletData = await walletResponse.json()
      console.log('🔍 Wallet check response:', walletData)

      if (walletData.Result !== 200) {
        return {
          isValid: false,
          exists: false,
          hasBalance: false,
          error: walletData.ERROR || `Wallet check failed: ${walletData.Result}`
        }
      }

      // Step 2: Get balance for existing wallet (required for green light)
      const balanceResponse = await fetch(config.nag_url + 'Circular_GetWalletBalance_', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({
          Blockchain: config.blockchain_id,
          Address: address.replace('0x', ''), // Strip 0x prefix as required by NAG API
          Asset: 'CIRX',
          Version: config.version || '1.0.8'
        })
      })

      if (!balanceResponse.ok) {
        throw new Error(`Balance HTTP ${balanceResponse.status}: ${balanceResponse.statusText}`)
      }

      const balanceData = await balanceResponse.json()
      console.log('🔍 Balance check response:', balanceData)

      if (balanceData.Result === 200 && balanceData.Response?.Balance !== undefined) {
        const balance = parseFloat(balanceData.Response.Balance) || 0
        
        return {
          isValid: true,
          exists: true,
          hasBalance: balance >= 0.0, // Green light only if balance >= 0.0
          balance: balance.toString(),
          response: {
            wallet: walletData.Response,
            balance: balanceData.Response
          }
        }
      } else {
        // Wallet exists but balance check failed
        return {
          isValid: true,
          exists: true,
          hasBalance: false,
          error: `Balance check failed: ${balanceData.ERROR || balanceData.Result}`,
          response: { wallet: walletData.Response }
        }
      }
    } catch (error) {
      console.error('🔍 Address validation error:', error)
      return {
        isValid: false,
        exists: false,
        hasBalance: false,
        error: `Validation failed: ${error.message}`
      }
    }
  }

  /**
   * Get cached validation result if available
   */
  const getCachedValidation = (address) => {
    const cacheKey = `${address}_${getBlockchainId()}`
    return validationCache.value.get(cacheKey)
  }

  /**
   * Clear validation cache
   */
  const clearCache = () => {
    validationCache.value.clear()
    validationPromises.value.clear()
  }

  /**
   * Delimiter-based address format validation 
   * Only valid if: 0x + exactly 64 characters
   * This defines when the delimiter has "stopped" and validation should occur
   */
  const isValidCircularAddressFormat = (address) => {
    if (!address || typeof address !== 'string') {
      return false
    }
    
    const trimmed = address.trim()
    
    // Delimiter logic: 0x + exactly 64 hex characters = complete address
    return (
      trimmed.startsWith('0x') &&
      trimmed.length === 66 && // 0x (2) + 64 hex chars
      /^0x[a-fA-F0-9]{64}$/.test(trimmed)
    )
  }

  /**
   * Check if address is awaiting Circular wallet API response
   * Yellow flashing condition: exactly 66 characters but API response not yet received
   */
  const isAddressPending = (address) => {
    if (!address || typeof address !== 'string') {
      return false
    }
    
    const trimmed = address.trim()
    
    // Only flash yellow when we have a complete address format (66 chars)
    // but are still waiting for the Circular wallet API response
    return (
      trimmed.startsWith('0x') &&
      trimmed.length === 66 && // Exactly the complete length (0x + 64 chars)
      /^0x[a-fA-F0-9]{64}$/.test(trimmed) // Valid hex format
    )
  }

  return {
    checkAddressExists,
    getCachedValidation,
    clearCache,
    isValidCircularAddressFormat,
    isAddressPending
  }
}

// Circular chain and CIRX address management composable
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
          nagUrl: '/api/v1/proxy/circular-labs?endpoint=NAG.php&cep=',
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