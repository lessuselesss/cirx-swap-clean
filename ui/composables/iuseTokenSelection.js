// Token selection and management composable
import { ref, nextTick } from 'vue'

export function iuseTokenSelection() {
  const inputToken = ref('ETH')
  const showTokenDropdown = ref(false)
  
  const selectToken = (token, lastEditedField) => {
    console.log('🔧 selectToken called with:', token)
    console.log('🔧 Current inputToken before change:', inputToken.value)
    
    // Update the selected token
    inputToken.value = token
    showTokenDropdown.value = false
    
    console.log('🔧 inputToken updated to:', inputToken.value)
    
    // Test: Force reactivity update
    nextTick(() => {
      console.log('🔧 After nextTick - inputToken:', inputToken.value)
      console.log('🔧 DOM should now reflect new token')
    })
    
    // Set edit state to input when token changes
    if (lastEditedField) {
      lastEditedField.value = 'input'
    }
  }
  
  const autoSelectNativeToken = (connectedWallet) => {
    if (connectedWallet === 'phantom') {
      // Phantom wallet - select SOL
      console.log('🪙 Auto-selected SOL for Phantom wallet')
      inputToken.value = 'SOL'
    } else {
      // Ethereum wallets (MetaMask, Coinbase, etc.) - select ETH
      console.log('🪙 Auto-selected ETH for Ethereum wallet')
      inputToken.value = 'ETH'
    }
  }
  
  const forceRefreshBalance = async () => {
    console.log('🔄 Force refreshing balance...')
    // With Wagmi, balance refreshes automatically
    console.log('✅ Balance refresh not needed with Wagmi - auto-refreshes')
  }
  
  return {
    inputToken,
    showTokenDropdown,
    selectToken,
    autoSelectNativeToken,
    forceRefreshBalance
  }
}