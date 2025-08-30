<template>
  <div v-if="showStatus" class="circular-chain-status">
    <!-- Detecting State -->
    <div v-if="guidance.status === 'detecting'" class="status-card detecting">
      <div class="flex items-center gap-3">
        <svg class="animate-spin w-5 h-5 text-blue-400" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <span class="text-blue-400">{{ guidance.message }}</span>
      </div>
    </div>

    <!-- Connected State -->
    <div v-else-if="guidance.status === 'connected'" class="status-card connected">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="w-5 h-5 rounded-full bg-green-400 flex items-center justify-center">
            <svg class="w-3 h-3 text-green-900" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
            </svg>
          </div>
          <div>
            <span class="text-green-400 font-medium">Circular Chain Connected</span>
            <div class="text-sm text-green-300">{{ formatCirxBalance }} CIRX available</div>
          </div>
        </div>
        <button 
          @click="refreshBalance" 
          :disabled="isLoadingBalance"
          class="text-green-300 hover:text-green-200 transition-colors"
          title="Refresh balance"
        >
          <svg :class="['w-4 h-4', isLoadingBalance ? 'animate-spin' : '']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0V9a8 8 0 1115.356 2M15 15v5h-.582M4.356 15A8.001 8.001 0 0015.418 15m0 0V15a8 8 0 10-15.356-2"/>
          </svg>
        </button>
      </div>
      <div class="mt-2 text-xs text-gray-400">
        {{ formatAddress(cirxAddress) }}
      </div>
    </div>

    <!-- Saturn Wallet No Circular Chain -->
    <div v-else-if="guidance.status === 'saturn-no-circular'" class="status-card warning">
      <div class="flex items-center gap-3">
        <div class="w-5 h-5 rounded-full bg-yellow-400 flex items-center justify-center">
          <svg class="w-3 h-3 text-yellow-900" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
          </svg>
        </div>
        <div class="flex-1">
          <span class="text-yellow-400 font-medium">Saturn Wallet Detected</span>
          <div class="text-sm text-yellow-300">{{ guidance.message }}</div>
        </div>
        <button 
          @click="$emit('help-needed')"
          class="text-yellow-300 hover:text-yellow-200 transition-colors text-sm"
        >
          Help
        </button>
      </div>
    </div>

    <!-- No Circular Chain -->
    <div v-else-if="guidance.status === 'no-circular'" class="status-card info">
      <div class="flex items-center gap-3">
        <div class="w-5 h-5 rounded-full bg-blue-400 flex items-center justify-center">
          <svg class="w-3 h-3 text-blue-900" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
          </svg>
        </div>
        <span class="text-blue-400">Ready for Circular address input</span>
      </div>
    </div>

    <!-- Error State -->
    <div v-else-if="guidance.status === 'error'" class="status-card error">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="w-5 h-5 rounded-full bg-red-400 flex items-center justify-center">
            <svg class="w-3 h-3 text-red-900" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
          </div>
          <span class="text-red-400">{{ guidance.message }}</span>
        </div>
        <button 
          @click="retryConnection"
          class="text-red-300 hover:text-red-200 transition-colors text-sm"
        >
          Retry
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useCircularChain } from '~/composables/useRPreFlightValidation.js'

// Props
defineProps({
  showStatus: {
    type: Boolean,
    default: true
  },
  compact: {
    type: Boolean,
    default: false
  }
})

// Emits
defineEmits(['help-needed'])

// Use Circular chain composable
const {
  cirxAddress,
  isLoadingBalance,
  formatCirxBalance,
  getUxGuidance,
  detectCircularChain,
  fetchCirxBalance,
  addCircularChain
} = useCircularChain()

// Computed
const guidance = computed(() => getUxGuidance.value)

// Methods
const formatAddress = (address) => {
  if (!address) return ''
  return `${address.slice(0, 6)}...${address.slice(-4)}`
}

const refreshBalance = async () => {
  await fetchCirxBalance()
}

const retryConnection = async () => {
  await detectCircularChain()
}

</script>

<style scoped>
.circular-chain-status {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.status-card {
  padding: 12px 16px;
  border-radius: 12px;
  border: 1px solid;
  transition: all 0.3s ease;
}

.status-card.detecting {
  background: rgba(59, 130, 246, 0.1);
  border-color: rgba(59, 130, 246, 0.3);
}

.status-card.connected {
  background: rgba(34, 197, 94, 0.1);
  border-color: rgba(34, 197, 94, 0.3);
}

.status-card.warning {
  background: rgba(251, 191, 36, 0.1);
  border-color: rgba(251, 191, 36, 0.3);
}

.status-card.info {
  background: rgba(59, 130, 246, 0.1);
  border-color: rgba(59, 130, 246, 0.3);
}

.status-card.error {
  background: rgba(239, 68, 68, 0.1);
  border-color: rgba(239, 68, 68, 0.3);
}

.status-card:hover {
  border-opacity: 0.5;
}
</style>