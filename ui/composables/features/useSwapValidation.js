import { computed, ref, watch, nextTick } from 'vue'
import { useFormattedNumbers } from '~/composables/useFormattedNumbers'
import { useCircularAddressValidation } from '~/composables/utils/validators'

/**
 * Composable for swap validation logic
 * Centralizes all validation rules and address checking functionality
 */
export function useSwapValidation(validationState = {}) {
  // Initialize address validation utilities
  const { isValidEthereumAddress, isValidSolanaAddress, isValidCircularAddress } = useFormattedNumbers()
  const { checkAddressExists, isValidCircularAddressFormat } = useCircularAddressValidation()
  
  // Use provided validation state or create local state
  const recipientAddressError = validationState.recipientAddressError || ref('')
  const recipientAddressType = validationState.recipientAddressType || ref('')
  const addressValidationState = validationState.addressValidationState || ref('idle')
  const hasClickedEnterAddress = validationState.hasClickedEnterAddress || ref(false)
  
  // Track ongoing validation to prevent race conditions
  let currentValidationPromise = null
  
  /**
   * Input validation for keypress events on numeric fields
   * Prevents invalid characters and multiple decimal points
   */
  const validateNumberInput = (event) => {
    const char = event.key
    const currentValue = event.target.value
    const cursorPosition = event.target.selectionStart
    
    // Allow control keys (backspace, delete, tab, escape, enter, etc.)
    if (event.ctrlKey || event.metaKey || 
        ['Backspace', 'Delete', 'Tab', 'Escape', 'Enter', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(char)) {
      return true
    }
    
    // Allow only numbers and decimal point
    if (!/[0-9.]/.test(char)) {
      event.preventDefault()
      return false
    }
    
    // Prevent multiple decimal points
    if (char === '.' && currentValue.includes('.')) {
      event.preventDefault()
      return false
    }
    
    // Prevent leading zeros followed by digits (but allow "0.")
    if (char !== '.' && currentValue === '0' && cursorPosition === 1) {
      event.preventDefault()
      return false
    }
    
    return true
  }

  /**
   * Synchronous validation for immediate format checks (Ethereum/Solana detection)
   * Provides instant feedback on address format
   */
  const validateRecipientAddressSync = (address) => {
    console.log('🔍 Sync validation called with:', address, 'length:', address?.length)
    
    if (!address) {
      recipientAddressError.value = ''
      recipientAddressType.value = ''
      addressValidationState.value = 'idle'
      return true
    }
    
    // Check for Ethereum addresses FIRST before treating as partial
    // Ethereum addresses are exactly 42 chars (0x + 40 hex)
    if (address.length === 42 && isValidEthereumAddress(address)) {
      console.log('🔴 Ethereum address detected:', address)
      recipientAddressError.value = 'Ethereum addresses are not supported. Please enter a valid CIRX address'
      recipientAddressType.value = ''
      addressValidationState.value = 'invalid'
      return false
    }
    
    // Clear errors for partial addresses being typed (but not complete Ethereum addresses)
    if (address === '0' || (address.startsWith('0x') && address.length < 66 && address.length !== 42)) {
      recipientAddressError.value = ''
      recipientAddressType.value = ''
      addressValidationState.value = 'idle'
      return false
    }
    
    // Check for invalid starting patterns that aren't partial
    if (address.startsWith('0') && !address.startsWith('0x')) {
      recipientAddressError.value = 'Invalid address format. Addresses should start with 0x'
      recipientAddressType.value = ''
      addressValidationState.value = 'invalid'
      return false
    }
    
    // Check for Solana addresses
    if (isValidSolanaAddress(address)) {
      recipientAddressError.value = 'Solana addresses are not supported. Please enter a valid CIRX address'
      recipientAddressType.value = ''
      addressValidationState.value = 'invalid'
      return false
    }
    
    // For any other length that's not partial, it's invalid
    if (address.startsWith('0x') && address.length > 66) {
      recipientAddressError.value = 'Invalid address format. Address is too long'
      recipientAddressType.value = ''
      addressValidationState.value = 'invalid'
      return false
    }
    
    return true
  }

  /**
   * Asynchronous address validation with backend API checks
   * Validates address format and existence on Circular network
   */
  const validateRecipientAddress = async (address) => {
    console.log('🔍 validateRecipientAddress called with:', address, 'length:', address?.length)
    
    // Cancel any ongoing validation
    currentValidationPromise = null
    
    // If the address is already valid and error-free, skip the full validation process
    const currentState = addressValidationState.value
    if (currentState === 'valid' && address && !recipientAddressError.value) {
      console.log('🔍 Address already validated and valid - skipping re-validation')
      return true
    }
    
    if (!address) {
      console.log('🔍 Empty address - setting state to idle')
      recipientAddressError.value = ''
      recipientAddressType.value = ''
      addressValidationState.value = 'idle'
      return true
    }
    
    // Skip validation if synchronous validation already handled these cases
    // The sync validation handles: Ethereum, Solana, partial addresses, invalid formats
    if (recipientAddressError.value) {
      // Error already set by sync validation, skip async validation
      return false
    }
    
    // Skip partial addresses that were already handled
    if (address === '0' || (address.startsWith('0x') && address.length < 66)) {
      return false
    }
    
    // Skip invalid format addresses that were already handled
    if ((address.startsWith('0') && !address.startsWith('0x')) ||
        isValidEthereumAddress(address) || 
        isValidSolanaAddress(address)) {
      return false
    }
    
    // Check if it looks like a Circular address format
    console.log('🔍 Checking if valid circular format:', address, 'result:', isValidCircularAddressFormat(address))
    if (isValidCircularAddressFormat(address)) {
      console.log('✅ Address passed format check, starting validation for:', address)
      
      // Create a unique promise for this validation
      const validationPromise = new Promise(async (resolve, reject) => {
        try {
          console.log('📝 Inside validation promise for:', address)
          
          // Set validating state immediately (this will show the yellow flash)
          console.log('🟡 Setting addressValidationState to validating for:', address)
          addressValidationState.value = 'validating'
          recipientAddressError.value = ''
          recipientAddressType.value = ''
          
          // Force DOM update and reflow to ensure animation starts immediately
          await nextTick()
          if (process.client) {
            // Force reflow by accessing a layout property
            const indicator = document.querySelector('[title="Validation status"], .animate-flash')
            if (indicator) {
              indicator.offsetHeight // Force reflow
              console.log('🔄 Forced DOM reflow for animation')
            }
          }
          
          // Small delay to ensure the yellow flash is visible
          await new Promise(r => setTimeout(r, 200))
          
          // Check if still current before API call
          if (currentValidationPromise !== validationPromise) {
            console.log('🔍 Validation cancelled before API call for:', address)
            return resolve({ cancelled: true })
          }
          
          // Perform async validation with balance checking
          console.log('🌐 Making API call for address:', address)
          const validation = await checkAddressExists(address)
          console.log('🌐 API call completed for address:', address, 'result:', validation)
          resolve({ validation, address, cancelled: false })
        } catch (error) {
          console.error('❌ Validation error for:', address, error)
          reject(error)
        }
      })
      
      // Set as current validation, then await
      currentValidationPromise = validationPromise
      
      try {
        const result = await validationPromise
        
        // Check if validation was cancelled
        if (result.cancelled) {
          console.log('🔍 Validation was cancelled for:', address)
          return false
        }
        
        // Final check if this is still the current validation
        if (currentValidationPromise !== validationPromise) {
          console.log('🔍 Validation no longer current for:', address)
          return false
        }
        
        const { validation } = result
        
        if (validation.isValid && validation.exists) {
          // Address exists on network, now check balance for green light
          recipientAddressError.value = ''
          recipientAddressType.value = 'circular'
          addressValidationState.value = 'valid'
          hasClickedEnterAddress.value = false
          return true
          
        } else if (validation.isValid && !validation.exists) {
          // Valid format but doesn't exist on network yet (could be new address)
          recipientAddressError.value = 'Address not found on Circular network. Please verify the address.'
          recipientAddressType.value = ''
          addressValidationState.value = 'invalid'
          return false
          
        } else {
          // Invalid or API error
          recipientAddressError.value = validation.error || 'Unable to validate address. Please check the format.'
          recipientAddressType.value = ''
          addressValidationState.value = 'invalid'
          return false
        }
        
      } catch (error) {
        console.error('🔍 Address validation error:', error)
        recipientAddressError.value = 'Unable to validate address. Please try again.'
        recipientAddressType.value = ''
        addressValidationState.value = 'invalid'
        return false
      }
    } else {
      // Doesn't match Circular address format
      if (address.length >= 66) {
        recipientAddressError.value = 'Invalid CIRX address format. Please check the address.'
        recipientAddressType.value = ''
        addressValidationState.value = 'invalid'
      }
      return false
    }
  }

  /**
   * Comprehensive purchase validation logic
   * Checks all requirements for enabling the purchase button
   */
  const createCanPurchaseValidator = (options) => {
    const {
      inputAmount,
      recipientAddress,
      loading,
      quoteLoading,
      reverseQuoteLoading,
      isConnected,
      inputBalance,
      inputToken,
      awaitedEthBalance,
      networkFee,
      isBackendConnected,
      hasValidQuote,
      minPurchaseAmount,
      isOtcMode
    } = options

    return computed(() => {
      try {
        // Basic requirements
        const hasAmount = inputAmount.value && parseFloat(inputAmount.value) > 0
        const notLoading = !loading.value && !quoteLoading.value && !reverseQuoteLoading.value
        
        // Address validation - use validation state instead of calling async function
        const addressValid = recipientAddress.value && 
          (addressValidationState.value === 'valid' || !recipientAddressError.value)
        
        // Either connected wallet OR valid recipient address required
        const connected = isConnected?.value || false
        const hasValidRecipient = connected || (recipientAddress.value && addressValid)
        
        // Balance validation - only check if wallet is connected
        const hasSufficientBalance = !connected || (() => {
          const inputAmountNum = parseFloat(inputAmount.value) || 0
          const balanceNum = parseFloat(inputBalance.value) || 0
          
          // For ETH, reserve gas fees (0.01 ETH)
          const gasReserve = inputToken.value === 'ETH' ? 0.01 : 0
          const availableBalance = Math.max(0, balanceNum - gasReserve)
          
          return inputAmountNum <= availableBalance
        })()
        
        // Network fee validation for ETH transactions
        let hasSufficientForFees = true
        if (connected) {
          const ethBal = parseFloat(awaitedEthBalance.value) || 0
          const feeEth = parseFloat(networkFee.value?.eth || '0') || 0
          const inputAmountNum = parseFloat(inputAmount.value) || 0
          
          if (inputToken.value === 'ETH') {
            hasSufficientForFees = ethBal >= (inputAmountNum + (feeEth || 0))
          } else {
            const tokenBal = parseFloat(inputBalance.value) || 0
            hasSufficientForFees = ethBal >= feeEth && tokenBal >= inputAmountNum
          }
        }
        
        // Backend connectivity check
        const backendReady = isBackendConnected.value || false
        
        // Quote validation
        const validQuote = hasValidQuote.value || false
        
        // Minimum amount validation
        const meetsMinimum = !minPurchaseAmount.value || 
          parseFloat(inputAmount.value) >= parseFloat(minPurchaseAmount.value)
        
        // Additional OTC mode validations if applicable
        let otcValidations = true
        if (isOtcMode?.value) {
          // Add OTC-specific validation logic here if needed
          // For now, no additional validations for OTC mode
        }

        const result = hasAmount && 
                      notLoading && 
                      hasValidRecipient && 
                      hasSufficientBalance && 
                      hasSufficientForFees && 
                      backendReady && 
                      validQuote && 
                      meetsMinimum && 
                      otcValidations
        
        // Debug logging for complex validation
        if (process.client && (hasAmount || connected)) {
          console.log('🔧 canPurchase validation:', {
            hasAmount,
            notLoading,
            hasValidRecipient,
            hasSufficientBalance,
            hasSufficientForFees,
            backendReady,
            validQuote,
            meetsMinimum,
            otcValidations,
            result
          })
        }
        
        return result
      } catch (error) {
        console.error('❌ Error in canPurchase computed:', error)
        return false
      }
    })
  }

  /**
   * Create address validation watcher
   * Sets up reactive validation when address changes
   */
  const createAddressWatcher = (recipientAddress, options = {}) => {
    const { immediate = true } = options
    
    return watch(recipientAddress, (newAddress) => {
      // Only do synchronous validation to avoid _withMods errors
      validateRecipientAddressSync(newAddress)
      
      // Async validation can be triggered separately if needed
      // Note: Removed async validation from watcher to prevent Vue reactivity conflicts
    }, { immediate })
  }

  return {
    // Validation functions
    validateNumberInput,
    validateRecipientAddress,
    validateRecipientAddressSync,
    createCanPurchaseValidator,
    createAddressWatcher,
    
    // Validation state
    recipientAddressError,
    recipientAddressType,
    addressValidationState,
    hasClickedEnterAddress,
    
    // Utilities from dependencies
    isValidEthereumAddress,
    isValidSolanaAddress,
    isValidCircularAddress,
    isValidCircularAddressFormat
  }
}