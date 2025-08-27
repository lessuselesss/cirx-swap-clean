<template>
  <!-- Show custom button when disconnected, w3m-button when connected -->
  <div>
    <!-- Custom styled button for disconnected state -->
    <button
      v-if="!isConnected"
      @click="handleClick"
      :class="[
        'flex items-center gap-2 px-4 py-2 rounded-xl font-medium transition-all duration-300 border relative gradient-border',
        'border-transparent shadow-lg hover:shadow-xl backdrop-blur-sm cursor-pointer'
      ]"
      style="background-color: transparent; color: #01DA9D;"
    >
      <!-- Wallet Icon -->
      <div class="w-5 h-5">
        <svg viewBox="0 0 24 24" fill="currentColor" class="w-full h-full">
          <path d="M21 18v1c0 1.1-.9 2-2 2H5c-1.1 0-2-.9-2-2V5c0-1.1.9-2 2-2h14c1.1 0 2 .9 2 2v1h-9c-1.1 0-2 .9-2 2v8c0 1.1.9 2 2 2h9zm-9-2h10V8H12v8zm4-2.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/>
        </svg>
      </div>

      <!-- Button Text -->
      <span class="whitespace-nowrap">
        Connect Wallet
      </span>
    </button>

    <!-- AppKit's native button when connected -->
    <w3m-button v-if="isConnected" />
  </div>
</template>

<script setup>
import { computed, ref, onMounted } from 'vue'

// Track connection state
const isConnected = ref(false)
const address = ref(null)

// Update state from AppKit when component mounts
onMounted(() => {
  if (process.client && window?.$appKit) {
    // Subscribe to AppKit state changes
    window.$appKit.subscribeAccount((account) => {
      isConnected.value = account?.isConnected || false
      address.value = account?.address || null
    })
    
    // Get initial state if available
    try {
      const state = window.$appKit.getState?.()
      if (state?.account) {
        isConnected.value = state.account.isConnected || false
        address.value = state.account.address || null
      }
    } catch (error) {
      // Ignore if getState doesn't exist
    }
  }
})

// Format address for display when connected
const buttonText = computed(() => {
  if (isConnected.value && address.value) {
    return `${address.value.slice(0, 6)}...${address.value.slice(-4)}`
  }
  return 'Connect Wallet'
})

// Click handler - just open AppKit modal
const handleClick = () => {
  if (process.client && window?.$appKit) {
    window.$appKit.open()
  }
}
</script>