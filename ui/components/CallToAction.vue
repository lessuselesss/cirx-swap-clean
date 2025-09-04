<template>
  <!-- CACHE CLEAR TEST -->
  <button
    :type="buttonType"
    :disabled="isButtonDisabled"
    :class="[
      buttonClasses,
      customClasses
    ]"
    @click="(event) => {
      console.log('🔥 BUTTON CLICKED! CallToAction button click detected');
      handleButtonClick(event, $emit);
    }"
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
import { toRefs, nextTick, watch, ref, computed } from 'vue'
import { useCTAState } from '~/composables/core/useCallToActionState.js'
import { useAppKitWallet } from '~/composables/useAppKitWallet.js'

// Get wallet composable with reactive state
const walletComposable = useAppKitWallet()

// Use reactive state from wallet composable - this is properly reactive for Vue templates
const isConnected = walletComposable.isConnected

// Watch for reactive state changes to debug
watch(isConnected, 
  (connected) => {
    console.log('🔄 CallToAction reactive connection state changed:', {
      connected,
      timestamp: new Date().toISOString()
    })
  },
  { immediate: true }
)

// DEBUG: Check reactive state initialization
console.log('🔥 CallToAction - Reactive connection state:', {
  reactiveConnected: isConnected.value,
  isRef: !!isConnected._rawValue !== undefined,
  timestamp: new Date().toISOString()
})

// Moved watch functions after buttonText destructuring to avoid initialization errors

// Wallet connection is now handled by the CTA state logic

const props = defineProps({
  // Core state props - wallet connection now handled by AppKit
  recipientAddress: { type: String, default: '' },
  recipientAddressError: { type: String, default: '' },
  addressValidationState: { type: String, default: 'idle' }, // CRITICAL: needed for "..." display
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

// DEBUG: Watch button state changes (moved after destructuring)
watch(() => [buttonText?.value, isButtonDisabled?.value, currentState?.value], 
  ([text, disabled, state]) => {
    console.log('🔍 CallToAction - Button State Change:', {
      buttonText: text,
      isDisabled: disabled,
      currentState: state,
      timestamp: new Date().toISOString()
    })
  }, 
  { immediate: true }
)

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

// Debug logging removed - functionality now handled by CTA state

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