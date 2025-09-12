// UI state management composable
import { ref, computed } from 'vue'

export function iuseUIState() {
  // Core UI state
  const activeTab = ref('liquid')
  const inputAmount = ref('')
  const cirxAmount = ref('')
  const loading = ref(false)
  const loadingText = ref('')
  const quote = ref(null)
  const showChart = ref(false)
  const showStaking = ref(false)
  const showWalletModal = ref(false)
  const showConfirmationModal = ref(false)
  
  // Address and balance state
  const recipientAddress = ref('')
  const recipientAddressError = ref('')
  const recipientAddressType = ref('')
  const isFetchingRecipientBalance = ref(false)
  const hasClickedEnterAddress = ref(false)
  const addressValidationState = ref('idle') // 'idle', 'validating', 'valid', 'invalid'
  
  // Input refs for focus management
  const addressInputRef = ref(null)
  const amountInputRef = ref(null)
  
  // OTC state
  const selectedTier = ref(null)
  const userManuallySelectedTier = ref(false)
  
  // Chart data state
  const chartDataLoading = ref(false)
  const chartDataError = ref(null)
  
  // Mock token balances for testing
  const mockTokenBalances = ref({
    ETH: '5.123456',   // Mock ETH balance
    USDC: '10000.50',  // Mock USDC balance  
    USDT: '7500.25'    // Mock USDT balance
  })
  
  // Computed states
  const isButtonShowingDots = computed(() => {
    // Disable during loading states (transaction processing)
    if (loading.value || quote.value?.loading) return true
    
    // Don't disable if not connected or no address - these are actionable states
    if (!recipientAddress.value || recipientAddress.value.trim() === '') return false
    
    // Disable for the specific "..." conditions (address validation states)
    return (
      addressValidationState.value === 'validating' ||
      (recipientAddress.value && (recipientAddress.value === '0' || (recipientAddress.value.startsWith('0x') && recipientAddress.value.length < 66))) ||
      (recipientAddress.value && recipientAddress.value.length === 66 && recipientAddress.value.startsWith('0x') && addressValidationState.value === 'idle')
    )
  })
  
  const displayCirxBalance = computed(() => {
    // Only show balance if we have a valid recipient address and fetched balance
    if (recipientAddress.value && !recipientAddressError.value) {
      return '0'
    }
    return null // No address or no balance fetched, show "Balance: -"
  })
  
  const shouldPositionLeft = computed(() => {
    // Position dropdowns to prevent overflow outside form boundaries
    return false // Let's try right-aligned first, adjust if needed
  })
  
  // Helper computed for USD calculations
  const getCurrentUsd = (inputAmount, inputToken, livePrices) => {
    const amt = parseFloat(inputAmount) || 0
    const px = livePrices?.[inputToken] || 0
    return +(amt * px).toFixed(2)
  }
  
  return {
    // Core UI state
    activeTab,
    inputAmount,
    cirxAmount,
    loading,
    loadingText,
    quote,
    showChart,
    showStaking,
    showWalletModal,
    showConfirmationModal,
    
    // Address and balance state
    recipientAddress,
    recipientAddressError,
    recipientAddressType,
    isFetchingRecipientBalance,
    hasClickedEnterAddress,
    addressValidationState,
    
    // Input refs
    addressInputRef,
    amountInputRef,
    
    // OTC state
    selectedTier,
    userManuallySelectedTier,
    
    // Chart data state
    chartDataLoading,
    chartDataError,
    
    // Mock balances
    mockTokenBalances,
    
    // Computed states
    isButtonShowingDots,
    displayCirxBalance,
    shouldPositionLeft,
    
    // Helper functions
    getCurrentUsd
  }
}