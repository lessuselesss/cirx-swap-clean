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
    chainId: '0x????????', // TODO: Add actual Circular chain ID when available
    chainName: 'Circular Protocol',
    nativeCurrency: {
      name: 'CIRX',
      symbol: 'CIRX',
      decimals: 18
    },
    rpcUrls: ['https://rpc.circular.protocol'], // TODO: Add actual RPC URL
    blockExplorerUrls: ['https://explorer.circular.protocol'] // TODO: Add actual explorer URL
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

  // Detect Saturn wallet and Circular chain connection
  const detectCircularChain = async () => {
    try {
      isDetectingChain.value = true
      chainConnectionError.value = ''

      // Show toast notification for detection start
      showToast('info', 'Checking Connection', 'Checking for Circular chain access...')

      // Check for Saturn wallet specifically (most likely to have Circular chain)
      if (window.extension) {
        console.log('🪐 Saturn wallet detected, checking for Circular chain access...')
        
        // TODO: Implement actual Saturn wallet Circular chain detection
        // This is where we'll add the real Saturn wallet API calls
        const saturnInfo = await detectSaturnCircularChain()
        
        if (saturnInfo.hasCircularChain) {
          cirxAddress.value = saturnInfo.address
          isCircularChainConnected.value = true
          console.log('✅ Circular chain connection found:', cirxAddress.value)
          
          // Show success toast
          showToast('success', 'Circular Chain Connected', `Connected with ${saturnInfo.address.slice(0, 6)}...${saturnInfo.address.slice(-4)}`)
          
          // Fetch CIRX balance
          await fetchCirxBalance()
        }
      }
      
      // Check other wallets that might support Circular chain
      if (!isCircularChainConnected.value) {
        await checkOtherWalletsForCircular()
      }
      
      // Show completion status if no connection found
      if (!isCircularChainConnected.value) {
        if (window.extension) {
          showToast('warning', 'Saturn Wallet Found', 'Saturn wallet detected but no Circular chain access yet')
        } else {
          showToast('info', 'No Circular Chain', 'No Circular chain access detected')
        }
      }
      
    } catch (error) {
      console.error('❌ Error detecting Circular chain:', error)
      chainConnectionError.value = error.message
      showToast('error', 'Connection Error', error.message)
    } finally {
      isDetectingChain.value = false
    }
  }

  // Saturn wallet specific detection
  const detectSaturnCircularChain = async () => {
    // Placeholder implementation - replace with actual Saturn wallet API
    if (window.extension && typeof window.extension === 'object') {
      try {
        // Check if extension has circular chain methods
        if (window.extension.getCircularAddress) {
          const address = await window.extension.getCircularAddress()
          return { hasCircularChain: true, address }
        }
        
        // Alternative: Check if it has generic account access
        if (window.extension.getAccounts) {
          const accounts = await window.extension.getAccounts()
          const circularAccount = accounts.find(acc => acc.chain === 'circular')
          if (circularAccount) {
            return { hasCircularChain: true, address: circularAccount.address }
          }
        }
        
        // TODO: Add more Saturn wallet detection methods as they become available
        console.log('🔍 Saturn wallet found but no Circular chain methods detected yet')
        return { hasCircularChain: false, address: null }
        
      } catch (error) {
        console.warn('⚠️ Error accessing Saturn wallet Circular chain:', error)
        return { hasCircularChain: false, address: null }
      }
    }
    
    return { hasCircularChain: false, address: null }
  }

  // Check other wallets for Circular chain support
  const checkOtherWalletsForCircular = async () => {
    // Check if any Ethereum wallets have Circular chain configured
    if (window.ethereum) {
      try {
        const chainId = await window.ethereum.request({ method: 'eth_chainId' })
        if (chainId === CIRCULAR_CHAIN_CONFIG.chainId) {
          const accounts = await window.ethereum.request({ method: 'eth_accounts' })
          if (accounts.length > 0) {
            cirxAddress.value = accounts[0]
            isCircularChainConnected.value = true
            console.log('✅ Circular chain found via Ethereum wallet')
            await fetchCirxBalance()
          }
        }
      } catch (error) {
        console.log('ℹ️ No Circular chain in Ethereum wallets:', error.message)
      }
    }
  }

  // Fetch CIRX balance for the connected address
  const fetchCirxBalance = async () => {
    if (!cirxAddress.value) return

    try {
      isLoadingBalance.value = true
      
      // TODO: Implement actual CIRX balance fetching
      // This could be via Saturn wallet API, RPC call, or indexer
      
      if (window.extension && window.extension.getCircularBalance) {
        // Saturn wallet method
        const balance = await window.extension.getCircularBalance(cirxAddress.value)
        cirxBalance.value = balance.toString()
      } else {
        // Fallback to RPC call or indexer
        const balance = await fetchBalanceFromRPC(cirxAddress.value)
        cirxBalance.value = balance
      }
      
      console.log(`💰 CIRX Balance: ${cirxBalance.value} CIRX`)
      
    } catch (error) {
      console.error('❌ Error fetching CIRX balance:', error)
      cirxBalance.value = '0'
    } finally {
      isLoadingBalance.value = false
    }
  }

  // Placeholder for RPC balance fetching
  const fetchBalanceFromRPC = async (address) => {
    // TODO: Implement actual RPC call to Circular chain
    // For now, return placeholder
    console.log('📡 Would fetch CIRX balance from RPC for:', address)
    return '0'
  }

  // Add Circular chain to wallet
  const addCircularChain = async () => {
    if (!window.ethereum) {
      throw new Error('No Ethereum wallet detected')
    }

    try {
      await window.ethereum.request({
        method: 'wallet_addEthereumChain',
        params: [CIRCULAR_CHAIN_CONFIG]
      })
      
      // Re-detect after adding
      await detectCircularChain()
      
    } catch (error) {
      console.error('❌ Error adding Circular chain:', error)
      throw error
    }
  }

  // Switch to Circular chain
  const switchToCircularChain = async () => {
    if (!window.ethereum) {
      throw new Error('No Ethereum wallet detected')
    }

    try {
      await window.ethereum.request({
        method: 'wallet_switchEthereumChain',
        params: [{ chainId: CIRCULAR_CHAIN_CONFIG.chainId }]
      })
      
      await detectCircularChain()
      
    } catch (error) {
      if (error.code === 4902) {
        // Chain not added yet, add it first
        await addCircularChain()
      } else {
        console.error('❌ Error switching to Circular chain:', error)
        throw error
      }
    }
  }

  // Format CIRX balance for display
  const formatCirxBalance = computed(() => {
    const balance = parseFloat(cirxBalance.value) || 0
    if (balance === 0) return '0.0000'
    
    // Format with appropriate decimal places
    if (balance < 0.0001) return '< 0.0001'
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
    
    if (window.extension) {
      return {
        status: 'saturn-no-circular',
        message: 'Saturn wallet detected but no Circular chain access yet',
        action: 'help'
      }
    }
    
    return {
      status: 'no-circular',
      message: 'No Circular chain access detected',
      action: 'add-chain'
    }
  })

  // Auto-detect on mount
  onMounted(() => {
    // Wait a bit for extensions to load
    setTimeout(detectCircularChain, 2000)
  })

  // Watch for wallet connection changes
  watch(() => window.ethereum?.selectedAddress, (newAddress) => {
    if (newAddress) {
      detectCircularChain()
    }
  })

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
    addCircularChain,
    switchToCircularChain,
    
    // Config
    CIRCULAR_CHAIN_CONFIG
  }
}