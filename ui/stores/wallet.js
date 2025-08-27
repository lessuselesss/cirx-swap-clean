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
  
  // Update functions to be called by AppKit plugin
  const updateAccount = (accountInfo) => {
    isConnected.value = accountInfo?.isConnected || false
    address.value = accountInfo?.address || null
  }
  
  const updateNetwork = (networkInfo) => {
    chainId.value = networkInfo?.chainId || null
  }

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
    isConnected: readonly(isConnected),
    activeWallet: readonly(activeWallet),
    activeChain: readonly(activeChain),
    ethereumWallet: readonly(ethereumWallet),
    // Expose update functions for AppKit plugin
    updateAccount,
    updateNetwork
  }
})