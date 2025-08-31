<template>
  <button
    :type="buttonType"
    :disabled="isButtonDisabled"
    :class="[
      buttonClasses,
      customClasses
    ]"
    @click="(event) => handleButtonClick(event, $emit)"
    @click.capture="debugClick"
  >
    <!-- Loading State -->
    <div v-if="shouldShowSpinner" class="flex items-center justify-center gap-3">
      <svg class="animate-spin w-5 h-5" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
      <span>{{ buttonText }}</span>
    </div>
    
    <!-- Normal State -->
    <span v-else>{{ buttonText }}</span>
  </button>
</template>

<script setup>
import { toRefs } from 'vue'
import { useCTAState } from '~/composables/useCallToActionState.js'

const props = defineProps({
  // Core state props
  walletConnected: { type: Boolean, default: false },
  recipientAddress: { type: String, default: '' },
  recipientAddressError: { type: String, default: '' },
  inputAmount: { type: String, default: '' },
  inputBalance: { type: String, default: '0' },
  ethBalance: { type: String, default: '0' },
  networkFeeEth: { type: String, default: '0' },
  inputToken: { type: String, default: 'ETH' },
  activeTab: { type: String, default: 'liquid' },
  loading: { type: Boolean, default: false },
  canPurchase: { type: Boolean, default: false },
  quote: { type: Object, default: null },
  
  // Customization props
  loadingText: { type: String, default: '' },
  buttonType: { type: String, default: null }, // Override button type
  customButtonText: { type: String, default: null }, // Override button text
  variant: { 
    type: String, 
    default: 'primary',
    validator: (value) => ['primary', 'secondary', 'outline'].includes(value)
  },
  customClasses: { type: String, default: '' }, // Additional CSS classes
  
  // Debug props
  debug: { type: Boolean, default: false }
})

const emit = defineEmits([
  'connect-wallet',
  'focus-address',
  'clear-and-focus-address', 
  'focus-amount',
  'click'
])

// Use the unified CTA state composable
const {
  buttonType,
  buttonText,
  buttonClasses,
  isButtonDisabled,
  shouldShowSpinner,
  currentActionType,
  handleButtonClick
} = useCTAState(toRefs(props))

// Debug logging
const debugClick = (e) => {
  if (props.debug) {
    console.log('🔥 CallToAction button click captured!', {
      disabled: e.target.disabled,
      canPurchase: props.canPurchase,
      loading: props.loading,
      buttonText: buttonText.value,
      currentActionType: currentActionType.value,
      recipientAddress: props.recipientAddress,
      inputAmount: props.inputAmount
    })
  }
}

// Expose internal state for parent components if needed
defineExpose({
  buttonType,
  buttonText,
  currentActionType,
  isButtonDisabled
})
</script>

<style scoped>
/* Additional button-specific styles can go here if needed */
.button-focus-ring:focus {
  @apply ring-2 ring-offset-2 ring-circular-primary;
}

.button-loading {
  @apply pointer-events-none;
}
</style>