
<template>
  <div class="relative">
    <div class="relative bg-circular-bg-primary/80 backdrop-blur-sm border border-gray-700/50 rounded-2xl p-6 sm:p-8">
      
      <!-- Tab Selection -->
      <SwapTabs 
        v-model="activeTab" 
        :otc-config="otcConfig"
      />

      <!-- Error Display -->
      <ErrorAlert 
        v-if="error" 
        :message="error" 
        @dismiss="clearError"
        class="mb-6"
      />

      <!-- Swap Form -->
      <div>
        
        <!-- Sell Field Section -->
        <div class="mb-6">
          <SwapSellField
            v-model:amount="inputAmount"
            v-model:token="inputToken"
            :balance="inputBalance"
            :loading="loading"
            :active-tab="activeTab"
            @set-max="setMaxAmount"
            @input-changed="handleInputAmountChange"
          />
        </div>

        <!-- Arrow -->
        <div class="flex justify-center -my-3 relative z-10">
          <div class="p-2 bg-gray-800 rounded-full border border-gray-700 shadow-lg">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" class="text-gray-400">
              <path d="M7 13L12 18L17 13M7 6L12 11L17 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
        </div>

        <!-- Buy Field Section -->
        <div class="mb-6">
          <SwapBuyField
            v-model:cirx-amount="cirxAmount"
            :quote="quote"
            :active-tab="activeTab"
            :loading="loading"
            :editable="true"
            :discount-tiers="discountTiers"
            :selected-tier="selectedTier"
            @cirx-changed="handleCirxAmountChange"
            @tier-changed="handleTierChange"
          />
        </div>

        <!-- Recipient Address (always shown now per new workflow) -->
        <RecipientAddressInput
          ref="recipientAddressInputRef"
          v-model="recipientAddress"
          :error="recipientAddressError"
          @validate="validateRecipientAddress"
        />

        <!-- Quote Details -->
        <SwapQuoteDetails
          v-if="quote"
          :quote="quote"
          :active-tab="activeTab"
          :input-token="inputToken"
          :input-amount="inputAmount"
        />

        <!-- Action Button -->
        <SwapActionButton
          :can-purchase="canPurchase"
          :loading="loading"
          :loading-text="loadingText"
          :active-tab="activeTab"
          :wallet-connected="walletStore.isConnected"
          :quote="quote"
          :input-amount="inputAmount"
          :input-balance="inputBalance"
          :input-token="inputToken"
          :eth-balance="awaitedEthBalance"
          :network-fee-eth="networkFee.eth"
          :recipient-address="recipientAddress"
          :recipient-address-error="recipientAddressError"
          @connect-wallet="handleConnectWallet"
          @enter-address="handleEnterAddress"
          @enter-valid-address="handleEnterValidAddress"
          @enter-amount="handleEnterAmount"
          @click="handleSwap"
        />

      </div>

      <!-- Footer Actions -->
      <div class="flex justify-center gap-4 mt-6 pt-6 border-t border-gray-700/50">
        <button
          @click="$emit('show-chart')"
          class="flex items-center gap-2 px-4 py-2 text-sm text-gray-400 hover:text-white transition-colors"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
            <path d="M3 3V21H21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M9 9L12 6L16 10L20 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          View Chart
        </button>
        
        <button
          @click="$emit('show-staking')"
          class="flex items-center gap-2 px-4 py-2 text-sm text-gray-400 hover:text-white transition-colors"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
            <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          Vesting Info
        </button>
      </div>
    </div>

    <!-- Transaction Progress Modal -->
    <UModal v-model="showTransactionProgress" :ui="{ width: 'sm:max-w-2xl' }">
      <div class="p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-semibold text-white">Transaction Progress</h3>
          <UButton
            color="gray"
            variant="ghost"
            size="sm"
            icon="i-heroicons-x-mark-20-solid"
            @click="showTransactionProgress = false"
          />
        </div>
        
        <TransactionProgress
          v-if="currentTransaction"
          :transaction="currentTransaction"
          :phase-config="transactionPhases[currentTransaction.phase]"
          @close="showTransactionProgress = false"
        />
      </div>
    </UModal>
  </div>
</template>

<script setup>
import { ref, computed, watch, inject, onBeforeUnmount, nextTick } from 'vue'
import { validateWalletAddress } from '../utils/validation.js'

// Composables and stores with defensive initialization
let walletStore, contracts, swapLogic, errorHandler

