<template>
  <button
    :type="buttonType"
    :disabled="isButtonDisabled"
    :class="[
      'w-full py-4 px-6 rounded-xl font-semibold text-lg transition-all duration-300 border-2',
      'disabled:opacity-50 disabled:cursor-not-allowed',
      buttonClasses
    ]"
    @click="(event) => handleButtonClick(event, $emit)"
    @click.capture="(e) => console.log('🔥 Button click captured! disabled:', e.target.disabled, 'canPurchase:', canPurchase, 'loading:', loading)"
  >
    <div v-if="loading" class="flex items-center justify-center gap-3">
      <svg class="animate-spin w-5 h-5" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
      <span>{{ loadingText || 'Processing...' }}</span>
    </div>
    
    <span v-else>{{ buttonText }}</span>
  </button>
</template>

<script setup>
import { toRefs } from 'vue'
import { useSwapButtonState } from '~/composables/useRCallToAction.js'

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
  networkFeeEth: { type: String, default: '0' },
  // New: recipient address for CTA logic
  recipientAddress: { type: String, default: '' },
  // New: recipient address error for CTA logic
  recipientAddressError: { type: String, default: '' }
})

const emit = defineEmits(['connect-wallet', 'enter-address', 'enter-valid-address', 'enter-amount', 'click'])

// Use the composable for button state management
const {
  buttonType,
  buttonText,
  buttonClasses,
  isButtonDisabled,
  handleButtonClick
} = useSwapButtonState(toRefs(props))

// All button logic is now handled by the useSwapButtonState composable
</script>