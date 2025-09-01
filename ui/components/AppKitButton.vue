<template>
  <button
    @click="handleClick"
    :disabled="isConnecting"
    :class="[
      'px-4 py-2 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-lg',
      'hover:from-blue-700 hover:to-purple-700 transition-all duration-200 font-medium shadow-lg',
      'disabled:opacity-50 disabled:cursor-not-allowed',
      'focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2'
    ]"
  >
    <span v-if="isConnecting" class="flex items-center gap-2">
      <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
      Connecting...
    </span>
    <span v-else-if="!isConnected">Connect Wallet</span>
    <span v-else class="flex items-center gap-2">
      <span class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></span>
      {{ formatAddress(address) }}
    </span>
  </button>
</template>

<script setup>
import { ref } from 'vue'
import { useAppKitWallet } from '~/composables/useAppKitWallet.js'

// Use defensive destructuring with default values to prevent undefined errors
const walletComposable = useAppKitWallet()
const address = walletComposable?.address || ref(null)
const isConnected = walletComposable?.isConnected || ref(false)
const open = walletComposable?.open || (() => console.warn('AppKit open function not available'))
const syncWithMetaMask = walletComposable?.syncWithMetaMask || (() => console.warn('MetaMask sync not available'))

const isConnecting = ref(false)

// Debug: Log wallet state to diagnose issues
console.log('🔍 AppKitButton - Wallet composable state:', {
  hasComposable: !!walletComposable,
  hasAddress: !!address,
  hasIsConnected: !!isConnected,
  hasOpen: !!open,
  addressValue: address?.value,
  isConnectedValue: isConnected?.value,
  timestamp: new Date().toISOString()
})

// Throttle connection attempts to prevent Lit component update scheduling issues
let lastConnectionAttempt = 0
const CONNECTION_THROTTLE = 1000 // 1 second

const handleClick = async () => {
  const now = Date.now()
  if (now - lastConnectionAttempt < CONNECTION_THROTTLE) {
    console.log('🔗 Connection attempt throttled')
    return
  }
  lastConnectionAttempt = now

  // First check if we're already connected (but maybe state is out of sync)
  if (isConnected.value) {
    console.log('🔗 Already connected to:', formatAddress(address.value))
    return
  }

  // Check if MetaMask is connected but AppKit doesn't know about it
  if (window.ethereum && window.ethereum.selectedAddress && !isConnected.value) {
    console.log('🔄 MetaMask connected but AppKit not synced - attempting sync first...')
    try {
      await syncWithMetaMask()
      // Wait a moment for the sync to take effect
      await new Promise(resolve => setTimeout(resolve, 500))
      
      // Check again if sync worked
      if (isConnected.value) {
        console.log('✅ Sync successful - wallet now connected!')
        return
      }
    } catch (error) {
      console.warn('⚠️ MetaMask sync failed, proceeding with modal:', error)
    }
  }

  try {
    isConnecting.value = true
    console.log('🔗 Opening AppKit modal (preventing Lit update scheduling issues)...')
    
    // Add small delay to prevent immediate state changes that cause Lit scheduling issues
    await new Promise(resolve => setTimeout(resolve, 100))
    
    // Use global AppKit instance directly for reliable modal opening
    if (window.$appKit && typeof window.$appKit.open === 'function') {
      window.$appKit.open()
    } else if (typeof open === 'function') {
      // Fallback to hook-based method
      open()
    } else {
      console.warn('AppKit modal not available')
    }
  } catch (error) {
    console.error('Error opening AppKit modal:', error)
  } finally {
    // Reset connecting state after a delay to prevent rapid state changes
    setTimeout(() => {
      isConnecting.value = false
    }, 1500)
  }
}

const formatAddress = (addr) => {
  if (!addr || typeof addr !== 'string') return ''
  if (addr.length < 10) return addr
  return `${addr.slice(0, 6)}...${addr.slice(-4)}`
}

// Expose for parent components if needed
defineExpose({
  handleClick,
  isConnected,
  address,
  isConnecting
})
</script>