import { computed } from 'vue'

/**
 * Unified composable for managing call-to-action button states
 * Handles the 5 core CTA states without wallet integration complexity
 */
export function useCTAState({
  // Core state props
  walletConnected,
  recipientAddress,
  recipientAddressError, 
  inputAmount,
  addressValidationState,
  activeTab,
  quote,
  
  // Optional customization
  loadingText = null,
  variant = 'primary'
}) {

  // Button type - determines click behavior
  const buttonType = computed(() => {
    // State 1: Not connected - clickable to connect
    if (!walletConnected?.value) return 'button'
    
    // State 3: Address validating - disabled
    if (walletConnected.value && addressValidationState?.value === 'validating') return 'disabled'
    
    // State 5: Invalid address - clickable to clear and focus
    if (walletConnected.value && recipientAddressError?.value) return 'button'
    
    // State 2: Empty address - clickable to focus
    if (walletConnected.value && (!recipientAddress?.value || recipientAddress.value.trim() === '')) return 'button'
    
    // State 4: Valid address but no amount - clickable to focus
    if (walletConnected.value && 
        recipientAddress?.value && 
        !recipientAddressError?.value && 
        addressValidationState?.value === 'valid' &&
        (!inputAmount?.value || parseFloat(inputAmount.value) <= 0)) return 'button'
    
    // Ready for transaction
    return 'submit'
  })

  // 5-State Button Text Logic
  const buttonText = computed(() => {
    // State 1: Wallet/AppKit not connected 
    // (Circular Chain address filled/empty = irrelevant, Sell field filled/empty = irrelevant)
    // Button displays "Connect": onclick opens AppKit/wallet
    if (!walletConnected?.value) {
      return 'Connect'
    }
    
    // State 3: Wallet connected + Address validating + Sell field input filled
    // Button displays "...": onclick is disabled
    if (walletConnected.value && addressValidationState?.value === 'validating') {
      return '...'
    }
    
    // State 5: Wallet connected + Address invalid (sell field filled/empty = irrelevant)
    // Button displays "Enter Valid Address": onclick clears address field and focuses
    if (walletConnected.value && recipientAddressError?.value) {
      return 'Enter Valid Address'
    }
    
    // State 2: Wallet connected + Address empty + Sell field empty
    // Button displays "Enter Address": onclick focuses address input field
    if (walletConnected.value && (!recipientAddress?.value || recipientAddress.value.trim() === '')) {
      return 'Enter Address'
    }
    
    // State 4: Wallet connected + Address validated + Sell field empty
    // Button displays "Enter Amount": onclick focuses sell field input
    if (walletConnected.value && 
        recipientAddress?.value && 
        !recipientAddressError?.value && 
        addressValidationState?.value === 'valid' &&
        (!inputAmount?.value || parseFloat(inputAmount.value) <= 0)) {
      return 'Enter Amount'
    }
    
    // Ready to purchase states (all validation passed)
    if (activeTab?.value === 'liquid') {
      return 'Buy Liquid CIRX'
    }
    
    // OTC tab with optional discount display
    const discount = quote?.value?.discount
    return discount && discount > 0 
      ? `Buy OTC CIRX (${discount}% Bonus)` 
      : 'Buy OTC CIRX'
  })

  // Button styling based on variant and active tab
  const buttonClasses = computed(() => {
    const baseClasses = 'w-full py-4 px-6 rounded-xl font-semibold text-lg transition-all duration-300 border-2 disabled:opacity-50 disabled:cursor-not-allowed'
    
    if (variant === 'primary') {
      if (activeTab?.value === 'liquid') {
        return `${baseClasses} bg-circular-primary border-circular-primary text-gray-900 hover:bg-circular-primary/90 hover:border-circular-primary/90`
      } else {
        return `${baseClasses} bg-circular-purple border-circular-purple text-white hover:bg-circular-purple/90 hover:border-circular-purple/90`
      }
    }
    
    return baseClasses
  })

  // Button disabled state
  const isButtonDisabled = computed(() => {
    // State 3: Address validating - button disabled
    if (walletConnected?.value && addressValidationState?.value === 'validating') {
      return true
    }
    
    return false
  })

  // Handle button click based on current state
  const handleButtonClick = (event, emit) => {
    console.log('🔥 useCTAState handleButtonClick called!', {
      walletConnected: walletConnected?.value,
      recipientAddress: recipientAddress?.value,
      recipientAddressError: recipientAddressError?.value,
      inputAmount: inputAmount?.value,
      addressValidationState: addressValidationState?.value,
      buttonType: buttonType.value,
      buttonText: buttonText.value
    })
    
    // State 1: Connect wallet
    if (!walletConnected?.value) {
      console.log('🔥 State 1: Connect - Opening AppKit modal')
      emit('connect-wallet')
      return
    }
    
    // State 3: Address validating - disabled, do nothing
    if (walletConnected.value && addressValidationState?.value === 'validating') {
      console.log('🔥 State 3: Address validating - button disabled')
      event.preventDefault()
      return
    }
    
    // State 5: Invalid address - clear and focus
    if (walletConnected.value && recipientAddressError?.value) {
      console.log('🔥 State 5: Invalid address - clearing and focusing address field')
      event.preventDefault()
      emit('clear-and-focus-address')
      return
    }
    
    // State 2: Empty address - focus address field
    if (walletConnected.value && (!recipientAddress?.value || recipientAddress.value.trim() === '')) {
      console.log('🔥 State 2: Empty address - focusing address field')
      event.preventDefault()
      emit('focus-address')
      return
    }
    
    // State 4: Valid address but no amount - focus amount field
    if (walletConnected.value && 
        recipientAddress?.value && 
        !recipientAddressError?.value && 
        addressValidationState?.value === 'valid' &&
        (!inputAmount?.value || parseFloat(inputAmount.value) <= 0)) {
      console.log('🔥 State 4: Valid address, no amount - focusing amount field')
      event.preventDefault()
      emit('focus-amount')
      return
    }
    
    // All states passed - ready for transaction
    console.log('🔥 All states passed - emitting transaction click')
    emit('click', event)
  }

  // Current state for debugging
  const currentState = computed(() => {
    if (!walletConnected?.value) return 'state-1-connect'
    if (addressValidationState?.value === 'validating') return 'state-3-validating'
    if (recipientAddressError?.value) return 'state-5-invalid-address'
    if (!recipientAddress?.value || recipientAddress.value.trim() === '') return 'state-2-enter-address'
    if (!inputAmount?.value || parseFloat(inputAmount.value) <= 0) return 'state-4-enter-amount'
    return 'ready-to-purchase'
  })

  // Current action type (alias for currentState for backward compatibility)
  const currentActionType = currentState

  // Loading spinner display logic
  const shouldShowSpinner = computed(() => {
    // Show spinner during address validation
    return addressValidationState?.value === 'validating'
  })

  return {
    buttonType,
    buttonText,
    buttonClasses,
    isButtonDisabled,
    shouldShowSpinner,
    currentState,
    currentActionType,
    handleButtonClick
  }
}