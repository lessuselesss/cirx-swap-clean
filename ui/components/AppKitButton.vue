<template>
  <button
    @click="handleClick"
    :disabled="isConnecting"
    :class="[
      'px-4 py-2 bg-transparent gradient-border text-white rounded-lg',
      'font-medium transition-all duration-200',
      'disabled:opacity-50 disabled:cursor-not-allowed',
      'focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2'
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
import { ref, computed, watch } from 'vue'
import { useAppKitWallet } from '~/composables/useAppKitWallet.js'
import { useAppKit } from '@reown/appkit/vue'

// Get AppKit modal control
const { open: openModal } = useAppKit()

// Get wallet composable with reactive state
const walletComposable = useAppKitWallet()

// Use reactive refs from wallet composable - these are properly reactive for Vue templates
const address = walletComposable.address
const isConnected = walletComposable.isConnected

// Watch for reactive state changes to debug
watch([isConnected, address], 
  ([connected, addr]) => {
    console.log('🔄 AppKitButton reactive state changed:', {
      connected,
      address: addr?.slice(0, 6) + '...' + addr?.slice(-4),
      timestamp: new Date().toISOString()
    })
  },
  { immediate: true }
)

// Debug logging with reactive state details
console.log('🔍 AppKit button initialization with reactive state:', {
  hasOpenModal: !!openModal,
  hasWalletComposable: !!walletComposable,
  addressRef: address.value?.slice(0, 6) + '...' + address.value?.slice(-4),
  connectedRef: isConnected.value,
  addressIsRef: !!address._rawValue !== undefined,
  connectedIsRef: !!isConnected._rawValue !== undefined,
  timestamp: new Date().toISOString()
})

const isConnecting = ref(false)

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

  try {
    isConnecting.value = true
    console.log('🔗 Opening AppKit modal...')
    
    // Use the openModal function from useAppKit - let AppKit handle everything
    if (openModal && typeof openModal === 'function') {
      console.log('✅ Calling openModal()')
      await openModal()
      console.log('✅ Modal opened successfully')
    } else {
      console.warn('⚠️ openModal function not available')
    }
    
  } catch (error) {
    console.error('❌ Error opening AppKit modal:', error)
  } finally {
    // Reset connecting state after a delay
    setTimeout(() => {
      isConnecting.value = false
    }, 500)
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
<style scoped>
/* Gradient border effect - matches the swap form */
.gradient-border {
  position: relative;
  border: 1px solid rgba(55, 65, 81, 0.5);
  border-radius: 0.5rem;
  transition: all 0.3s ease;
}

.gradient-border:hover {
  border: 1px solid #00ff88;
  animation: border-color-cycle 24s ease infinite;
}

@keyframes border-color-cycle {
  0% { border-color: #00ff88; }
  25% { border-color: #00d9ff; }
  50% { border-color: #8b5cf6; }
  75% { border-color: #a855f7; }
  100% { border-color: #00ff88; }
}
</style>