try {
  walletStore = useWalletStore()
  console.log('✅ WalletStore initialized in SwapForm')
} catch (error) {
  console.error('❌ Failed to initialize walletStore in SwapForm:', error)
  // Create a mock store to prevent crashes
  walletStore = {
    isConnected: ref(false),
    isConnecting: ref(false),
    activeWallet: ref(null),
    connectWallet: () => Promise.reject(new Error('Wallet store not available')),
    clearError: () => {},
    setSelectedToken: () => {} // Mock for testing
  }
}

try {
  contracts = useBackendApi()
  console.log('✅ Backend API initialized in SwapForm')
} catch (error) {
  console.error('❌ Failed to initialize backend API in SwapForm:', error)
  contracts = {
    calculateCirxQuote: () => ({ cirxAmount: '0', platformFee: { token: '0' } }),
    initiateSwap: () => Promise.reject(new Error('Backend API not available')),
    isLoading: ref(false)
  }
}

try {
  swapLogic = useSwapLogic()
  console.log('✅ SwapLogic initialized in SwapForm')
} catch (error) {
  console.error('❌ Failed to initialize swapLogic in SwapForm:', error)
  swapLogic = {
    calculateQuote: () => null,
    validateSwap: () => ({ isValid: false, errors: ['Swap logic not available'] })
  }
}

try {
  errorHandler = useErrorHandler()
  console.log('✅ ErrorHandler initialized in SwapForm')
} catch (error) {
  console.error('❌ Failed to initialize errorHandler in SwapForm:', error)
  errorHandler = {
    handleError: (err) => ({ userMessage: err.message || 'An error occurred' }),
    shouldShowAsToast: () => true,
    clearError: () => {}
  }
}

// Toast notifications
const toast = inject('toast')

// Transaction status tracking
const { trackTransaction, transactionPhases, getTransaction } = useTransactionStatus()
const currentTransaction = ref(null)
const showTransactionProgress = ref(false)

// Props and emits
defineEmits(['show-chart', 'show-staking'])

// Local reactive state
const activeTab = ref('liquid')
const inputAmount = ref('')
const inputToken = ref('ETH')
const cirxAmount = ref('')
const recipientAddress = ref('')
const recipientAddressError = ref('')
const loading = ref(false)
const loadingText = ref('')
const error = ref(null)
const selectedTier = ref(null)
const recipientAddressInputRef = ref(null)

// Track which field was last updated to prevent circular updates
const lastUpdatedField = ref('input') // 'input' or 'cirx'

// Debounce timers for preventing rapid updates that lose focus
let debounceTimer = null
let reverseDebounceTimer = null

// OTC Configuration
const { otcConfig, discountTiers } = useOtcConfig()

// Computed properties
const inputBalance = computed(() => {
  if (!walletStore.isConnected) return '0.0'
  
  // Validate wallet supports the selected token
  try {
    walletStore.validateWalletForToken(inputToken.value)
    return contracts.getTokenBalance(inputToken.value)
  } catch (err) {
    return '0.0'
  }
})

// Pure quote calculation without side effects to prevent focus loss
const quote = computed(() => {
  // Calculate quote based on which field was last updated
  if (lastUpdatedField.value === 'input') {
    // Forward calculation: input amount -> CIRX amount
    if (!inputAmount.value || parseFloat(inputAmount.value) <= 0) return null
    
    return contracts.calculateCirxQuote(
      inputAmount.value,
      inputToken.value,
      activeTab.value === 'otc'
    )
    
  } else if (lastUpdatedField.value === 'cirx') {
    // Reverse calculation: CIRX amount -> input amount
    if (!cirxAmount.value || parseFloat(cirxAmount.value) <= 0) return null
    
    const reverseQuote = swapLogic.calculateReverseQuote(
      cirxAmount.value,
      inputToken.value,
      activeTab.value === 'otc',
      selectedTier.value
    )
    
    // Return the forward quote for consistency
    return reverseQuote?.forwardQuote || null
  }
  
  return null
})

// Handle field updates with watchers to preserve focus
watch(quote, (newQuote) => {
  if (!newQuote) return
  
  // CRITICAL: Don't update CIRX field if user is actively typing in it
  // This prevents focus loss during user input
  if (lastUpdatedField.value === 'cirx') return
  
  // Clear any existing debounce timer
  if (debounceTimer) {
    clearTimeout(debounceTimer)
  }
  
  // Debounce updates to prevent rapid re-renders that lose focus
  debounceTimer = setTimeout(() => {
    // Double-check that input field is still the last updated field (user might have switched)
    if (lastUpdatedField.value === 'input' && newQuote.cirxAmount !== cirxAmount.value) {
      // Update CIRX amount from input calculation only if input field was last modified
      cirxAmount.value = newQuote.cirxAmount
    }
    // If user switched to CIRX field during debounce, do NOT update to preserve focus
  }, 100) // 100ms debounce prevents focus loss
}, { immediate: false })

