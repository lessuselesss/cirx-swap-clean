/**
 * Minimal wallet store using Reown AppKit
 * Provides compatibility layer for useSwapService
 */
import { defineStore } from 'pinia'
import { computed, readonly, ref } from 'vue'

export const useWalletStore = defineStore('wallet', () => {
  // State managed by AppKit plugin subscriptions
  const isConnected = ref(false)
  const address = ref(null)
  const chainId = ref(null)
  const balances = ref({}) // Token balances from AppKit
  const selectedToken = ref('ETH') // Currently selected input token
  
  // Update functions to be called by AppKit plugin
  const updateAccount = (accountInfo) => {
    isConnected.value = accountInfo?.isConnected || false
    address.value = accountInfo?.address || null
  }
  
  const updateNetwork = (networkInfo) => {
    chainId.value = networkInfo?.chainId || null
  }

  const updateBalances = (balanceData) => {
    balances.value = balanceData || {}
  }

  // Token selection management
  const selectInputToken = (token) => {
    selectedToken.value = token
  }

  // Get balance for currently selected token
  const selectedTokenBalance = computed(() => {
    if (!isConnected.value) return '0.000000000000000000'
    
    const tokenBalance = balances.value[selectedToken.value]
    if (!tokenBalance) return '0.000000000000000000'
    
    // Format balance to 18 decimals for consistency
    const amount = parseFloat(tokenBalance.formatted || '0')
    return isNaN(amount) ? '0.000000000000000000' : amount.toFixed(18)
  })

  // Computed properties for compatibility
  const activeWallet = computed(() => ({
    address: address.value,
    isOnSupportedChain: chainId.value === 1 // Ethereum mainnet
  }))

  const activeChain = computed(() => {
    return chainId.value === 1 ? 'ethereum' : 'unsupported'
  })

  const ethereumWallet = computed(() => ({
    publicClient: null, // Will be provided by AppKit when needed
    walletClient: null
  }))

  return {
    // Connection state
    isConnected: readonly(isConnected),
    address: readonly(address),
    activeWallet: readonly(activeWallet),
    activeChain: readonly(activeChain),
    ethereumWallet: readonly(ethereumWallet),
    
    // Token selection and balances
    selectedToken: readonly(selectedToken),
    selectedTokenBalance: readonly(selectedTokenBalance),
    balances: readonly(balances),
    
    // Actions
    selectInputToken,
    
    // Update functions for AppKit plugin
    updateAccount,
    updateNetwork,
    updateBalances
  }
})