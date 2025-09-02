import { computed } from 'vue'

/**
 * Unified composable for managing call-to-action button states
 * Handles the 5 core CTA states without wallet integration complexity
 */
export function useCTAState({
  // Core state props
  isConnected,
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
  
  // Debug log the received isConnected value
  console.log('🔍 useCTAState - Received isConnected:', {
    value: isConnected?.value,
    type: typeof isConnected?.value,
    isRef: !!isConnected?.value !== undefined,
    timestamp: new Date().toISOString()
  })
  
  // Debug addressValidationState
  console.log('🔍 useCTAState - addressValidationState:', {
    raw: addressValidationState,
    value: addressValidationState?.value,
    type: typeof addressValidationState
  })

  // Button type - determines click behavior
  const buttonType = computed(() => {
    // Get validation state consistently 
    const validationState = addressValidationState?.value || addressValidationState
    
    // State 1: Not connected - clickable to connect
    if (isConnected?.value !== true) return 'button'
    
    // State 3: Address validating - disabled
    if (isConnected?.value && validationState === 'validating') return 'disabled'
    
    // Also disabled when address is complete (66 chars) but validation hasn't started
    if (isConnected?.value && recipientAddress?.value && 
        recipientAddress.value.startsWith('0x') && 
        recipientAddress.value.length === 66 && 
        validationState === 'idle' &&
        !recipientAddressError?.value) {
      return 'disabled'
    }
    
    // State 5: Invalid address - clickable to clear and focus
    if (isConnected?.value && recipientAddressError?.value) return 'button'
    
    // NEW: Partial address being typed - clickable to show Get CIRX Wallet modal
    if (isConnected?.value && recipientAddress?.value && 
        (recipientAddress.value === '0' || 
         (recipientAddress.value.startsWith('0x') && recipientAddress.value.length < 66))) {
      return 'button'
    }
    
    // State 2: Empty address - clickable to focus
    if (isConnected?.value && (!recipientAddress?.value || recipientAddress.value.trim() === '')) return 'button'
    
    // State 4: Valid address but no amount - clickable to focus
    if (isConnected?.value && 
        recipientAddress?.value && 
        !recipientAddressError?.value && 
        validationState === 'valid' &&
        (!inputAmount?.value || parseFloat(inputAmount.value) <= 0)) return 'button'
    
    // Ready for transaction
    return 'submit'
  })

  // 5-State Button Text Logic
  const buttonText = computed(() => {
    // Debug logging for validation state
    // In computed, addressValidationState is already unwrapped
    const validationState = addressValidationState?.value || addressValidationState
    if (validationState === 'validating') {
      console.log('🔍 CTA buttonText - addressValidationState is validating, should show "..."')
    }
    
    // State 1: Wallet/AppKit not connected 
    // (Circular Chain address filled/empty = irrelevant, Sell field filled/empty = irrelevant)
    // Button displays "Connect": onclick opens AppKit/wallet
    if (isConnected?.value !== true) {
      return 'Connect'
    }
    
    // State 3: Wallet connected + Address validating + Sell field input filled
    // Button displays "...": onclick is disabled  
    // PRIORITY CHECK - must check this BEFORE any other states
    if (validationState === 'validating') {
      console.log('✅ CTA returning "..." for validating state')
      return '...'
    }
    
    // State 5: Wallet connected + Address invalid (sell field filled/empty = irrelevant)
    // Check if it's a Circular address format that failed validation (yellow solid indicator)
    if (isConnected?.value && recipientAddressError?.value) {
      // If it looks like a Circular address but validation failed, show "Get CIRX Wallet"
      if (recipientAddress?.value && 
          recipientAddress.value.startsWith('0x') && 
          recipientAddress.value.length === 66 &&
          validationState === 'invalid') {
        return 'Get CIRX Wallet'
      }
      // For other invalid addresses (Ethereum, Solana, etc.), show "Enter Valid Address"
      return 'Enter Valid Address'
    }
    
    // NEW: State for partial address being typed (shows "Get CIRX Wallet" while typing)
    // When user is typing a Circular-style address but it's not complete yet
    if (isConnected?.value && recipientAddress?.value && 
        (recipientAddress.value === '0' || 
         (recipientAddress.value.startsWith('0x') && recipientAddress.value.length < 66))) {
      return 'Get CIRX Wallet'
    }
    
    // State for complete address (66 chars) waiting to be validated
    // Show "..." if address is complete but validation hasn't started yet
    if (isConnected?.value && recipientAddress?.value && 
        recipientAddress.value.startsWith('0x') && 
        recipientAddress.value.length === 66 && 
        validationState === 'idle' &&
        !recipientAddressError?.value) {
      return '...'
    }
    
    // State 2: Wallet connected + Address empty + Sell field empty
    // Button displays "Enter Address": onclick focuses address input field
    if (isConnected?.value && (!recipientAddress?.value || recipientAddress.value.trim() === '')) {
      return 'Enter Address'
    }
    
    // State 4: Wallet connected + Address validated + Sell field empty
    // Button displays "Input Amount": onclick focuses sell field input
    if (isConnected?.value && 
        recipientAddress?.value && 
        !recipientAddressError?.value && 
        validationState === 'valid' &&
        (!inputAmount?.value || parseFloat(inputAmount.value) <= 0)) {
      return 'Input Amount'
    }
    
    // Ready to purchase states (all validation passed)
    // BUT ONLY if not currently validating!
    if (validationState !== 'validating') {
      if (activeTab?.value === 'liquid') {
        return 'Buy Liquid CIRX'
      }
      
      // OTC tab with optional discount display
      const discount = quote?.value?.discount
      return discount && discount > 0 
        ? `Buy OTC CIRX (${discount}% Bonus)` 
        : 'Buy OTC CIRX'
    }
    
    // Fallback - should never reach here
    console.warn('⚠️ CTA buttonText reached unexpected state')
    return '...'
  })

  // Button styling based on variant - always use circular-primary to match "Buy Liquid" button
  const buttonClasses = computed(() => {
    const baseClasses = 'w-full py-4 px-6 rounded-xl font-semibold text-lg transition-all duration-300 border-2 disabled:opacity-50 disabled:cursor-not-allowed'
    
    const variantValue = variant?.value || variant || 'primary'
    console.log('🎨 CTA buttonClasses computed - variant:', variantValue)
    
    if (variantValue === 'primary') {
      // Always use circular-primary with 10% opacity for subtle CTA button styling
      return `${baseClasses} bg-circular-primary/10 border-transparent text-circular-primary hover:bg-circular-primary/20 hover:border-transparent`
    }
    
    console.log('🎨 CTA buttonClasses - fallback classes:', baseClasses)
    return baseClasses
  })

  // Button disabled state
  const isButtonDisabled = computed(() => {
    // Get validation state consistently 
    const validationState = addressValidationState?.value || addressValidationState
    
    // State 3: Address validating - button disabled
    if (isConnected?.value && validationState === 'validating') {
      return true
    }
    
    // Also disable when address is complete (66 chars) but validation hasn't started
    if (isConnected?.value && recipientAddress?.value && 
        recipientAddress.value.startsWith('0x') && 
        recipientAddress.value.length === 66 && 
        validationState === 'idle' &&
        !recipientAddressError?.value) {
      return true
    }
    
    return false
  })

  // Handle button click based on current state
  const handleButtonClick = (event, emit) => {
    // Snapshot current values to avoid reactive side effects during click handling
    const isConnectedSnapshot = isConnected?.value
    const recipientAddressSnapshot = recipientAddress?.value
    const recipientAddressErrorSnapshot = recipientAddressError?.value
    const inputAmountSnapshot = inputAmount?.value
    const validationStateSnapshot = addressValidationState?.value || addressValidationState
    
    console.log('🔥 useCTAState handleButtonClick called!', {
      timestamp: new Date().toISOString(),
      isConnected: isConnectedSnapshot,
      recipientAddress: recipientAddressSnapshot,
      recipientAddressError: recipientAddressErrorSnapshot,
      inputAmount: inputAmountSnapshot,
      inputAmountParsed: parseFloat(inputAmountSnapshot || '0'),
      addressValidationState: validationStateSnapshot,
      buttonType: buttonType.value,
      buttonText: buttonText.value,
      eventType: event.type,
      eventTarget: event.target.tagName
    })
    
    // State 1: Connect wallet
    if (isConnectedSnapshot !== true) {
      console.log('🔥 State 1: Connect - Opening AppKit modal')
      emit('connect-wallet')
      return
    }
    
    // State 3: Address validating - disabled, do nothing
    if (isConnectedSnapshot && validationStateSnapshot === 'validating') {
      console.log('🔥 State 3: Address validating - button disabled')
      event.preventDefault()
      return
    }
    
    // State 5: Invalid address - either get CIRX wallet or clear and focus
    if (isConnectedSnapshot && recipientAddressErrorSnapshot) {
      // Check if it's a Circular address format that failed validation (show "Get CIRX Wallet")
      if (recipientAddressSnapshot && 
          recipientAddressSnapshot.startsWith('0x') && 
          recipientAddressSnapshot.length === 66 &&
          validationStateSnapshot === 'invalid') {
        console.log('🔥 State 5: Invalid Circular address - opening Get CIRX Wallet modal')
        event.preventDefault()
        emit('get-cirx-wallet')
        return
      }
      // For other invalid addresses, clear and focus
      console.log('🔥 State 5: Invalid address - clearing and focusing address field')
      event.preventDefault()
      emit('clear-and-focus-address')
      return
    }
    
    // NEW: Handle partial address being typed - show Get CIRX Wallet modal
    if (isConnectedSnapshot && recipientAddressSnapshot && 
        (recipientAddressSnapshot === '0' || 
         (recipientAddressSnapshot.startsWith('0x') && recipientAddressSnapshot.length < 66))) {
      console.log('🔥 Partial address being typed - opening Get CIRX Wallet modal')
      event.preventDefault()
      emit('get-cirx-wallet')
      return
    }
    
    // State 2: Empty address - focus address field
    if (isConnectedSnapshot && (!recipientAddressSnapshot || recipientAddressSnapshot.trim() === '')) {
      console.log('🔥 State 2: Empty address - focusing address field')
      event.preventDefault()
      emit('focus-address')
      return
    }
    
    // State 4: Valid address but no amount - focus Sell field (amount input)
    if (isConnectedSnapshot && 
        recipientAddressSnapshot && 
        !recipientAddressErrorSnapshot && 
        validationStateSnapshot === 'valid' &&
        (!inputAmountSnapshot || parseFloat(inputAmountSnapshot) <= 0)) {
      console.log('🔥 State 4: Valid address, no amount - focusing Sell field (amount input)')
      event.preventDefault()
      event.stopPropagation() // Prevent any other click handlers from interfering
      emit('focus-amount')
      return
    }
    
    // All states passed - ready for transaction
    console.log('🔥 All states passed - emitting transaction click')
    emit('click', event)
  }

  // Current state for debugging
  const currentState = computed(() => {
    // Get validation state consistently 
    const validationState = addressValidationState?.value || addressValidationState
    
    if (isConnected?.value !== true) return 'state-1-connect'
    if (validationState === 'validating') return 'state-3-validating'
    if (recipientAddressError?.value) return 'state-5-invalid-address'
    if (!recipientAddress?.value || recipientAddress.value.trim() === '') return 'state-2-enter-address'
    if (!inputAmount?.value || parseFloat(inputAmount.value) <= 0) return 'state-4-enter-amount'
    return 'ready-to-purchase'
  })

  // Current action type (alias for currentState for backward compatibility)
  const currentActionType = currentState

  // Loading spinner display logic
  const shouldShowSpinner = computed(() => {
    // Don't show spinner for "..." state - dots are sufficient visual indicator
    // Only show spinner for other loading states if needed
    return false
  })

  // Handler functions for individual states
  const handleConnectWallet = async () => {
    console.log('🔗 handleConnectWallet called - opening AppKit modal')
    try {
      if (process.client && window.$appKit && typeof window.$appKit.open === 'function') {
        window.$appKit.open()
      } else if (process.client && typeof open === 'function') {
        open()
      } else {
        console.warn('AppKit modal not available')
      }
    } catch (error) {
      console.error('Error opening wallet modal:', error)
    }
  }

  const handleFocusAddress = () => {
    console.log('🎯 handleFocusAddress called - focusing recipient address input')
    if (!process.client) return
    
    setTimeout(() => {
      const addressInput = document.querySelector('input[placeholder*="Testnet"], input[placeholder*="Circular SandBox"], input[placeholder*="Wallet Address"]')
      if (addressInput) {
        console.log('🎯 Focusing address input from button click')
        addressInput.focus()
      } else {
        console.log('❌ Address input not found in handleFocusAddress')
      }
    }, 100)
  }

  const handleClearAndFocusAddress = () => {
    console.log('🎯 handleClearAndFocusAddress called - clearing and focusing address input')
    if (recipientAddress?.value !== undefined) {
      recipientAddress.value = ''
    }
    
    if (!process.client) return
    
    setTimeout(() => {
      const addressInput = document.querySelector('input[placeholder*="Testnet"], input[placeholder*="Circular SandBox"], input[placeholder*="Wallet Address"]')
      if (addressInput) {
        console.log('🎯 Clearing and focusing address input from button click')
        addressInput.focus()
      } else {
        console.log('❌ Address input not found in handleClearAndFocusAddress')
      }
    }, 100)
  }

  const handleFocusAmount = () => {
    console.log('🎯 handleFocusAmount called - focusing Sell field (amount input)')
    if (!process.client) return
    
    // Set a flag to prevent address blur validation during programmatic focus
    if (typeof window !== 'undefined') {
      window._preventAddressBlurValidation = true
    }
    
    // Try to focus immediately first, then with timeout as fallback
    const focusInput = () => {
      // Target the Sell field specifically - it's in the .input-section-top
      const sellSection = document.querySelector('.input-section-top')
      const amountInput = sellSection?.querySelector('.amount-input') ||
                         document.querySelector('input[placeholder="0.0"]:first-of-type')
      
      if (amountInput) {
        console.log('🎯 Focusing Sell field (amount input) from button click', amountInput)
        amountInput.focus()
        
        // Clear the flag after a brief delay to allow other blur events to work normally
        setTimeout(() => {
          if (typeof window !== 'undefined') {
            window._preventAddressBlurValidation = false
          }
        }, 100)
        
        return true
      } else {
        console.log('❌ Sell field input not found in handleFocusAmount')
        // Clear flag even if focus failed
        if (typeof window !== 'undefined') {
          window._preventAddressBlurValidation = false
        }
        return false
      }
    }
    
    // Try immediately first
    if (!focusInput()) {
      // If immediate focus fails, try with timeout
      setTimeout(focusInput, 50)
    }
  }

  // Alternative name for handleFocusAmount for clarity
  const handleFocusAmountInput = handleFocusAmount

  const handleGetCirxWallet = (showModalFn) => {
    console.log('🔗 handleGetCirxWallet called - opening Circular wallet modal')
    // Call the function to show the modal (passed from parent component)
    if (typeof showModalFn === 'function') {
      showModalFn()
    } else {
      console.warn('No show modal function provided for get-cirx-wallet')
    }
  }

  const handleSwap = (swapControllerFn) => {
    console.log('🔥 CTA handleSwap called - delegating to controller')
    
    // The CTA composable should NOT contain swap validation logic
    // That belongs in the controller function in index.vue
    // Just call the controller function directly
    if (typeof swapControllerFn === 'function') {
      console.log('✅ Calling swap controller function')
      swapControllerFn()
    } else {
      console.warn('No swap controller function provided')
    }
  }

  const handleValidateAddress = async (validateFn, address) => {
    console.log('🔍 CTA handleValidateAddress called with:', address)
    if (typeof validateFn === 'function') {
      await validateFn(address)
    } else {
      console.warn('No validation function provided')
    }
  }

  return {
    buttonType,
    buttonText,
    buttonClasses,
    isButtonDisabled,
    shouldShowSpinner,
    currentState,
    currentActionType,
    handleButtonClick,
    
    // Individual handler functions
    handleConnectWallet,
    handleFocusAddress,
    handleClearAndFocusAddress,
    handleFocusAmount,
    handleFocusAmountInput,
    handleGetCirxWallet,
    handleSwap,
    handleValidateAddress
  }
}