// Handle reverse calculation updates separately
watch(() => [cirxAmount.value, lastUpdatedField.value], ([newCirxAmount, field]) => {
  if (field !== 'cirx' || !newCirxAmount || parseFloat(newCirxAmount) <= 0) return
  
  // Don't update input field if user is actively typing in it
  if (lastUpdatedField.value === 'input') return
  
  const reverseQuote = swapLogic.calculateReverseQuote(
    newCirxAmount,
    inputToken.value,
    activeTab.value === 'otc',
    selectedTier.value
  )
  
  if (reverseQuote && reverseQuote.inputAmount.toString() !== inputAmount.value) {
    // Clear any existing reverse debounce timer
    if (reverseDebounceTimer) {
      clearTimeout(reverseDebounceTimer)
    }
    
    // Debounce reverse calculation updates
    reverseDebounceTimer = setTimeout(() => {
      inputAmount.value = reverseQuote.inputAmount.toFixed(6).replace(/\.?0+$/, '')
    }, 100)
  }
}, { immediate: false })

const canPurchase = computed(() => {
  const hasAmount = inputAmount.value && parseFloat(inputAmount.value) > 0
  const notLoading = !loading.value
  const hasWallet = walletStore.isConnected
  const hasValidRecipient = recipientAddress.value && !recipientAddressError.value
  
  // Balance validation - only check if wallet is connected
  const hasSufficientBalance = !walletStore.isConnected || (() => {
    const inputAmountNum = parseFloat(inputAmount.value) || 0
    const balanceNum = parseFloat(inputBalance.value) || 0
    
    // For ETH, reserve gas fees (0.01 ETH)
    const gasReserve = inputToken.value === 'ETH' ? 0.01 : 0
    const availableBalance = Math.max(0, balanceNum - gasReserve)
    
    return inputAmountNum <= availableBalance
  })()
  
  // All conditions must be met: amount + no loading + wallet connected + valid recipient + sufficient balance
  return hasAmount && notLoading && hasWallet && hasValidRecipient && hasSufficientBalance
})

// Methods
const setMaxAmount = () => {
  const balance = parseFloat(inputBalance.value)
  if (balance > 0) {
    // Reserve a small amount for gas fees if using ETH
    const reserveAmount = inputToken.value === 'ETH' ? 0.001 : 0
    const maxAmount = Math.max(0, balance - reserveAmount)
    inputAmount.value = maxAmount.toString()
  }
}

const validateRecipientAddress = (address) => {
  if (!address) {
    recipientAddressError.value = ''
    return true
  }

  // Use circular-specific validation that only accepts Circular addresses
  const result = validateWalletAddress(address, 'circular')
  
  if (!result.isValid) {
    // Custom error message for rejected Ethereum addresses
    if (address.length === 42 && address.startsWith('0x')) {
      recipientAddressError.value = 'Ethereum addresses are not supported. Please enter a valid CIRX address'
    } else {
      recipientAddressError.value = result.errors[0] || 'Invalid CIRX address format'
    }
    return false
  }

  recipientAddressError.value = ''
  return true
}

const handleConnectWallet = async () => {
  try {
    error.value = null
    
    // Open centralized wallet modal
    try { useWalletStore().openWalletModal() } catch {}
    // Optional: keep a small hint
    toast?.info('Select a wallet to connect.', { title: 'Connect Wallet', autoTimeoutMs: 3000 })
    
  } catch (err) {
    console.error('Wallet connection preparation failed:', err)
    
    // Use simpler error handling to avoid triggering critical error
    toast?.error('Unable to prepare wallet connection. Please refresh the page and try again.', {
      title: 'Connection Error',
      autoTimeoutMs: 5000
    })
  }
}

const handleEnterAddress = () => {
  console.log('🎯 handleEnterAddress called - focusing recipient address input')
  setTimeout(() => {
    if (recipientAddressInputRef.value) {
      console.log('🎯 Calling focusInput on recipient address (from button click)')
      recipientAddressInputRef.value.focusInput()
    } else {
      console.log('❌ recipientAddressInputRef is not available in handleEnterAddress')
    }
  }, 50)
}

const handleEnterValidAddress = () => {
  console.log('🎯 handleEnterValidAddress called - clearing and focusing recipient address input')
  setTimeout(() => {
    if (recipientAddressInputRef.value) {
      recipientAddressInputRef.value.clearAndFocusInput()
    }
  }, 50)
}

