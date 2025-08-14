<template>
  <div v-if="quote" class="mb-6 p-4 bg-gray-800/30 rounded-xl border border-gray-700/50">
    <h3 class="text-sm font-medium text-white mb-3">Transaction Details</h3>
    
    <div class="space-y-2 text-sm">
      <!-- Exchange Rate -->
      <div class="flex justify-between">
        <span class="text-gray-400">Exchange Rate</span>
        <span class="text-white">1 {{ inputToken }} = {{ formatRate() }} CIRX</span>
      </div>
      
      <!-- Inverse Rate -->
      <div class="flex justify-between">
        <span class="text-gray-400">CIRX Price</span>
        <span class="text-white">1 CIRX = {{ formatInverseRate() }} {{ inputToken }}</span>
      </div>
      
      <!-- Fee -->
      <div class="flex justify-between">
        <span class="text-gray-400">Platform Fee ({{ quote.feeRate || '0.3' }}%)</span>
        <span class="text-white">{{ formatFee() }} {{ inputToken }}</span>
      </div>
      
      <!-- OTC Discount -->
      <div v-if="activeTab === 'otc' && quote.discount > 0" class="flex justify-between">
        <span class="text-green-400">OTC Bonus ({{ quote.discount }}%)</span>
        <span class="text-green-400">+{{ formatBonus() }} CIRX</span>
      </div>
      
      <!-- Minimum Received -->
      <div class="flex justify-between">
        <span class="text-gray-400">Minimum Received</span>
        <span class="text-white">{{ quote.minimumReceived || quote.cirxAmount }} CIRX</span>
      </div>
      
      <!-- Slippage -->
      <div class="flex justify-between">
        <span class="text-gray-400">Max Slippage</span>
        <span class="text-white">0.5%</span>
      </div>
      
      <!-- Vesting Info for OTC -->
      <div v-if="activeTab === 'otc'" class="pt-2 border-t border-gray-700/50">
        <div class="flex justify-between">
          <span class="text-yellow-400">Vesting Period</span>
          <span class="text-yellow-400">6 months (linear)</span>
        </div>
        <div class="text-xs text-gray-500 mt-1">
          Tokens will vest linearly over 6 months and can be claimed at any time
        </div>
      </div>
    </div>
    
    <!-- Price Impact Warning -->
    <div v-if="quote.priceImpact > 2" class="mt-3 p-2 bg-yellow-500/10 border border-yellow-500/30 rounded-lg">
      <div class="flex items-center gap-2">
        <svg class="w-4 h-4 text-yellow-400" viewBox="0 0 24 24" fill="none">
          <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" stroke="currentColor" stroke-width="2"/>
          <line x1="12" y1="9" x2="12" y2="13" stroke="currentColor" stroke-width="2"/>
          <circle cx="12" cy="17" r="1" fill="currentColor"/>
        </svg>
        <span class="text-sm text-yellow-400">High price impact ({{ quote.priceImpact }}%)</span>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  quote: {
    type: Object,
    required: true
  },
  activeTab: {
    type: String,
    required: true
  },
  inputToken: {
    type: String,
    required: true
  },
  inputAmount: {
    type: String,
    required: true
  }
})

// Format exchange rate with NaN protection  
// Shows how many CIRX tokens you get for 1 input token (e.g., "1 ETH = 16,667 CIRX")
const formatRate = () => {
  if (!props.quote.tokenPrice || !props.quote.cirxPrice || 
      typeof props.quote.tokenPrice !== 'number' || typeof props.quote.cirxPrice !== 'number' ||
      isNaN(props.quote.tokenPrice) || isNaN(props.quote.cirxPrice) ||
      props.quote.tokenPrice <= 0 || props.quote.cirxPrice <= 0) {
    return '0'
  }
  
  // Calculate actual exchange rate: inputTokenPrice / cirxPrice
  // If ETH = $2500 and CIRX = $0.15, then 1 ETH = 16,667 CIRX
  const exchangeRate = props.quote.tokenPrice / props.quote.cirxPrice
  
  if (!isFinite(exchangeRate) || exchangeRate <= 0) {
    console.warn('Invalid exchange rate calculation:', { 
      tokenPrice: props.quote.tokenPrice, 
      cirxPrice: props.quote.cirxPrice, 
      result: exchangeRate 
    })
    return '0'
  }
  
  return new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2
  }).format(exchangeRate)
}

// Format inverse rate (1 CIRX = X token) using actual CIRX price with NaN protection
const formatInverseRate = () => {
  // Comprehensive validation to prevent NaN
  if (!props.quote.tokenPrice || !props.quote.cirxPrice || 
      typeof props.quote.tokenPrice !== 'number' || typeof props.quote.cirxPrice !== 'number' ||
      isNaN(props.quote.tokenPrice) || isNaN(props.quote.cirxPrice) ||
      props.quote.tokenPrice <= 0 || props.quote.cirxPrice <= 0) {
    return '0'
  }
  
  // Safe division calculation
  const inverseRate = props.quote.cirxPrice / props.quote.tokenPrice
  
  // Validate result before formatting
  if (!isFinite(inverseRate) || inverseRate <= 0) {
    console.warn('Invalid inverse rate calculation:', { 
      cirxPrice: props.quote.cirxPrice, 
      tokenPrice: props.quote.tokenPrice, 
      result: inverseRate 
    })
    return '0'
  }
  
  return new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 6,
    maximumFractionDigits: 8
  }).format(inverseRate)
}

// Format fee amount with NaN protection
const formatFee = () => {
  if (!props.quote.feeAmount || typeof props.quote.feeAmount !== 'number' || 
      isNaN(props.quote.feeAmount) || props.quote.feeAmount < 0) {
    return '0'
  }
  
  const fee = parseFloat(props.quote.feeAmount)
  if (!isFinite(fee)) return '0'
  
  return fee.toFixed(6).replace(/\.?0+$/, '') || '0'
}

// Format bonus amount for OTC with comprehensive NaN protection
const formatBonus = () => {
  // Validate all required values
  if (!props.quote.discount || !props.inputAmount || !props.quote.tokenPrice ||
      typeof props.quote.discount !== 'number' || typeof props.quote.tokenPrice !== 'number' ||
      isNaN(props.quote.discount) || isNaN(props.quote.tokenPrice) ||
      props.quote.discount <= 0 || props.quote.tokenPrice <= 0) {
    return '0'
  }
  
  const inputAmount = parseFloat(props.inputAmount)
  if (isNaN(inputAmount) || inputAmount <= 0) return '0'
  
  // Safe calculation with validation
  const baseAmount = inputAmount * props.quote.tokenPrice
  if (!isFinite(baseAmount) || baseAmount <= 0) return '0'
  
  const discountRate = props.quote.discount / 100
  if (!isFinite(discountRate) || discountRate <= 0) return '0'
  
  const bonusAmount = baseAmount * discountRate
  if (!isFinite(bonusAmount) || bonusAmount <= 0) return '0'
  
  return new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2
  }).format(bonusAmount)
}
</script>