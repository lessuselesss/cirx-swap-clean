import { computed } from 'vue'
import { mainnet, sepolia } from 'viem/chains'
import { useAppKitNetwork, useAppKitAccount } from '@reown/appkit/vue'
import { useAppKitWallet } from './useAppKitWallet.js'

/**
 * AppKit-compatible Viem clients composable
 * Uses centralized provider from useAppKitWallet to avoid duplication
 * Provides publicClient and walletClient through AppKit provider
 */
export function useAppKitClients() {
  const { isConnected, chainId } = useAppKitAccount()
  const { chainId: appKitChainId } = useAppKitNetwork()
  const { publicClient: centralizedPublicClient, walletClient: centralizedWalletClient } = useAppKitWallet()
  
  // Use centralized clients from useAppKitWallet to avoid provider duplication

  // Chain configuration mapping
  const chainMapping = {
    1: mainnet,
    11155111: sepolia,
  }

  // Get current chain from AppKit
  const currentChain = computed(() => {
    const id = chainId?.value || appKitChainId?.value || 1
    return chainMapping[id] || mainnet
  })

  // Helper to check if wallet client is available
  const isWalletClientReady = computed(() => {
    return !!(centralizedWalletClient.value && isConnected?.value)
  })

  // Helper to check if public client is available
  const isPublicClientReady = computed(() => {
    return !!centralizedPublicClient.value
  })

  // Chain information
  const chainInfo = computed(() => {
    const chain = currentChain.value
    return {
      id: chain.id,
      name: chain.name,
      currency: chain.nativeCurrency,
      blockExplorer: chain.blockExplorers?.default?.url
    }
  })

  return {
    // Viem clients (delegated to centralized composable)
    publicClient: centralizedPublicClient,
    walletClient: centralizedWalletClient,
    
    // Chain information
    currentChain,
    chainInfo,
    
    // Status checks
    isWalletClientReady,
    isPublicClientReady
  }
}