const handleEnterAmount = () => {
  console.log('🎯 handleEnterAmount called - focusing amount input')
  // Focus the amount input field (sell field)
  // This one should go to the sell field, which is the expected behavior
}

const handleSwap = async () => {
  console.log('🔥 handleSwap called!', {
    walletConnected: walletStore.isConnected,
    recipientAddress: recipientAddress.value,
    recipientAddressError: recipientAddressError.value,
    canPurchase: canPurchase.value,
    loading: loading.value
  })
  
  // Handle the four CTA states based on wallet connection and address input
  if (!walletStore.isConnected && !recipientAddress.value) {
    // State 1: "Connect" - No wallet + no address
    return handleConnectWallet()
  }
  
  if (!walletStore.isConnected && recipientAddress.value) {
    // State 2: "Connect Wallet" - Has address but no wallet
    return handleConnectWallet()
  }
  
  if (walletStore.isConnected && !recipientAddress.value) {
    // State 3: "Enter Address" - Has wallet but no address
    console.log('🎯 Enter Address clicked - attempting to focus CIRX address input')
    await nextTick()
    // Use setTimeout to delay focus and avoid interference from other event handlers
    setTimeout(() => {
      if (recipientAddressInputRef.value) {
        console.log('🎯 Calling focusInput on recipient address (delayed)')
        recipientAddressInputRef.value.focusInput()
        console.log('🎯 Focus called - checking if focus was applied')
      } else {
        console.log('❌ recipientAddressInputRef is not available')
      }
    }, 50)
    return
  }
  
  if (walletStore.isConnected && recipientAddress.value && recipientAddressError.value) {
    // State 4: "Enter a Valid Address" - Has wallet + invalid address
    await nextTick()
    if (recipientAddressInputRef.value) {
      recipientAddressInputRef.value.clearAndFocusInput()
    }
    return
  }
  
  if (!canPurchase.value) return

  try {
    error.value = null
    loading.value = true
    loadingText.value = 'Preparing transaction...'

    // Validate inputs using error handler
    const validation = swapLogic.validateSwap(
      inputAmount.value,
      inputToken.value,
      recipientAddress.value,
      walletStore.isConnected
    )

    if (!validation.isValid) {
      throw new Error(validation.errors.join(', '))
    }

    // Get recipient address
    const recipient = walletStore.isConnected 
      ? walletStore.activeWallet.address 
      : recipientAddress.value

    // Validate quote
    if (!quote.value) {
      throw new Error('Unable to calculate quote')
    }

    loadingText.value = 'Confirm transaction in wallet...'

    // Step 1: Get the deposit address for the payment
    const depositAddress = contracts.getDepositAddress(inputToken.value, 'ethereum')
    
    // Step 2: Create transaction data for MetaMask
    const totalAmount = quote.value.totalPaymentRequired || inputAmount.value
    
    // Step 3: Execute MetaMask transaction
    let txHash
    if (!walletStore.isConnected) {
      throw new Error('Wallet not connected. Please connect your wallet first.')
    }

    try {
      // Prepare transaction for MetaMask
      const txParams = {
        to: depositAddress,
        value: inputToken.value === 'ETH' ? 
          '0x' + Math.floor(parseFloat(totalAmount) * 1e18).toString(16) : '0x0',
        data: '0x', // Simple transfer, no data needed
      }

      // Request transaction through wallet
      txHash = await walletStore.ethereumWallet?.walletClient?.sendTransaction(txParams)
      
      if (!txHash) {
        throw new Error('Transaction was rejected or failed')
      }

      loadingText.value = 'Processing swap on backend...'

      // Step 4: Call backend API with transaction details
      const swapData = contracts.createSwapTransaction(
        txHash,
        'ethereum',
        recipient,
        totalAmount,
        inputToken.value
      )

      const result = await contracts.initiateSwap(swapData)
      
    } catch (walletError) {
      if (walletError.code === 4001) {
        throw new Error('Transaction was rejected by user')
      }
      throw new Error(`Wallet transaction failed: ${walletError.message}`)
    }

    if (result.success) {
      // Start tracking the transaction with real-time updates
      currentTransaction.value = trackTransaction(result.transaction_id, {
        showToasts: true,
        onStatusChange: (statusData, previousPhase) => {
          console.log(`Transaction ${result.transaction_id} moved from ${previousPhase} to ${statusData.phase}`)
        },
        onComplete: (statusData) => {
          // Clear form only when truly complete
          inputAmount.value = ''
          recipientAddress.value = ''
          
          // Show final success notification
          toast?.success('CIRX tokens successfully transferred to your address!', {
            title: 'Swap Complete',
            autoTimeoutMs: 10000,
            actions: [{
              label: 'View Transaction',
              handler: () => {
                const config = useRuntimeConfig()
                const isTestnet = config.public.testnetMode === true || config.public.testnetMode === 'true'
                const baseUrl = isTestnet ? 'https://sepolia.etherscan.io/tx/' : 'https://etherscan.io/tx/'
                window.open(`${baseUrl}${txHash}`, '_blank')
              },
              primary: false
            }]
          })
        },
        onError: (error) => {
          console.error('Transaction tracking error:', error)
        }
      })
      
      // Show transaction progress modal
      showTransactionProgress.value = true
      
      // Show initial success notification for payment submission
      toast?.success('Payment submitted successfully! Tracking transaction progress...', {
        title: 'Payment Sent',
        autoTimeoutMs: 5000
      })

      // Optionally redirect to transaction page
      // await navigateTo(`/transaction/${result.hash}`)
    }

  } catch (err) {
    console.error('Swap failed:', err)
    
    const processedError = errorHandler.handleError(err, {
      description: `${activeTab.value} swap`,
      retryTransaction: () => handleSwap(),
      retryContract: () => handleSwap()
    })

    if (errorHandler.shouldShowAsToast(processedError)) {
      toast?.error(processedError.userMessage, {
        title: 'Swap Failed',
        actions: processedError.actions,
        autoTimeoutMs: 10000
      })
    } else {
      error.value = processedError.userMessage
    }
  } finally {
    loading.value = false
    loadingText.value = ''
  }
}

