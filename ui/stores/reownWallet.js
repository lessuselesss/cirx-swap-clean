import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { useAppKit, useAppKitAccount, useAppKitNetwork, useAppKitState } from '@reown/appkit/vue'
import { useAccount, useBalance, useChainId, useDisconnect } from '@wagmi/vue'
import { formatUnits } from 'viem'

/**
 * Reown AppKit based wallet store
 * Provides unified access to multi-chain wallet functionality
 */
export const useReownWalletStore = defineStore('reownWallet', () => {
  // Initialize refs to avoid undefined access during SSR/initial render
  const isInitialized = ref(false)
  
  // Reown AppKit composables with defensive initialization
  let appKit, appKitAccount, appKitNetwork, appKitState, wagmiAccount, wagmiBalance, wagmiChainId, wagmiDisconnect
  
  try {
    appKit = useAppKit()
    appKitAccount = useAppKitAccount()
    appKitNetwork = useAppKitNetwork()
    appKitState = useAppKitState()
    wagmiAccount = useAccount()
    wagmiBalance = useBalance({
      address: computed(() => appKitAccount?.address?.value || null),
    })
    wagmiChainId = useChainId()
    wagmiDisconnect = useDisconnect()
  } catch (error) {
    console.warn('⚠️ Reown hooks not available during SSR, will initialize on client')
  }
  
  // Extracted values with null safety
  const { open } = appKit || {}
  const { address, isConnected, isConnecting, isDisconnected } = appKitAccount || {}
  const { caipNetwork, chainId } = appKitNetwork || {}
  const { selectedNetworkId } = appKitState || {}
  const { address: wagmiAddress, connector } = wagmiAccount || {}
  const { data: balance, isLoading: isBalanceLoading, refetch: refetchBalance } = wagmiBalance || {}
  const currentChainId = wagmiChainId
  const { disconnect } = wagmiDisconnect || {}

  // Local state
  const isWalletModalOpen = ref(false)
  const selectedToken = ref('ETH')
  const lastActivity = ref(Date.now())
  const globalError = ref(null)

  // Computed properties
  const activeWallet = computed(() => {
    if (!isConnected?.value || !address?.value) return null
    
    return {
      type: getWalletType(),
      address: address.value,
      chain: getChainType(),
      chainId: chainId?.value
    }
  })

  const currentError = computed(() => globalError.value)

  const availableWallets = computed(() => [
    {
      type: 'metamask',
      name: 'MetaMask',
      chain: 'ethereum',
      available: true // Reown handles availability detection
    },
    {
      type: 'walletconnect',
      name: 'WalletConnect',
      chain: 'ethereum',
      available: true
    },
    {
      type: 'coinbase',
      name: 'Coinbase Wallet',
      chain: 'ethereum', 
      available: true
    },
    {
      type: 'phantom',
      name: 'Phantom',
      chain: 'solana',
      available: true // Reown handles Solana wallets too
    }
  ])

  const shortAddress = computed(() => {
    if (!address.value) return ''
    const addr = address.value
    return `${addr.slice(0, 6)}...${addr.slice(-4)}`
  })

  const formattedBalance = computed(() => {
    console.log('🔍 REOWN STORE: formattedBalance computed called')
    console.log('🔍 REOWN STORE: balance?.value:', balance?.value)
    console.log('🔍 REOWN STORE: isConnected:', isConnected?.value)
    console.log('🔍 REOWN STORE: address:', address?.value)
    
    if (!balance?.value) {
      console.log('🔍 REOWN STORE: No balance data, returning 0.0')
      return '0.0'
    }
    
    try {
      console.log('🔍 REOWN STORE: Raw balance object:', balance.value)
      console.log('🔍 REOWN STORE: balance.value.value:', balance.value.value)
      console.log('🔍 REOWN STORE: balance.value.decimals:', balance.value.decimals)
      
      const formatted = formatUnits(balance.value.value, balance.value.decimals)
      const amount = parseFloat(formatted)
      
      console.log('🔍 REOWN STORE: Formatted units result:', formatted)
      console.log('🔍 REOWN STORE: Parsed amount:', amount)
      
      // Show full precision (up to token decimals, typically 18 for ETH)
      const decimals = balance.value.decimals || 18
      let fullPrecision = amount.toFixed(decimals)
      
      // Remove trailing zeros but keep at least one decimal place
      fullPrecision = fullPrecision.replace(/\.?0+$/, '')
      if (!fullPrecision.includes('.')) {
        fullPrecision += '.0'
      }
      
      console.log('🔍 REOWN STORE: Final formatted balance:', fullPrecision)
      return fullPrecision
    } catch (error) {
      console.error('🔍 REOWN STORE: Error formatting balance:', error)
      return '0.0'
    }
  })

  const balanceSymbol = computed(() => {
    return balance?.value?.symbol || selectedToken.value || 'ETH'
  })

  const networkName = computed(() => {
    return caipNetwork?.value?.name || 'Unknown Network'
  })

  const isOnSupportedChain = computed(() => {
    // Define supported chain IDs for your app
    const supportedChainIds = [1, 8453, 42161, 11155111] // Mainnet, Base, Arbitrum, Sepolia
    return supportedChainIds.includes(chainId?.value)
  })

  // Helper functions
  function getWalletType() {
    if (!connector?.value) return 'unknown'
    
    const name = connector.value.name?.toLowerCase() || ''
    if (name.includes('metamask')) return 'metamask'
    if (name.includes('walletconnect')) return 'walletconnect'
    if (name.includes('coinbase')) return 'coinbase'
    if (name.includes('phantom')) return 'phantom'
    return 'unknown'
  }

  function getChainType() {
    if (!caipNetwork?.value) return 'unknown'
    
    // Check if it's a Solana network
    if (caipNetwork.value.id?.includes('solana')) return 'solana'
    
    // Otherwise assume EVM
    return 'ethereum'
  }

  // Actions
  const connectWallet = async (walletType = null, chain = 'ethereum') => {
    try {
      clearError()
      updateActivity()
      
      if (open) {
        if (walletType) {
          // Open modal with specific view or let user choose
          open({ view: 'Connect' })
        } else {
          // Open generic connect modal
          open()
        }
      } else {
        throw new Error('AppKit not initialized')
      }
      
      return true
    } catch (error) {
      console.error('❌ Connect wallet failed:', error)
      globalError.value = error.message || 'Failed to connect wallet'
      throw error
    }
  }

  const disconnectWallet = async () => {
    try {
      if (connector?.value && disconnect) {
        await disconnect()
      }
      
      globalError.value = null
      console.log('✅ Wallet disconnected successfully')
      return true
    } catch (error) {
      console.error('❌ Disconnect failed:', error)
      globalError.value = error.message || 'Failed to disconnect wallet'
      throw error
    }
  }

  const switchChain = async (targetChainId) => {
    try {
      // Reown handles chain switching through the Networks modal
      if (open) {
        open({ view: 'Networks' })
      } else {
        throw new Error('AppKit not available')
      }
      return true
    } catch (error) {
      console.error('❌ Chain switch failed:', error)
      globalError.value = error.message || 'Failed to switch chain'
      throw error
    }
  }

  const refreshBalance = async () => {
    try {
      if (refetchBalance) {
        await refetchBalance()
        updateActivity()
        console.log('✅ Balance refreshed successfully')
      } else {
        console.warn('⚠️ Balance refetch function not available')
      }
    } catch (error) {
      console.error('❌ Failed to refresh balance:', error)
      globalError.value = error.message || 'Failed to refresh balance'
      throw error
    }
  }

  const initialize = async () => {
    if (isInitialized.value) return true
    
    try {
      // Wait for AppKit to be available
      if (typeof window !== 'undefined') {
        // Give AppKit time to initialize
        await new Promise(resolve => setTimeout(resolve, 100))
        
        // Check if hooks are now available
        if (address && isConnected) {
          isInitialized.value = true
          console.log('✅ Reown wallet store initialized with hooks')
        } else {
          console.warn('⚠️ Reown hooks still not available, store partially initialized')
        }
      }
      
      updateActivity()
      console.log('✅ Reown wallet store initialized')
      return true
    } catch (error) {
      console.error('❌ Initialization failed:', error)
      return false
    }
  }

  // Modal controls  
  const openWalletModal = () => {
    isWalletModalOpen.value = true
    open()
  }

  const closeWalletModal = () => {
    isWalletModalOpen.value = false
  }

  const openAccountModal = () => {
    if (open) {
      open({ view: 'Account' })
    } else {
      console.warn('⚠️ AppKit open function not available')
    }
  }

  const openNetworksModal = () => {
    if (open) {
      open({ view: 'Networks' })
    } else {
      console.warn('⚠️ AppKit open function not available')
    }
  }

  // Utility functions
  const clearError = () => {
    globalError.value = null
  }

  const clearErrors = () => clearError()

  const updateActivity = () => {
    lastActivity.value = Date.now()
  }

  const setSelectedToken = (tokenSymbol) => {
    selectedToken.value = tokenSymbol
  }

  const validateWalletForToken = (tokenSymbol) => {
    const ethereumTokens = ['ETH', 'USDC', 'USDT', 'CIRX']
    if (ethereumTokens.includes(tokenSymbol)) {
      if (!isConnected?.value || getChainType() !== 'ethereum') {
        throw new Error(`${tokenSymbol} requires Ethereum wallet connection`)
      }
      return true
    }
    
    const solanaTokens = ['SOL', 'USDC_SOL']
    if (solanaTokens.includes(tokenSymbol)) {
      if (!isConnected?.value || getChainType() !== 'solana') {
        throw new Error(`${tokenSymbol} requires Solana wallet connection`)
      }
      return true
    }
    
    throw new Error(`Unsupported token: ${tokenSymbol}`)
  }

  const getTokenBalance = (tokenSymbol) => {
    // For now, return the native token balance
    // This can be extended to support ERC20/SPL tokens
    if (tokenSymbol === balanceSymbol.value) {
      return formattedBalance.value
    }
    return '0.0'
  }

  // Return store interface
  return {
    // State
    isConnected: computed(() => isConnected?.value || false),
    isConnecting: computed(() => isConnecting?.value || false),
    isDisconnected: computed(() => isDisconnected?.value || true),
    activeWallet,
    availableWallets,
    currentError,
    shortAddress,
    formattedBalance,
    balanceSymbol,
    networkName,
    isOnSupportedChain,
    selectedToken,
    isWalletModalOpen,
    lastActivity,
    address: computed(() => address?.value || null),
    chainId: computed(() => chainId?.value || null),
    caipNetwork: computed(() => caipNetwork?.value || null),
    
    // Computed
    balance: computed(() => balance?.value || null),
    isBalanceLoading: computed(() => isBalanceLoading?.value || false),
    
    // Actions
    connectWallet,
    disconnectWallet,
    switchChain,
    refreshBalance,
    initialize,
    openWalletModal,
    closeWalletModal,
    openAccountModal,
    openNetworksModal,
    clearError,
    clearErrors,
    updateActivity,
    setSelectedToken,
    validateWalletForToken,
    getTokenBalance
  }
})