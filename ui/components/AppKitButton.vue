<template>
  <button
    @click="handleClick"
    class="px-4 py-2 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-lg hover:from-blue-700 hover:to-purple-700 transition-all duration-200 font-medium shadow-lg"
  >
    <span v-if="!isConnected">Connect Wallet</span>
    <span v-else class="flex items-center gap-2">
      <span class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></span>
      {{ formatAddress(address) }}
    </span>
  </button>
</template>

<script setup>
import { useAppKitWallet } from '~/composables/useAppKitWallet.js'

const { address, isConnected, open } = useAppKitWallet()

const handleClick = () => {
  try {
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
  }
}

const formatAddress = (addr) => {
  if (!addr) return ''
  return `${addr.slice(0, 6)}...${addr.slice(-4)}`
}
</script>