<template>
  <div class="relative">
    <!-- Connect Wallet Button (when disconnected) -->
    <button
      v-if="!isConnected"
      @click="openConnectModal"
      class="px-4 py-2 bg-circular-primary text-gray-900 rounded-lg font-medium hover:bg-circular-primary-hover transition-colors flex items-center gap-2"
      :disabled="isConnecting"
    >
      <!-- Connect Wallet Icon -->
      <svg width="20" height="20" fill="currentColor" viewBox="0 0 640 640">
        <path d="M482.4 221.9C517.7 213.6 544 181.9 544 144C544 99.8 508.2 64 464 64C420.6 64 385.3 98.5 384 141.5L200.2 215.1C185.7 200.8 165.9 192 144 192C99.8 192 64 227.8 64 272C64 316.2 99.8 352 144 352C156.2 352 167.8 349.3 178.1 344.4L323.7 471.8C321.3 479.4 320 487.6 320 496C320 540.2 355.8 576 400 576C444.2 576 480 540.2 480 496C480 468.3 466 443.9 444.6 429.6L482.4 221.9zM220.3 296.2C222.5 289.3 223.8 282 224 274.5L407.8 201C411.4 204.5 415.2 207.7 419.4 210.5L381.6 418.1C376.1 419.4 370.8 421.2 365.8 423.6L220.3 296.2z"/>
      </svg>
      
      <span v-if="isConnecting">Connecting...</span>
      <span v-else>Connect Wallet</span>
    </button>

    <!-- Connected State -->
    <div v-else class="flex items-center gap-3">
      <!-- Network Badge -->
      <div v-if="caipNetwork" class="px-2 py-1 bg-gray-700 rounded-lg text-xs text-gray-300 capitalize">
        {{ networkDisplayName }}
      </div>
      
      <!-- Account Info -->
      <div class="bg-gray-800 border border-gray-600 rounded-lg px-3 py-2 cursor-pointer hover:bg-gray-700 transition-colors" @click="openAccountModal">
        <div class="flex items-center gap-2">
          <!-- Chain/Wallet Icon -->
          <div class="w-4 h-4 flex items-center justify-center text-white">
            <div class="w-2 h-2 bg-green-400 rounded-full"></div>
          </div>
          <span class="text-sm font-medium text-white">{{ shortAddress }}</span>
        </div>
        <div class="text-xs text-gray-400">
          {{ balanceDisplay }}
        </div>
      </div>
      
      <!-- Network Switch Button (if wrong network) -->
      <button
        v-if="!isCorrectNetwork"
        @click="openNetworksModal"
        class="px-3 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium text-sm transition-colors"
      >
        Switch Network
      </button>
    </div>

    <!-- Error Display -->
    <div
      v-if="error"
      class="absolute top-full left-0 right-0 mt-2 p-3 bg-red-900/90 border border-red-700 rounded-lg text-red-200 text-sm z-10"
    >
      {{ error }}
      <button
        @click="clearError"
        class="ml-2 text-red-400 hover:text-red-200"
      >
        ✕
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, onMounted } from 'vue'

// Use the Reown wallet store which handles initialization
import { useReownWalletStore } from '~/stores/reownWallet'

// Local state
const error = ref(null)

// Use the store instead of direct hooks
const reownStore = useReownWalletStore()

// Initialize store on component mount
onMounted(() => {
  try {
    reownStore.initialize()
  } catch (err) {
    console.warn('Store initialization deferred:', err.message)
  }
})

// Computed properties using store
const isConnected = computed(() => reownStore.isConnected)
const isConnecting = computed(() => reownStore.isConnecting)
const address = computed(() => reownStore.address)
const chainId = computed(() => reownStore.chainId)
const caipNetwork = computed(() => reownStore.caipNetwork)
const balance = computed(() => reownStore.balance)

const shortAddress = computed(() => {
  const addr = address.value
  if (!addr) return ''
  return `${addr.slice(0, 6)}...${addr.slice(-4)}`
})

const networkDisplayName = computed(() => {
  const network = caipNetwork.value
  if (!network) return 'Unknown'
  return network.name || 'Unknown Network'
})

const balanceDisplay = computed(() => {
  const bal = balance.value
  if (!bal) return '0.0 ETH'
  return reownStore.formattedBalance || '0.0 ETH'
})

const isCorrectNetwork = computed(() => {
  const chain = chainId.value
  if (!chain) return false
  // Define supported chain IDs
  const supportedChainIds = [1, 8453, 42161, 11155111] // Mainnet, Base, Arbitrum, Sepolia
  return supportedChainIds.includes(chain)
})

// Modal functions using store
const openConnectModal = () => {
  clearError()
  reownStore.connectWallet()
}

const openAccountModal = () => {
  reownStore.openAccountModal()
}

const openNetworksModal = () => {
  reownStore.openNetworksModal()
}

// Error handling
const clearError = () => {
  error.value = null
}

// Watch for connection errors
import { watchEffect } from 'vue'

watchEffect(() => {
  // Clear error when successfully connected
  if (isConnected.value) {
    error.value = null
  }
})
</script>

<style scoped>
/* Add any custom styles if needed */
</style>