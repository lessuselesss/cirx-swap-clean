
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
        
        <!-- Combined Sell/Buy Fields with Arrow Overlay -->
        <div class="relative">
          <!-- Sell Field Section -->
          <div>
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

          <!-- Buy Field Section - positioned directly against sell field -->
          <div>
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

          <!-- Arrow - positioned as absolute overlay -->
          <div class="absolute left-1/2 top-1/2 transform -translate-x-1/2 -translate-y-1/2 z-10">
            <div class="p-2 bg-gray-800 rounded-full border border-gray-700 shadow-lg">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" class="text-gray-400">
                <path d="M7 13L12 18L17 13M7 6L12 11L17 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </div>
          </div>
        </div>

        <div class="mb-6"></div>

        <!-- Recipient Address (always shown now per new workflow) -->
        <RecipientAddressInput
          ref="recipientAddressInputRef"
          v-model="recipientAddress"
          :error="recipientAddressError"
          @validate="handleAddressValidate"
        />

        <!-- Quote Details -->
        <SwapQuoteDetails
          v-if="quote"
          :quote="quote"
          :active-tab="activeTab"
          :input-token="inputToken"
          :input-amount="inputAmount"
        />

        <!-- Unified CTA Button -->
        <CallToAction
          :recipient-address="recipientAddress"
          :recipient-address-error="recipientAddressError"
          :input-amount="inputAmount"
          :input-balance="inputBalance"
          :eth-balance="ethBalance"
          :network-fee-eth="networkFee.eth"
          :input-token="inputToken"
          :active-tab="activeTab"
          :loading="loading"
          :loading-text="loadingText"
          :can-purchase="canPurchase"
          :quote="quote"
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
import CallToAction from '~/components/CallToAction.vue'
import { useApiClient } from '~/composables/core/useApiClient.js'
import { useCirxUtils } from '~/composables/useCirxUtils.js'
import { useSwapLogic } from '~/composables/useSwapHandler.js'
import { useErrorHandler } from '~/composables/core/useErrorService.js'
import { useTransactionStatus } from '~/composables/features/useTransactionData.js'
import { useVestedConfig } from '~/composables/useFormattedNumbers.js'
import { useAppKitWallet } from '~/composables/useAppKitWallet.js'
import { useSwapValidation } from '~/composables/features/useSwapValidation.js'

// Use enhanced wallet composable for connection state (includes AppKit singleton)
const { address, isConnected, open } = useAppKitWallet()

// Watch for wallet connection and auto-populate recipient address
watch(address, (newAddress) => {
  if (newAddress && !recipientAddress.value) {
    recipientAddress.value = newAddress
  }
})

let contracts, swapLogic, errorHandler

try {
  const apiClient = useApiClient()
  const cirxUtils = useCirxUtils()
  
  contracts = {
    calculateCirxQuote: cirxUtils.calculateCirxQuote,
    isLoading: apiClient.isLoading
  }
  
  console.log('✅ Unified API client initialized in SwapForm')
} catch (error) {
  console.error('❌ Failed to initialize API client in SwapForm:', error)
  contracts = {
    calculateCirxQuote: () => ({ cirxAmount: '0', platformFee: { token: '0' } }),
    isLoading: ref(false)
  }
}

try {
  swapLogic = useSwapLogic()
  console.log('✅ SwapLogic initialized in SwapForm')
  console.log('🔧 SwapLogic has getTokenPrice:', typeof swapLogic.getTokenPrice)
} catch (error) {
  console.error('❌ Failed to initialize swapLogic in SwapForm:', error)
  swapLogic = {
    calculateQuote: () => null,
    calculateReverseQuote: () => null,
    validateSwap: () => ({ isValid: false, errors: ['Swap logic not available'] }),
    getTokenPrice: (token) => {
      // Fallback token prices
      const fallbackPrices = {
        ETH: 2500,
        USDC: 1,
        USDT: 1,
        SOL: 100,
        CIRX: 1
      }
      return fallbackPrices[token] || 1
    }
  }
}

// Initialize validation composable
let validation
try {
  validation = useSwapValidation()
  console.log('✅ SwapValidation initialized in SwapForm')
} catch (error) {
  console.error('❌ Failed to initialize validation in SwapForm:', error)
  validation = {
    validateRecipientAddress: () => true,
    validateRecipientAddressSync: () => true,
    recipientAddressError: ref(''),
    recipientAddressType: ref(''),
    addressValidationState: ref('idle')
  }
}

// Extract validation state
const { recipientAddressError, recipientAddressType, addressValidationState } = validation

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
// recipientAddressError now comes from validation composable
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

// Vested Configuration
const { otcConfig, discountTiers } = useVestedConfig()
console.log('🔧 Vested config loaded. discountTiers:', discountTiers.value)

// Computed properties
const inputBalance = computed(() => {
  // Wallet functionality removed - return placeholder balance
  return '0.0'
})

