import { computed, ref, watch } from 'vue'

/**
 * Clean Solana wallet provider
 * Handles only Solana-based wallets (Phantom, Solflare, etc.)
 * Separated from Ethereum and transaction logic
 */
export function useSolanaWallet() {
  // Connection state
  const isConnected = ref(false)
  const isConnecting = ref(false)
  const address = ref(null)
  const balance = ref('0')
  const error = ref(null)
  const walletType = ref(null)
  
  // Connection management
  const connectionAttempts = ref(0)
  const maxRetries = 3
  const lastConnectedWallet = ref(null)

  // Supported Solana wallets configuration
  const SUPPORTED_WALLETS = {
    phantom: {
      name: 'Phantom',
      windowKey: 'solana',
      requiredMethods: ['connect', 'disconnect', 'signTransaction'],
      checkInstalled: () => typeof window !== 'undefined' && window.solana?.isPhantom
    },
    solflare: {
      name: 'Solflare',
      windowKey: 'solflare',
      requiredMethods: ['connect', 'disconnect', 'signTransaction'],
      checkInstalled: () => typeof window !== 'undefined' && window.solflare?.isSolflare
    }
  }

  // Computed properties
  const shortAddress = computed(() => {
    if (!address.value) return ''
    return `${address.value.slice(0, 6)}...${address.value.slice(-4)}`
  })

  const formattedBalance = computed(() => {
    const bal = parseFloat(balance.value)
    if (bal === 0) return '0'
    return bal.toFixed(4)
  })

  const availableWallets = computed(() => {
    return Object.entries(SUPPORTED_WALLETS)
      .filter(([_, config]) => config.checkInstalled())
      .map(([type, config]) => ({
        type,
        name: config.name,
        isInstalled: true
      }))
  })

  const connectedWalletInfo = computed(() => {
    if (!isConnected.value || !walletType.value) return null
    return SUPPORTED_WALLETS[walletType.value] || null
  })

  // Wallet connection functions
  const connectSolanaWallet = async (targetWalletType = 'phantom') => {
    if (isConnecting.value) {
      throw new Error('Connection already in progress')
    }

    try {
      // Reset connection attempts for new wallet type
      if (lastConnectedWallet.value !== targetWalletType) {
        connectionAttempts.value = 0
      }

      if (connectionAttempts.value >= maxRetries) {
        throw new Error(`Maximum connection attempts (${maxRetries}) exceeded for ${targetWalletType}`)
      }

      const walletConfig = SUPPORTED_WALLETS[targetWalletType]
      if (!walletConfig) {
        throw new Error(`Unsupported wallet type: ${targetWalletType}`)
      }

      if (!walletConfig.checkInstalled()) {
        throw new Error(`${walletConfig.name} wallet is not installed`)
      }

      isConnecting.value = true
      error.value = null
      connectionAttempts.value++
      lastConnectedWallet.value = targetWalletType

      const walletInstance = window[walletConfig.windowKey]

      // Verify required methods are available
      for (const method of walletConfig.requiredMethods) {
        if (typeof walletInstance[method] !== 'function') {
          throw new Error(`${walletConfig.name} wallet missing required method: ${method}`)
        }
      }

      // Check if already connected silently first
      if (walletInstance.isConnected) {
        const response = { publicKey: walletInstance.publicKey }
        address.value = response.publicKey.toString()
        isConnected.value = true
        walletType.value = targetWalletType
        connectionAttempts.value = 0
        return {
          success: true,
          address: address.value,
          walletType: targetWalletType,
          balance: balance.value
        }
      }

      // Attempt connection with timeout (but only if user explicitly requested it)
      const connectionPromise = walletInstance.connect()
      const timeoutPromise = new Promise((_, reject) => {
        setTimeout(() => reject(new Error('Connection timeout')), 30000)
      })

      const response = await Promise.race([connectionPromise, timeoutPromise])

      if (!response?.publicKey) {
        throw new Error('No public key received from wallet')
      }

      // Update state
      address.value = response.publicKey.toString()
      isConnected.value = true
      walletType.value = targetWalletType
      connectionAttempts.value = 0

      // Get balance
      await updateBalance()

      // Save preference
      saveWalletPreference(targetWalletType)

      // Setup event listeners for account changes
      setupEventListeners(walletInstance)

      return {
        success: true,
        address: address.value,
        walletType: targetWalletType,
        balance: balance.value
      }

    } catch (err) {
      console.error(`Failed to connect ${targetWalletType}:`, err)
      error.value = err.message

      // Reset attempts if we've hit the max
      if (connectionAttempts.value >= maxRetries) {
        connectionAttempts.value = 0
        lastConnectedWallet.value = null
      }

      throw err
    } finally {
      isConnecting.value = false
    }
  }

  const disconnectSolanaWallet = async () => {
    try {
      if (!isConnected.value || !walletType.value) {
        return { success: true }
      }

      const walletConfig = SUPPORTED_WALLETS[walletType.value]
      const walletInstance = window[walletConfig.windowKey]

      if (walletInstance && typeof walletInstance.disconnect === 'function') {
        await walletInstance.disconnect()
      }

    } catch (err) {
      console.error('Failed to disconnect Solana wallet:', err)
      // Continue with cleanup even if disconnect fails
    } finally {
      // Always reset state
      resetWalletState()
      clearWalletPreference()
    }

    return { success: true }
  }

  // Balance management
  const updateBalance = async () => {
    if (!isConnected.value || !address.value || !walletType.value) {
      balance.value = '0'
      return
    }

    try {
      const walletConfig = SUPPORTED_WALLETS[walletType.value]
      const walletInstance = window[walletConfig.windowKey]

      // Different wallets may have different balance methods
      let newBalance = '0'

      if (walletType.value === 'phantom' && walletInstance.getBalance) {
        const balanceInLamports = await walletInstance.getBalance()
        newBalance = (balanceInLamports / 1e9).toString() // Convert lamports to SOL
      } else if (walletInstance.connection) {
        // Fallback: use connection.getBalance if available
        const balanceInLamports = await walletInstance.connection.getBalance(address.value)
        newBalance = (balanceInLamports / 1e9).toString()
      }

      balance.value = newBalance

    } catch (err) {
      console.warn('Failed to update Solana balance:', err)
      balance.value = '0'
    }
  }

  // Event listeners for wallet changes
  const setupEventListeners = (walletInstance) => {
    if (!walletInstance) return

    // Listen for account changes
    if (walletInstance.on && typeof walletInstance.on === 'function') {
      walletInstance.on('accountChanged', (newAccount) => {
        if (newAccount) {
          address.value = newAccount.toString()
          updateBalance()
        } else {
          resetWalletState()
        }
      })

      walletInstance.on('disconnect', () => {
        resetWalletState()
      })
    }
  }

  // State management
  const resetWalletState = () => {
    isConnected.value = false
    isConnecting.value = false
    address.value = null
    balance.value = '0'
    walletType.value = null
    error.value = null
    connectionAttempts.value = 0
    lastConnectedWallet.value = null
  }

  // Persistence functions
  const saveWalletPreference = (walletTypeValue) => {
    if (typeof window !== 'undefined') {
      localStorage.setItem('solana-wallet-preference', walletTypeValue)
    }
  }

  const getWalletPreference = () => {
    if (typeof window !== 'undefined') {
      return localStorage.getItem('solana-wallet-preference')
    }
    return null
  }

  const clearWalletPreference = () => {
    if (typeof window !== 'undefined') {
      localStorage.removeItem('solana-wallet-preference')
    }
  }

  // Auto-reconnection
  const attemptAutoReconnect = async () => {
    const preferredWallet = getWalletPreference()
    
    if (!preferredWallet || isConnected.value) {
      return false
    }

    try {
      await connectSolanaWallet(preferredWallet)
      return true
    } catch (err) {
      console.warn('Solana auto-reconnect failed:', err)
      clearWalletPreference()
      return false
    }
  }

  // Utility functions
  const refreshBalance = async () => {
    await updateBalance()
  }

  const validateConnection = () => {
    if (!isConnected.value) {
      throw new Error('Solana wallet not connected')
    }
    
    if (!address.value) {
      throw new Error('No Solana address available')
    }

    if (!walletType.value) {
      throw new Error('Unknown Solana wallet type')
    }

    return true
  }

  // Get wallet instance for advanced usage
  const getWalletInstance = () => {
    if (!isConnected.value || !walletType.value) {
      return null
    }

    const walletConfig = SUPPORTED_WALLETS[walletType.value]
    return window[walletConfig.windowKey] || null
  }

  // Watch for balance updates periodically when connected
  let balanceUpdateInterval = null
  watch(isConnected, (connected) => {
    if (connected) {
      // Update balance every 30 seconds when connected
      balanceUpdateInterval = setInterval(updateBalance, 30000)
    } else {
      if (balanceUpdateInterval) {
        clearInterval(balanceUpdateInterval)
        balanceUpdateInterval = null
      }
    }
  })

  // Cleanup on unmount
  const cleanup = () => {
    if (balanceUpdateInterval) {
      clearInterval(balanceUpdateInterval)
    }
    resetWalletState()
  }

  // Return clean interface
  return {
    // Connection state
    isConnected,
    isConnecting,
    address,
    shortAddress,
    balance: formattedBalance,
    walletType,
    connectedWalletInfo,
    
    // Available wallets
    availableWallets,
    
    // Connection methods
    connectSolanaWallet,
    disconnectSolanaWallet,
    attemptAutoReconnect,
    
    // Balance management
    refreshBalance,
    updateBalance,
    
    // Utility methods
    validateConnection,
    getWalletInstance,
    cleanup,
    
    // Error state
    error,
    
    // Configuration
    SUPPORTED_WALLETS,
    
    // Connection status
    connectionAttempts: computed(() => connectionAttempts.value),
    maxRetries
  }
}