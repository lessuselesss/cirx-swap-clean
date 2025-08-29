<template>
  <!-- Show custom button when disconnected, w3m-button when connected -->
  <div>
    <!-- Custom styled button for disconnected state -->
    <button
      v-if="!isConnected"
      @click="handleClick"
      :class="[
        'group flex items-center gap-2 px-4 py-2 rounded-xl font-medium transition-all duration-300 border relative gradient-border',
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
      <span class="whitespace-nowrap wallet-text">
        Connect
      </span>
    </button>

    <!-- AppKit's native button when connected - temporarily commented out -->
    <!-- <w3m-button v-if="isConnected" /> -->
    <button v-if="isConnected" @click="handleClick" class="px-4 py-2 bg-blue-500 text-white rounded">
      {{ buttonText }}
    </button>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useAppKit, useAppKitAccount } from '@reown/appkit/vue'

// Use AppKit composables directly - no global window references
const { open } = useAppKit()
const { address, isConnected } = useAppKitAccount()

// Format address for display when connected
const buttonText = computed(() => {
  if (isConnected && address) {
    return `${address.slice(0, 6)}...${address.slice(-4)}`
  }
  return 'Connect'
})

// Click handler - use AppKit composable
const handleClick = () => {
  open()
}
</script>

<style scoped>
/* Wallet text - white by default, animated gradient on hover */
.wallet-text {
  color: #01DA9D;
  transition: all 0.3s ease;
}

.group:hover .wallet-text {
  background: linear-gradient(45deg, #00ff88, #00d9ff, #8b5cf6, #a855f7, #00ff88);
  background-size: 400% 400%;
  background-clip: text;
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  animation: text-gradient-cycle 3s ease infinite;
}

@keyframes text-gradient-cycle {
  0% {
    background-position: 0% 50%;
  }
  50% {
    background-position: 100% 50%;
  }
  100% {
    background-position: 0% 50%;
  }
}
</style>