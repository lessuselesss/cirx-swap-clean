// Amount input handlers composable
import { ref } from 'vue'
import { useFormattingUtils } from './core/useFormattingUtils.js'

export function iuseAmountHandlers() {
  const { formatWithCommas } = useFormattingUtils()
  const lastEditedField = ref('input') // 'input' or 'output'
  
  const handleInputAmountChange = (value, inputAmount, inputToken, autoSelectNativeToken) => {
    // Auto-select native token if none selected and user starts typing
    if (!inputToken.value && value && parseFloat(value) > 0) {
      if (autoSelectNativeToken) autoSelectNativeToken()
    }
    
    // Set the formatted value
    inputAmount.value = formatWithCommas(value)
    lastEditedField.value = 'input'
  }
  
  const handleCirxAmountChange = (value, cirxAmount, inputToken, autoSelectNativeToken) => {
    // Auto-select native token if none selected and user starts typing in CIRX field
    if (!inputToken.value && value && parseFloat(value) > 0) {
      if (autoSelectNativeToken) autoSelectNativeToken()
    }
    
    // Set the formatted value
    cirxAmount.value = formatWithCommas(value)
    lastEditedField.value = 'output'
  }
  
  const setMaxAmount = (inputToken, inputBalance, isConnected, inputAmount) => {
    console.log('🔧 setMaxAmount called for token:', inputToken.value)
    console.log('🔧 Current inputBalance:', inputBalance.value)
    
    // Can't set max if no wallet connected or no balance available
    if (!isConnected?.value || inputBalance.value === null) {
      console.log('🔧 No wallet connected or no balance available')
      return
    }
    
    // Get the current balance for the selected token
    const balance = parseFloat(inputBalance.value || '0')
    
    if (balance > 0) {
      // Reserve different amounts based on token type
      let maxAmount = 0
      
      if (inputToken.value === 'ETH') {
        // Reserve more ETH for gas fees (5% reserve)
        maxAmount = balance * 0.95
      } else {
        // For ERC-20 tokens (USDC/USDT), reserve less (1% for micro gas)
        maxAmount = balance * 0.99
      }
      
      console.log('🔧 Setting max amount:', {
        token: inputToken.value,
        balance,
        maxAmount,
        formatted: maxAmount.toFixed(6)
      })
      
      inputAmount.value = maxAmount.toFixed(6)
    } else {
      inputAmount.value = '1.0' // Fallback for demo
    }
    
    // Set edit state to input when using max amount
    lastEditedField.value = 'input'
  }
  
  const reverseSwap = () => {
    console.log('Reverse swap not supported yet')
  }
  
  return {
    lastEditedField,
    handleInputAmountChange,
    handleCirxAmountChange,
    setMaxAmount,
    reverseSwap
  }
}