import { computed } from 'vue'

/**
 * Composable for managing swap button state and behavior
 * Centralizes all call-to-action logic for the swap form
 */
export function useSwapButtonState({
  walletConnected,
  recipientAddress,
  recipientAddressError,
  inputAmount,
  inputBalance,
  ethBalance,
  networkFeeEth,
  inputToken,
  activeTab,
  loading,
  canPurchase,
  quote
}) {
  
  // Button type - "button" for focus states, "submit" for purchase states
  const buttonType = computed(() => {
    // For focus states, use type="button" to prevent form submission
    if (!walletConnected.value) return 'button'
    if (walletConnected.value && !recipientAddress.value) return 'button' // "Enter Address"
    if (walletConnected.value && recipientAddress.value && recipientAddressError.value) return 'button' // "Enter a Valid Address"
    
    // For purchase states, use type="submit" to trigger form submission
    return 'submit'
  })

  // Button text based on current state
  const buttonText = computed(() => {
    if (loading.value) {
      return 'Processing...' // Could be customized with loadingText prop
    }

    // CTA Logic based on wallet connection and recipient address
    if (!walletConnected.value && !recipientAddress.value) {
      // State 1: No wallet + no address = "Connect"
      return 'Connect'
    }
    
    if (!walletConnected.value && recipientAddress.value) {
      // State 2: Has address but no wallet = "Connect Wallet"
      return 'Connect Wallet'
    }
    
    if (walletConnected.value && !recipientAddress.value) {
      // State 3: Has wallet but no address = "Enter Address"
      return 'Enter Address'
    }
    
    if (walletConnected.value && recipientAddress.value && recipientAddressError.value) {
      // State 4: Has wallet + invalid address = "Enter a Circular Chain Address"
      return 'Enter a Circular Chain Address'
    }
    
    if (walletConnected.value && recipientAddress.value && !recipientAddressError.value) {
      // State 5: Has wallet + valid address, check if amount is entered
      const hasAmount = inputAmount.value && parseFloat(inputAmount.value) > 0
      
      if (!hasAmount) {
        return 'Enter Amount'
      }
    }
    
    // Both wallet, valid address, and amount are present - check other purchase conditions
    if (!canPurchase.value) {
      // Show specific insufficient balance messages
      const amountNum = parseFloat(inputAmount.value) || 0
      const tokenBal = parseFloat(inputBalance.value) || 0
      const ethBal = parseFloat(ethBalance.value) || 0
      const feeEth = parseFloat(networkFeeEth.value) || 0

      if (inputToken.value === 'ETH') {
        if (ethBal < amountNum + feeEth) return 'Insufficient ETH (incl. gas)'
      } else {
        if (tokenBal < amountNum) return `Insufficient ${inputToken.value}`
        if (ethBal < feeEth) return 'Insufficient ETH for gas'
      }

      return 'Enter Amount'
    }

    // Ready to purchase
    if (activeTab.value === 'liquid') return 'Buy Liquid CIRX'
    const discount = quote.value?.discount
    return discount && discount > 0 ? `Buy OTC CIRX (${discount}% Bonus)` : 'Buy OTC CIRX'
  })

  // Button styling based on state and tab
  const buttonClasses = computed(() => {
    if (!walletConnected.value) {
      return 'bg-circular-primary border-circular-primary text-gray-900 hover:bg-circular-primary/90 hover:border-circular-primary/90'
    }

    if (activeTab.value === 'liquid') {
      return 'bg-circular-primary border-circular-primary text-gray-900 hover:bg-circular-primary/90 hover:border-circular-primary/90'
    } else {
      return 'bg-circular-purple border-circular-purple text-white hover:bg-circular-purple/90 hover:border-circular-purple/90'
    }
  })

  // Button disabled state
  const isButtonDisabled = computed(() => {
    return !canPurchase.value || loading.value
  })

  // Handle button click based on current state
  const handleButtonClick = (event, emit) => {
    console.log('🔥 useSwapButtonState handleButtonClick called!', {
      buttonType: buttonType.value,
      walletConnected: walletConnected.value,
      recipientAddress: recipientAddress.value,
      recipientAddressError: recipientAddressError.value,
      inputAmount: inputAmount.value,
      canPurchase: canPurchase.value,
      loading: loading.value
    })
    
    // Only handle click for non-submit button types (focus states)
    if (buttonType.value === 'button') {
      console.log('🔥 Button type is "button" - handling internally')
      event.preventDefault()
      
      if (!walletConnected.value) {
        console.log('🔥 Emitting connect-wallet')
        emit('connect-wallet')
      } else if (walletConnected.value && !recipientAddress.value) {
        console.log('🔥 Emitting enter-address')
        emit('enter-address')
      } else if (walletConnected.value && recipientAddress.value && recipientAddressError.value) {
        console.log('🔥 Emitting enter-valid-address')
        emit('enter-valid-address')
      } else if (!inputAmount.value || parseFloat(inputAmount.value) <= 0) {
        console.log('🔥 Emitting enter-amount')
        emit('enter-amount')
      }
    } else {
      console.log('🔥 Button type is "submit" - emitting click event to parent')
      // For submit type buttons, emit click event for parent to handle
      emit('click', event)
    }
  }

  return {
    buttonType,
    buttonText,
    buttonClasses,
    isButtonDisabled,
    handleButtonClick
  }
}