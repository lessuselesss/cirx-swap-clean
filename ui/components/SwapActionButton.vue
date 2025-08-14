<template>
  <button
    type="submit"
    :disabled="!canPurchase || loading"
    :class="[
      'w-full py-4 px-6 rounded-xl font-semibold text-lg transition-all duration-300 border-2',
      'disabled:opacity-50 disabled:cursor-not-allowed',
      getButtonClasses()
    ]"
  >
    <div v-if="loading" class="flex items-center justify-center gap-3">
      <svg class="animate-spin w-5 h-5" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
      <span>{{ loadingText || 'Processing...' }}</span>
    </div>
    
    <span v-else>{{ getButtonText() }}</span>
  </button>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  canPurchase: { type: Boolean, required: true },
  loading: { type: Boolean, default: false },
  loadingText: { type: String, default: '' },
  activeTab: { type: String, required: true },
  walletConnected: { type: Boolean, required: true },
  quote: { type: Object, default: null },
  inputAmount: { type: String, default: '' },
  inputBalance: { type: String, default: '0' },
  inputToken: { type: String, default: 'ETH' },
  // New: balances for gating messages
  ethBalance: { type: String, default: '0' },
  networkFeeEth: { type: String, default: '0' }
})

const emit = defineEmits(['connect-wallet'])

// Button styling based on state and tab
const getButtonClasses = () => {
  if (!props.walletConnected) {
    return 'bg-circular-primary border-circular-primary text-gray-900 hover:bg-circular-primary/90 hover:border-circular-primary/90'
  }

  if (props.activeTab === 'liquid') {
    return 'bg-circular-primary border-circular-primary text-gray-900 hover:bg-circular-primary/90 hover:border-circular-primary/90'
  } else {
    return 'bg-circular-purple border-circular-purple text-white hover:bg-circular-purple/90 hover:border-circular-purple/90'
  }
}

// Button text based on state
const getButtonText = () => {
  if (!props.walletConnected) return 'Connect Wallet'

  if (!props.canPurchase) {
    const hasAmount = props.inputAmount && parseFloat(props.inputAmount) > 0
    if (!hasAmount) return 'Enter Amount'

    // Show specific insufficient messages
    const amountNum = parseFloat(props.inputAmount) || 0
    const tokenBal = parseFloat(props.inputBalance) || 0
    const ethBal = parseFloat(props.ethBalance) || 0
    const feeEth = parseFloat(props.networkFeeEth) || 0

    if (props.inputToken === 'ETH') {
      if (ethBal < amountNum + feeEth) return 'Insufficient ETH (incl. gas)'
    } else {
      if (tokenBal < amountNum) return `Insufficient ${props.inputToken}`
      if (ethBal < feeEth) return 'Insufficient ETH for gas'
    }

    return 'Enter Amount'
  }

  if (props.activeTab === 'liquid') return 'Buy Liquid CIRX'
  const discount = props.quote?.discount
  return discount && discount > 0 ? `Buy OTC CIRX (${discount}% Bonus)` : 'Buy OTC CIRX'
}
</script>