<template>
  <div 
    v-if="quote" 
    class="bg-transparent border border-cyan-500/20 rounded-xl p-4 mb-6 hover:border-cyan-500/40 transition-all duration-300" 
    :class="isRefreshing ? 'border-cyan-400/40' : ''"
  >
    <!-- Exchange Rate -->
    <div class="flex justify-between items-center mb-2">
      <span class="text-sm text-gray-400">Exchange Rate</span>
      <span class="text-sm font-medium text-white" :class="isLoading ? 'opacity-60' : ''">
        1 {{ inputToken }} = {{ quote.rate }} CIRX
      </span>
    </div>
    
    <!-- Price Update Status -->
    <div class="flex justify-between items-center mb-2">
      <span class="text-xs text-gray-500" :class="isRefreshing ? 'text-cyan-400' : ''">
        {{ refreshStatusText }}
      </span>
    </div>
    
    <!-- CIRX Price -->
    <div class="flex justify-between items-center mb-2">
      <span class="text-sm text-gray-400">CIRX Price</span>
      <span class="text-sm font-medium text-white">
        1 CIRX = {{ quote.inverseRate }} {{ inputToken }}
      </span>
    </div>
    
    <!-- Platform Fee -->
    <div class="flex justify-between items-center mb-2">
      <span class="text-sm text-gray-400">Platform Fee</span>
      <span class="text-sm font-medium text-white">{{ quote.fee }}%</span>
    </div>
    
    <!-- Network Fee -->
    <div class="flex justify-between items-center mb-2">
      <span class="text-sm text-gray-400">Est. Network Fee</span>
      <span class="text-sm font-medium text-white">
        ~{{ networkFee.eth }} ETH (~${{ networkFee.usd }})
      </span>
    </div>
    
    <!-- OTC Discount (conditional) -->
    <div v-if="isOtc && quote.discount > 0" class="flex justify-between items-center mb-2">
      <span class="text-sm text-gray-400">OTC Discount</span>
      <span class="text-sm font-medium text-circular-primary">{{ quote.discount }}%</span>
    </div>
    
    <!-- Vesting Period (conditional) -->
    <div v-if="isOtc" class="flex justify-between items-center">
      <span class="text-sm text-gray-400">Vesting Period</span>
      <span class="text-sm font-medium text-white">
        {{ vestingPeriod.months }} months ({{ vestingPeriod.type }})
      </span>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  quote: {
    type: Object,
    default: null
  },
  inputToken: {
    type: String,
    required: true
  },
  networkFee: {
    type: Object,
    required: true,
    default: () => ({ eth: '0.0000', usd: '0.00' })
  },
  isOtc: {
    type: Boolean,
    default: false
  },
  isRefreshing: {
    type: Boolean,
    default: false
  },
  isLoading: {
    type: Boolean,
    default: false
  },
  priceCountdown: {
    type: Number,
    default: 30
  },
  vestingPeriod: {
    type: Object,
    default: () => ({ months: 6, type: 'linear' })
  }
})

const refreshStatusText = computed(() => {
  if (props.isRefreshing) {
    return 'Updating prices...'
  }
  return `Next price update in ${props.priceCountdown}s`
})
</script>

<style scoped>
/* Component-specific styles if needed */
</style>