const clearError = () => {
  error.value = null
  errorHandler.clearError()
}

// Handle input amount changes
const handleInputAmountChange = () => {
  lastUpdatedField.value = 'input'
}

// Handle CIRX amount changes from SwapBuyField component
const handleCirxAmountChange = () => {
  lastUpdatedField.value = 'cirx'
}

// Handle discount tier selection changes
const handleTierChange = (tier) => {
  selectedTier.value = tier
  
  // Recalculate quote with new tier if we have an input amount
  if (inputAmount.value && parseFloat(inputAmount.value) > 0) {
    lastUpdatedField.value = 'input'
    
    const newQuote = swapLogic.calculateQuote(
      inputAmount.value,
      inputToken.value,
      activeTab.value === 'otc',
      tier // Pass the selected tier
    )
    
    if (newQuote) {
      cirxAmount.value = newQuote.cirxAmount
    }
  }
}

// Watch for tab or token changes to reset field tracking and recalculate
watch([activeTab, inputToken], () => {
  // Reset to input field priority when tab/token changes
  lastUpdatedField.value = 'input'
  
  // Sync selected token to wallet store for header balance display
  try { useWalletStore().setSelectedToken(inputToken.value) } catch {}
  
  // Handle tier selection based on tab
  if (activeTab.value === 'otc') {
    // Set default tier if none selected
    if (!selectedTier.value && discountTiers.value.length > 0) {
      selectedTier.value = discountTiers.value[0]
    }
  } else {
    // Clear tier selection for liquid tab
    selectedTier.value = null
  }
  
  // Recalculate quote if we have an input amount
  if (inputAmount.value && parseFloat(inputAmount.value) > 0) {
    const newQuote = swapLogic.calculateQuote(
      inputAmount.value,
      inputToken.value,
      activeTab.value === 'otc',
      selectedTier.value
    )
    
    if (newQuote) {
      cirxAmount.value = newQuote.cirxAmount
    }
  }
})

// Watch for token changes to validate wallet compatibility
watch([inputToken, () => walletStore.activeChain], () => {
  if (walletStore.isConnected) {
    try {
      walletStore.validateWalletForToken(inputToken.value)
      error.value = null
    } catch (err) {
      const processedError = errorHandler.handleError(err, {
        description: 'Token validation'
      })
      
      // Show as toast for validation errors
      toast?.warning(processedError.userMessage, {
        title: 'Token Not Supported',
        autoTimeoutMs: 4000
      })
      
      // Auto-switch to compatible token
      if (walletStore.activeChain === 'solana' && ['ETH', 'USDC', 'USDT'].includes(inputToken.value)) {
        inputToken.value = 'SOL'
      } else if (walletStore.activeChain === 'ethereum' && inputToken.value === 'SOL') {
        inputToken.value = 'ETH'
      }
    }
  }
})

// Cleanup debounce timers to prevent memory leaks and focus issues
onBeforeUnmount(() => {
  if (debounceTimer) {
    clearTimeout(debounceTimer)
    debounceTimer = null
  }
  if (reverseDebounceTimer) {
    clearTimeout(reverseDebounceTimer)
    reverseDebounceTimer = null
  }
})
</script>