/**
 * useWallet - Main wallet composable that aggregates multi-wallet functionality
 * Provides a unified interface for Ethereum and Solana wallets
 */

import { computed, ref, watch, onUnmounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useWalletStore } from '~/stores/wallet'

export const useWallet = () => {
  // Use the wallet store
  const walletStore = useWalletStore()
  const { isConnected: isConnectedRef, activeWallet: activeWalletRef } = storeToRefs(walletStore)
  
  // Live balance state
  const liveBalance = ref(null)
  const isLoadingBalance = ref(false)
  let balanceInterval = null
  
  // Mock balances for development (fallback when live data unavailable)
  const mockBalances = {
    ETH: '2.5234',
    USDC: '1500.00',
    USDT: '750.50',
    SOL: '45.25',
    USDC_SOL: '850.75',
    CIRX: '0.00'
  }
  
  // Computed properties from store
  const isConnected = computed(() => isConnectedRef.value)
  const account = computed(() => activeWalletRef.value?.address || null)
  const connectedWallet = computed(() => activeWalletRef.value?.type || null)
  const shortAddress = computed(() => {
    if (!account.value) return ''
    return `${account.value.slice(0, 6)}...${account.value.slice(-4)}`
  })
  
  // Live balance fetching for Phantom wallet
  const fetchLiveBalance = async () => {
    if (!isConnected.value || connectedWallet.value !== 'phantom') return
    
    try {
      isLoadingBalance.value = true
      
      // Check if Phantom wallet is available and connected
      if (typeof window !== 'undefined' && window.solana?.isPhantom && window.solana.isConnected) {
        // Create connection to Solana RPC
        const { Connection, PublicKey, LAMPORTS_PER_SOL } = await import('@solana/web3.js')
        
        // Use Phantom's connection or fallback to public RPC
        const connection = new Connection('https://api.mainnet-beta.solana.com')
        const publicKey = new PublicKey(account.value)
        
        // Get balance in lamports and convert to SOL
        const balanceInLamports = await connection.getBalance(publicKey)
        const balanceInSol = balanceInLamports / LAMPORTS_PER_SOL
        
        liveBalance.value = balanceInSol.toFixed(4)
        console.log(`✅ Live SOL balance: ${balanceInSol.toFixed(4)}`)
      }
    } catch (error) {
      console.warn('Failed to fetch live SOL balance:', error)
      liveBalance.value = null
    } finally {
      isLoadingBalance.value = false
    }
  }
  
  // Watch for wallet connection changes
  watch([isConnected, connectedWallet], async ([connected, wallet]) => {
    // Clear existing interval
    if (balanceInterval) {
      clearInterval(balanceInterval)
      balanceInterval = null
    }
    
    // Reset balance when disconnected
    if (!connected) {
      liveBalance.value = null
      return
    }
    
    // Fetch balance and start interval for Phantom wallet
    if (wallet === 'phantom') {
      await fetchLiveBalance()
      
      // Set up periodic balance updates every 30 seconds
      balanceInterval = setInterval(async () => {
        if (isConnected.value && connectedWallet.value === 'phantom') {
          await fetchLiveBalance()
        }
      }, 30000)
    } else {
      // Clear live balance for other wallets
      liveBalance.value = null
    }
  }, { immediate: true })
  
  // Cleanup on unmount
  onUnmounted(() => {
    if (balanceInterval) {
      clearInterval(balanceInterval)
    }
  })
  
  // Balance with live data priority
  const balance = computed(() => {
    if (!isConnected.value) return '0.0'
    
    // For Phantom wallet, use live balance if available
    if (connectedWallet.value === 'phantom') {
      return liveBalance.value || mockBalances.SOL
    } else {
      return mockBalances.ETH
    }
  })
  
  // Get token balance
  const getTokenBalance = (token) => {
    if (!isConnected.value) return '0.0'
    
    // Use MetaMask balance if available
    if (connectedWallet.value === 'metamask' && walletStore.metaMaskWallet) {
      return walletStore.metaMaskWallet.getTokenBalance(token)
    }
    
    // For Phantom wallet, handle SOL specially with live balance
    if (connectedWallet.value === 'phantom') {
      if (token === 'SOL') {
        return liveBalance.value || mockBalances.SOL
      }
      // For other tokens, Phantom doesn't support them yet
      return '0.0'
    }
    
    // Fallback to mock balances
    return mockBalances[token] || '0.0'
  }
  
  // Execute swap (mock implementation)
  const executeSwap = async (fromToken, amount, toToken, isOTC = false) => {
    try {
      // Use MetaMask swap if available
      if (connectedWallet.value === 'metamask' && walletStore.metaMaskWallet) {
        const result = await walletStore.metaMaskWallet.executeSwap(fromToken, amount, toToken, isOTC)
        return {
          success: true,
          hash: result.hash,
          amount: amount,
          toToken: toToken,
          isOTC: isOTC
        }
      }
      
      // Fallback to mock implementation
      await new Promise(resolve => setTimeout(resolve, 2000))
      
      return {
        success: true,
        hash: '0x' + Math.random().toString(16).substr(2, 64),
        amount: amount,
        toToken: toToken,
        isOTC: isOTC
      }
    } catch (error) {
      console.error('Swap execution failed:', error)
      return {
        success: false,
        error: error.message
      }
    }
  }
  
  return {
    // State
    isConnected,
    account,
    balance,
    connectedWallet,
    shortAddress,
    isLoadingBalance,
    
    // Methods
    getTokenBalance,
    executeSwap,
    fetchLiveBalance,
    
    // Store methods
    connectWallet: walletStore.connectWallet,
    disconnectWallet: walletStore.disconnectWallet,
    clearError: walletStore.clearError
  }
}