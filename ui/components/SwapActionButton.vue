<template>
  <button
    :type="getButtonType()"
    :disabled="!canPurchase || loading"
    :class="[
      'w-full py-4 px-6 rounded-xl font-semibold text-lg transition-all duration-300 border-2',
      'disabled:opacity-50 disabled:cursor-not-allowed',
      getButtonClasses()
    ]"
    @click="handleButtonClick"
    @click.capture="(e) => console.log('🔥 Button click captured! disabled:', e.target.disabled, 'canPurchase:', canPurchase, 'loading:', loading)"
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
  networkFeeEth: { type: String, default: '0' },
  // New: recipient address for CTA logic
  recipientAddress: { type: String, default: '' },
  // New: recipient address error for CTA logic
  recipientAddressError: { type: String, default: '' }
})

const emit = defineEmits(['connect-wallet', 'enter-address', 'enter-valid-address', 'enter-amount', 'click'])

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

// Button type - "button" for focus states, "submit" for purchase states
const getButtonType = () => {
  // For focus states, use type="button" to prevent form submission
  if (!props.walletConnected) return 'button'
  if (props.walletConnected && !props.recipientAddress) return 'button' // "Enter Address"
  if (props.walletConnected && props.recipientAddress && props.recipientAddressError) return 'button' // "Enter a Valid Address"
  
  // For purchase states, use type="submit" to trigger form submission
  return 'submit'
}

// Handle button click based on current state
const handleButtonClick = (event) => {
  console.log('🔥 SwapActionButton handleButtonClick called!', {
    buttonType: getButtonType(),
    walletConnected: props.walletConnected,
    recipientAddress: props.recipientAddress,
    recipientAddressError: props.recipientAddressError,
    inputAmount: props.inputAmount,
    canPurchase: props.canPurchase,
    loading: props.loading
  })
  
  // Only handle click for non-submit button types (focus states)
  if (getButtonType() === 'button') {
    console.log('🔥 Button type is "button" - handling internally')
    event.preventDefault()
    
    if (!props.walletConnected) {
      console.log('🔥 Emitting connect-wallet')
      emit('connect-wallet')
    } else if (props.walletConnected && !props.recipientAddress) {
      console.log('🔥 Emitting enter-address')
      emit('enter-address')
    } else if (props.walletConnected && props.recipientAddress && props.recipientAddressError) {
      console.log('🔥 Emitting enter-valid-address')
      emit('enter-valid-address')
    } else if (!props.inputAmount || parseFloat(props.inputAmount) <= 0) {
      console.log('🔥 Emitting enter-amount')
      emit('enter-amount')
    }
  } else {
    console.log('🔥 Button type is "submit" - emitting click event to parent')
    // For submit type buttons, emit click event for parent to handle
    emit('click', event)
  }
}

// Button text based on state
const getButtonText = () => {
  // CTA Logic based on wallet connection and recipient address
  if (!props.walletConnected && !props.recipientAddress) {
    // State 1: No wallet + no address = "Connect"
    return 'Connect'
  }
  
  if (!props.walletConnected && props.recipientAddress) {
    // State 2: Has address but no wallet = "Connect Wallet"
    return 'Connect Wallet'
  }
  
  if (props.walletConnected && !props.recipientAddress) {
    // State 3: Has wallet but no address = "Enter Address"
    return 'Enter Address'
  }
  
  if (props.walletConnected && props.recipientAddress && props.recipientAddressError) {
    // State 4: Has wallet + invalid address = "Enter a Circular Chain Address"
    return 'Enter a Circular Chain Address'
  }
  
  if (props.walletConnected && props.recipientAddress && !props.recipientAddressError) {
    // State 5: Has wallet + valid address, check if amount is entered
    const hasAmount = props.inputAmount && parseFloat(props.inputAmount) > 0
    
    if (!hasAmount) {
      return 'Enter Amount'
    }
  }
  
  // Both wallet, valid address, and amount are present - check other purchase conditions
  if (!props.canPurchase) {
    // Show specific insufficient balance messages
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

  // Ready to purchase
  if (props.activeTab === 'liquid') return 'Buy Liquid CIRX'
  const discount = props.quote?.discount
  return discount && discount > 0 ? `Buy OTC CIRX (${discount}% Bonus)` : 'Buy OTC CIRX'
}
</script>