// ETH balance for gas fee calculations
const ethBalance = computed(() => {
  // Wallet functionality removed - return placeholder balance
  return '0'
})

// Network fee estimation
const networkFee = computed(() => ({
  eth: '0.01' // Estimated gas fee in ETH - TODO: implement dynamic gas estimation
}))

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
  const hasValidRecipient = recipientAddress.value && !recipientAddressError.value
  
  // Wallet functionality removed - simplified validation
  // Only require amount and valid recipient address
  return hasAmount && notLoading && hasValidRecipient
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

// Use validation from composable - sync validation for immediate feedback
const validateRecipientAddress = (address) => {
  return validation.validateRecipientAddressSync(address)
}

const handleConnectWallet = async () => {
  // Use same logic as AppKit button in header
  try {
    if (window.$appKit && typeof window.$appKit.open === 'function') {
      window.$appKit.open()
    } else if (typeof open === 'function') {
      open()
    } else {
      console.warn('AppKit modal not available')
    }
  } catch (error) {
    console.error('Error opening AppKit modal:', error)
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
    walletConnected: isConnected?.value || false,
    recipientAddress: recipientAddress.value,
    recipientAddressError: recipientAddressError.value,
    canPurchase: canPurchase.value,
    loading: loading.value
  })
  
  // Simplified logic without wallet connection states
  if (!recipientAddress.value) {
    // No address entered - focus address input
    console.log('🎯 Enter Address clicked - attempting to focus CIRX address input')
    await nextTick()
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
  
  if (recipientAddress.value && recipientAddressError.value) {
    // Invalid address - clear and focus address input
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
      false
    )

    if (!validation.isValid) {
      throw new Error(validation.errors.join(', '))
    }

    // Get recipient address (wallet functionality removed)
    const recipient = recipientAddress.value

    // Validate quote
    if (!quote.value) {
      throw new Error('Unable to calculate quote')
    }

    loadingText.value = 'Confirm transaction in wallet...'

    // Step 1: Get the deposit address for the payment
    const depositAddress = contracts.getDepositAddress(inputToken.value, 'ethereum')
    
    // Step 2: Create transaction data for MetaMask
    const totalAmount = quote.value.totalPaymentRequired || inputAmount.value
    
    // Step 3: Execute transaction (wallet functionality removed)
    let txHash

    try {
      // Wallet functionality removed - throw error
      throw new Error('Transaction functionality has been removed. Wallet integration is disabled.')
      
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
                // Use the same logic as server-side BlockchainExplorerService
                const config = useRuntimeConfig()
                const network = config.public.ethereumNetwork || 'mainnet'
                const baseUrl = network === 'sepolia' ? 'https://sepolia.etherscan.io/tx/' : 'https://etherscan.io/tx/'
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
  console.log('🔧 handleTierChange called with tier:', tier)
  console.log('🔧 Current inputAmount before change:', inputAmount.value)
  console.log('🔧 Current inputToken:', inputToken.value)
  
  selectedTier.value = tier
  
  // Calculate and set the minimum token amount for this tier
  if (tier && tier.minAmount) {
    console.log('🔧 Setting minimum amount for tier:', tier.minAmount)
    
    // Get current token price for conversion
    const currentPrice = swapLogic.getTokenPrice(inputToken.value)
    console.log('🔧 Retrieved token price:', currentPrice)
    
    if (currentPrice && currentPrice > 0) {
      // Calculate token amount needed to reach the minimum USD value
      const requiredTokenAmount = tier.minAmount / currentPrice
      const formattedAmount = requiredTokenAmount.toFixed(6)
      
      console.log('🔧 Calculated token amount:', {
        minUsdAmount: tier.minAmount,
        currentPrice,
        requiredTokenAmount,
        formattedAmount
      })
      
      // Update the input amount - this should trigger the reactive binding
      const oldValue = inputAmount.value
      inputAmount.value = formattedAmount
      lastUpdatedField.value = 'input'
      
      console.log('🔧 inputAmount updated:', {
        oldValue,
        newValue: inputAmount.value,
        actuallyChanged: oldValue !== inputAmount.value
      })
      
      // Force reactivity by triggering the change after a tick
      nextTick(() => {
        console.log('🔧 nextTick: inputAmount is now:', inputAmount.value)
      })
      
    } else {
      console.warn('🔧 Unable to calculate token amount: no valid price')
    }
  }
}

// DEBUG: Watch inputAmount changes
watch(inputAmount, (newValue, oldValue) => {
  console.log('🔧 inputAmount changed:', { oldValue, newValue })
}, { immediate: false })

// Watch for tab or token changes to reset field tracking and recalculate
watch([activeTab, inputToken], () => {
  // Reset to input field priority when tab/token changes
  lastUpdatedField.value = 'input'
  
  // Wallet functionality removed - no token syncing needed
  
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

// Wallet functionality removed - no token validation needed

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