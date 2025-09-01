<template>
  <!-- CACHE CLEAR TEST -->
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
import { toRefs, nextTick, watch } from 'vue'
import { useCTAState } from '~/composables/core/useCallToActionState.js'
import { useAppKitWallet } from '~/composables/useAppKitWallet.js'

// Use centralized wallet composable (includes AppKit singleton)
const { isConnected, open } = useAppKitWallet()

// DEBUG: Check what isConnected actually contains
console.log('🔥 CallToAction - isConnected from useAppKitWallet:', {
  isConnected,
  value: isConnected?.value,
  type: typeof isConnected?.value,
  timestamp: new Date().toISOString()
})

const connectWallet = () => {
  // Use global AppKit instance directly for reliable modal opening
  if (window.$appKit && typeof window.$appKit.open === 'function') {
    window.$appKit.open()
  } else if (typeof open === 'function') {
    open()
  } else {
    console.warn('AppKit modal not available')
  }
}

const props = defineProps({
  // Core state props - wallet connection now handled by AppKit
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
// Explicitly create the state object with wallet connection from AppKit
const ctaStateProps = {
  // Spread props first
  ...toRefs(props),
  // Use AppKit isConnected directly
  isConnected: isConnected
}

console.log('🔥 CallToAction - CTA State Props:', {
  isConnected: ctaStateProps.isConnected,
  isConnectedValue: ctaStateProps.isConnected?.value,
  allProps: Object.keys(ctaStateProps)
})

const ctaStateResult = useCTAState(ctaStateProps)

console.log('🔥 CallToAction - useCTAState returned:', {
  keys: Object.keys(ctaStateResult || {}),
  buttonText: ctaStateResult?.buttonText,
  currentState: ctaStateResult?.currentState,
  isUndefined: ctaStateResult === undefined,
  isNull: ctaStateResult === null,
  type: typeof ctaStateResult
})

const {
  buttonType,
  buttonText,
  buttonClasses,
  isButtonDisabled,
  shouldShowSpinner,
  currentActionType,
  currentState,
  handleButtonClick
} = ctaStateResult || {}

// Debug CTA state after component initialization
nextTick(() => {
  console.log('🔍 CallToAction - CTA button state FIXED:', {
    buttonText: buttonText?.value,
    isConnected: isConnected?.value,
    isConnectedType: typeof isConnected?.value,
    rawIsConnected: isConnected?.value,
    currentActionType: currentActionType?.value,
    currentStateValue: currentState?.value,
    hasButtonText: !!buttonText,
    hasCurrentState: !!currentState
  })
})

// Add continuous debug watcher to see state changes
watch(() => [isConnected?.value, buttonText?.value, currentState?.value], 
  ([connected, text, state]) => {
    console.log('🔍 CallToAction - State Changed:', {
      timestamp: new Date().toISOString(),
      isConnected: connected,
      buttonText: text, 
      currentState: state,
      isConnectedType: typeof connected,
      buttonTextExists: !!buttonText,
      currentStateExists: !!currentState
    })
  }, 
  { immediate: true }
)

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