<template>
  <div class="min-h-screen bg-circular-bg-primary">
    <!-- Header -->
    <header class="bg-transparent backdrop-blur-sm border-b border-gray-800/30 sticky top-0 z-50">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
          <div class="flex items-center gap-2 sm:gap-4">
            <img 
              src="/circular-logo.svg" 
              alt="Circular Protocol" 
              class="h-8 w-auto"
            />
            <span class="text-xs sm:text-sm hidden sm:block text-gray-400">History</span>
          </div>
          <div class="flex items-center gap-2 sm:gap-4">
            <!-- Navigation -->
            <NuxtLink 
              to="/swap" 
              class="px-3 py-2 text-gray-400 hover:text-white transition-colors text-sm font-medium"
            >
              Swap
            </NuxtLink>
            <!-- Multi-Wallet connection -->
            <MultiWalletButton />
          </div>
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <div class="max-w-6xl mx-auto p-4 md:p-8">
      <!-- Page Title -->
      <div class="mb-8">
        <h1 class="text-3xl font-bold text-white mb-2">Transaction History</h1>
        <p class="text-gray-400">View your CIRX purchase and vesting history</p>
      </div>

      <!-- Wallet Connection Check -->
      <div v-if="!isConnected" class="text-center py-16">
        <div class="bg-gray-800 border border-gray-700 rounded-xl p-8 max-w-md mx-auto">
          <div class="w-16 h-16 bg-gray-700 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24" class="text-gray-400">
              <path d="M12 2C13.1 2 14 2.9 14 4C14 5.1 13.1 6 12 6C10.9 6 10 5.1 10 4C10 2.9 10.9 2 12 2M21 9V7L15 1L13.5 2.5L16.17 5.33C15.24 5.1 14.25 5 13.17 5H10.83C9.75 5 8.76 5.1 7.83 5.33L10.5 2.5L9 1L3 7V9C3 10.66 4.34 12 6 12H8L8 21C8 21.6 8.4 22 9 22H15C15.6 22 16 21.6 16 21L16 12H18C19.66 12 21 10.66 21 9Z"/>
            </svg>
          </div>
          <h3 class="text-xl font-semibold text-white mb-2">Connect Your Wallet</h3>
          <p class="text-gray-400 mb-6">Connect your wallet to view your transaction history and vesting positions.</p>
          <div class="flex justify-center">
            <MultiWalletButton />
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div v-else-if="isLoading" class="text-center py-16">
        <div class="bg-gray-800 border border-gray-700 rounded-xl p-8 max-w-md mx-auto">
          <div class="w-16 h-16 bg-gray-700 rounded-full flex items-center justify-center mx-auto mb-4 animate-pulse">
            <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24" class="text-gray-400">
              <path d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/>
            </svg>
          </div>
          <h3 class="text-xl font-semibold text-white mb-2">Loading Transaction History</h3>
          <p class="text-gray-400">Fetching your CIRX transactions...</p>
        </div>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="text-center py-16">
        <div class="bg-red-900/20 border border-red-800 rounded-xl p-8 max-w-md mx-auto">
          <div class="w-16 h-16 bg-red-800/30 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24" class="text-red-400">
              <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
            </svg>
          </div>
          <h3 class="text-xl font-semibold text-white mb-2">
            {{ getErrorTitle(lastError) }}
          </h3>
          <p class="text-gray-400 mb-4">{{ error }}</p>
          
          <!-- Error actions -->
          <div class="flex flex-col gap-2">
            <button 
              v-if="isErrorRetryable(lastError)"
              @click="retryLoadUserData" 
              :disabled="retryInProgress"
              class="px-4 py-2 bg-red-600 hover:bg-red-700 disabled:bg-red-800 disabled:cursor-not-allowed text-white rounded-lg font-medium transition-colors"
            >
              <span v-if="retryInProgress">Retrying...</span>
              <span v-else>Try Again</span>
            </button>
            
            <button 
              v-if="serviceStatus === 'unavailable'"
              @click="checkServiceAndLoad"
              class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg font-medium transition-colors"
            >
              Check Service Status
            </button>
          </div>
          
          <!-- Technical details for debugging (collapsed) -->
          <details class="mt-4 text-left">
            <summary class="cursor-pointer text-sm text-gray-400 hover:text-gray-300">
              Technical Details
            </summary>
            <div class="mt-2 p-3 bg-gray-900/50 rounded text-xs text-gray-400 font-mono">
              <div v-if="lastError">Error Code: {{ lastError.code || 'UNKNOWN' }}</div>
              <div v-if="lastError">Timestamp: {{ lastError.timestamp || new Date().toISOString() }}</div>
              <div>Service Status: {{ serviceStatus }}</div>
              <div>User Address: {{ address || 'Not connected' }}</div>
            </div>
          </details>
        </div>
      </div>

      <!-- Service Unavailable State -->
      <div v-else-if="shouldShowServiceUnavailable" class="text-center py-16">
        <div class="bg-orange-900/20 border border-orange-800 rounded-xl p-8 max-w-md mx-auto">
          <div class="w-16 h-16 bg-orange-800/30 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24" class="text-orange-400">
              <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
            </svg>
          </div>
          <h3 class="text-xl font-semibold text-white mb-2">Service Temporarily Unavailable</h3>
          <p class="text-gray-400 mb-4">
            The transaction history service is currently unavailable. Your transaction data is safe, 
            but we can't display it right now.
          </p>
          <div class="flex flex-col gap-2">
            <button 
              @click="checkServiceAndLoad"
              class="px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white rounded-lg font-medium transition-colors"
            >
              Check Service Status
            </button>
            <p class="text-sm text-gray-500 mt-2">
              Your wallet balance and trading functionality remain unaffected.
            </p>
          </div>
        </div>
      </div>

      <!-- Empty State (Service Available, No Data) -->
      <div v-else-if="shouldShowEmptyState" class="text-center py-16">
        <div class="bg-gray-800 border border-gray-700 rounded-xl p-8 max-w-md mx-auto">
          <div class="w-16 h-16 bg-gray-700 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24" class="text-gray-400">
              <path d="M9 11H7v8h2v-8zm4 0h-2v8h2v-8zm4 0h-2v8h2v-8zm2-7v2H3V4h4V2h6v2h4zm-6 0V2H9v2h6z"/>
            </svg>
          </div>
          <h3 class="text-xl font-semibold text-white mb-2">No Transactions Yet</h3>
          <p class="text-gray-400 mb-4">
            You haven't made any CIRX purchases yet. Your transaction history will appear here 
            after your first trade.
          </p>
          <NuxtLink 
            to="/swap" 
            class="inline-flex items-center px-4 py-2 bg-circular-primary text-gray-900 rounded-lg font-medium hover:bg-circular-primary-hover transition-colors"
          >
            Make Your First Purchase
          </NuxtLink>
        </div>
      </div>

      <!-- Transaction History (Service Available & Has Data) -->
      <div v-else-if="shouldShowData" class="space-y-6">
        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
          <!-- Total Purchases -->
          <div class="bg-gradient-to-br from-circular-bg-secondary to-circular-bg-secondary/95 border border-gray-700 rounded-xl p-6">
            <div class="flex items-center justify-between mb-2">
              <h3 class="text-sm font-medium text-gray-400">Total Purchases</h3>
              <div class="w-8 h-8 bg-circular-primary/20 rounded-lg flex items-center justify-center">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24" class="text-circular-primary">
                  <path d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/>
                </svg>
              </div>
            </div>
            <div class="text-2xl font-bold text-white">{{ displayStats.totalPurchases }}</div>
            <div class="text-sm text-gray-400">{{ displayStats.totalUsdValue }}</div>
          </div>

          <!-- Vesting Balance -->
          <div class="bg-gradient-to-br from-circular-bg-secondary to-circular-bg-secondary/95 border border-gray-700 rounded-xl p-6">
            <div class="flex items-center justify-between mb-2">
              <h3 class="text-sm font-medium text-gray-400">Vesting Balance</h3>
              <div class="w-8 h-8 bg-purple-500/20 rounded-lg flex items-center justify-center">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24" class="text-purple-400">
                  <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                </svg>
              </div>
            </div>
            <div class="text-2xl font-bold text-white">{{ displayStats.vestingBalance }}</div>
            <div class="text-sm text-gray-400">{{ displayStats.claimableAmount }}</div>
          </div>

          <!-- Liquid Balance -->
          <div class="bg-gradient-to-br from-circular-bg-secondary to-circular-bg-secondary/95 border border-gray-700 rounded-xl p-6">
            <div class="flex items-center justify-between mb-2">
              <h3 class="text-sm font-medium text-gray-400">Liquid Balance</h3>
              <div class="w-8 h-8 bg-green-500/20 rounded-lg flex items-center justify-center">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24" class="text-green-400">
                  <path d="M17 6V4a2 2 0 00-2-2H9a2 2 0 00-2 2v2H3v14a2 2 0 002 2h14a2 2 0 002-2V6h-4zM9 4h6v2H9V4zm8 16H7V8h10v12z"/>
                </svg>
              </div>
            </div>
            <div class="text-2xl font-bold text-white">{{ displayCirxBalance }}</div>
            <div class="text-sm text-gray-400">Available immediately</div>
          </div>
        </div>

        <!-- Transaction List -->
        <div class="bg-gradient-to-br from-circular-bg-secondary to-circular-bg-secondary/95 border border-gray-700 rounded-xl overflow-hidden">
          <div class="p-6 border-b border-gray-700">
            <h2 class="text-xl font-semibold text-white">Recent Transactions</h2>
          </div>
          
          <div v-if="displayTransactions.length === 0 && serviceStatus === 'available'" class="p-8 text-center">
            <div class="w-16 h-16 bg-gray-700 rounded-full flex items-center justify-center mx-auto mb-4">
              <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24" class="text-gray-400">
                <path d="M9 11H7v8h2v-8zm4 0h-2v8h2v-8zm4 0h-2v8h2v-8zm2-7v2H3V4h4V2h6v2h4zm-6 0V2H9v2h6z"/>
              </svg>
            </div>
            <h3 class="text-lg font-medium text-gray-300 mb-2">No transactions yet</h3>
            <p class="text-gray-400 mb-4">Your CIRX purchases will appear here</p>
            <NuxtLink to="/swap" class="inline-flex items-center px-4 py-2 bg-circular-primary text-gray-900 rounded-lg font-medium hover:bg-circular-primary-hover transition-colors">
              Make your first purchase
            </NuxtLink>
          </div>

          <div v-else-if="displayTransactions.length === 0 && serviceStatus === 'unavailable'" class="p-8 text-center">
            <div class="w-16 h-16 bg-orange-700/30 rounded-full flex items-center justify-center mx-auto mb-4">
              <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24" class="text-orange-400">
                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
              </svg>
            </div>
            <h3 class="text-lg font-medium text-gray-300 mb-2">Service Unavailable</h3>
            <p class="text-gray-400">Cannot load transaction history at this time</p>
          </div>

          <div v-else class="divide-y divide-gray-700">
            <div
              v-for="tx in displayTransactions"
              :key="tx.id"
              class="p-6 hover:bg-gray-800/50 transition-colors"
            >
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                  <!-- Transaction Type Icon -->
                  <div :class="[
                    'w-10 h-10 rounded-lg flex items-center justify-center',
                    tx.type === 'liquid' ? 'bg-green-500/20' : 'bg-purple-500/20'
                  ]">
                    <svg
                      width="20"
                      height="20"
                      fill="currentColor"
                      viewBox="0 0 24 24"
                      :class="tx.type === 'liquid' ? 'text-green-400' : 'text-purple-400'"
                    >
                      <path v-if="tx.type === 'liquid'" d="M17 6V4a2 2 0 00-2-2H9a2 2 0 00-2 2v2H3v14a2 2 0 002 2h14a2 2 0 002-2V6h-4z"/>
                      <path v-else d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                    </svg>
                  </div>
                  
                  <!-- Transaction Details -->
                  <div>
                    <div class="flex items-center gap-2 mb-1">
                      <span class="font-medium text-white">
                        {{ tx.type === 'liquid' ? 'Liquid Purchase' : 'OTC Purchase' }}
                      </span>
                      <span :class="[
                        'px-2 py-1 text-xs rounded-full font-medium',
                        tx.status === 'completed' ? 'bg-green-500/20 text-green-400' :
                        tx.status === 'pending' ? 'bg-yellow-500/20 text-yellow-400' :
                        'bg-red-500/20 text-red-400'
                      ]">
                        {{ tx.status }}
                      </span>
                    </div>
                    <div class="text-sm text-gray-400">
                      {{ tx.date }} • {{ tx.inputAmount }} {{ tx.inputToken }} → {{ tx.cirxAmount }} CIRX
                      <span v-if="tx.discount > 0" class="text-purple-400 ml-2">
                        ({{ tx.discount }}% discount)
                      </span>
                    </div>
                  </div>
                </div>
                
                <!-- Transaction Hash -->
                <div class="flex items-center gap-2">
                  <a
                    :href="getEtherscanUrl(tx.hash)"
                    target="_blank"
                    class="text-gray-400 hover:text-white transition-colors"
                    title="View on Etherscan"
                  >
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                      <path d="M14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3h-7z"/>
                      <path d="M19 19H5V5h7V3H5a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7h-2v7z"/>
                    </svg>
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Vesting Positions (if any OTC purchases exist) -->
        <div v-if="displayVestingPositions.length > 0" class="bg-gradient-to-br from-circular-bg-secondary to-circular-bg-secondary/95 border border-gray-700 rounded-xl overflow-hidden">
          <div class="p-6 border-b border-gray-700">
            <h2 class="text-xl font-semibold text-white">Vesting Positions</h2>
          </div>
          
          <div class="divide-y divide-gray-700">
            <div
              v-for="position in displayVestingPositions"
              :key="position.id"
              class="p-6"
            >
              <div class="flex items-center justify-between mb-4">
                <div>
                  <div class="font-medium text-white mb-1">{{ position.totalAmount }} CIRX</div>
                  <div class="text-sm text-gray-400">Started {{ position.startDate }}</div>
                </div>
                <button
                  v-if="parseFloat(position.claimableAmount) > 0"
                  @click="claimTokens(position.id)"
                  class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg font-medium transition-colors"
                  :disabled="claimingPositions.includes(position.id)"
                >
                  <span v-if="claimingPositions.includes(position.id)">Claiming...</span>
                  <span v-else>Claim {{ position.claimableAmount }} CIRX</span>
                </button>
              </div>
              
              <!-- Progress Bar -->
              <div class="mb-3">
                <div class="flex justify-between text-sm text-gray-400 mb-2">
                  <span>Progress</span>
                  <span>{{ position.progressPercent }}%</span>
                </div>
                <div class="w-full bg-gray-700 rounded-full h-2">
                  <div
                    class="bg-purple-500 h-2 rounded-full transition-all duration-300"
                    :style="{ width: position.progressPercent + '%' }"
                  ></div>
                </div>
              </div>
              
              <div class="grid grid-cols-3 gap-4 text-sm">
                <div>
                  <div class="text-gray-400">Total</div>
                  <div class="text-white font-medium">{{ position.totalAmount }} CIRX</div>
                </div>
                <div>
                  <div class="text-gray-400">Claimed</div>
                  <div class="text-white font-medium">{{ position.claimedAmount }} CIRX</div>
                </div>
                <div>
                  <div class="text-gray-400">Claimable</div>
                  <div class="text-purple-400 font-medium">{{ position.claimableAmount }} CIRX</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
// Page metadata
definePageMeta({
  title: 'Transaction History - Circular CIRX',
  layout: 'default'
})

// Multi-Wallet connection
const { 
  isConnected, 
  getTokenBalance,
  address
} = useWallet()

// Transaction History API integration
const {
  isLoading,
  error,
  formattedTransactions,
  formattedVestingPositions,
  formattedUserStats,
  hasAnyData,
  serviceStatus,
  fetchUserData,
  checkServiceStatus
} = useTransactionHistory()

// Service state
const retryInProgress = ref(false)
const lastError = ref(null)

// Check service status on mount
onMounted(async () => {
  await checkServiceAndLoad()
})

// Watch for address changes
watch(address, async (newAddress) => {
  if (newAddress && serviceStatus.value === 'available') {
    await loadUserData()
  }
})

// Check service status and load data
const checkServiceAndLoad = async () => {
  try {
    const isAvailable = await checkServiceStatus()
    
    if (isAvailable && address.value) {
      await loadUserData()
    }
  } catch (err) {
    console.warn('Failed to check service status:', err)
  }
}

// Load user data from indexer
const loadUserData = async () => {
  if (!address.value) return
  
  try {
    lastError.value = null
    await fetchUserData(address.value)
  } catch (err) {
    lastError.value = err
    console.error('Failed to load user data:', err)
  }
}

// Retry loading user data
const retryLoadUserData = async () => {
  if (retryInProgress.value) return
  
  retryInProgress.value = true
  try {
    await loadUserData()
  } finally {
    retryInProgress.value = false
  }
}

// Error handling helpers
const getErrorTitle = (error) => {
  if (!error) return 'Failed to Load Data'
  
  switch (error.code) {
    case 'NETWORK_ERROR':
      return 'Connection Error'
    case 'TIMEOUT':
      return 'Request Timeout'
    case 'RATE_LIMIT':
      return 'Rate Limited'
    case 'SERVER_ERROR':
      return 'Server Error'
    case 'VALIDATION_ERROR':
      return 'Invalid Request'
    default:
      return 'Failed to Load Data'
  }
}

const isErrorRetryable = (error) => {
  return error?.retryable !== false
}

// Display data - only show real data when available
const displayStats = computed(() => {
  if (serviceStatus.value === 'available' && formattedUserStats.value) {
    return {
      totalPurchases: formattedUserStats.value.totalPurchases,
      totalUsdValue: formattedUserStats.value.totalUsdValue,
      vestingBalance: formattedUserStats.value.vestingBalance,
      claimableAmount: `${formattedVestingPositions.value
        .reduce((sum, pos) => sum + parseFloat(pos.claimableAmount.replace(/,/g, '') || 0), 0)
        .toLocaleString()} CIRX claimable`
    }
  }
  
  // Return empty state when service unavailable
  return {
    totalPurchases: '0 purchases',
    totalUsdValue: 'Service unavailable',
    vestingBalance: '0 CIRX',
    claimableAmount: '0 CIRX claimable'
  }
})

const displayTransactions = computed(() => {
  return serviceStatus.value === 'available' ? formattedTransactions.value : []
})

const displayVestingPositions = computed(() => {
  return serviceStatus.value === 'available' ? formattedVestingPositions.value : []
})

// Check if we should show data (service available and user connected)
const shouldShowData = computed(() => {
  return serviceStatus.value === 'available' && isConnected.value
})

// Check if we should show empty state (service available but no data)
const shouldShowEmptyState = computed(() => {
  return serviceStatus.value === 'available' && isConnected.value && !hasAnyData.value && !isLoading.value && !error.value
})

// Check if we should show service unavailable state
const shouldShowServiceUnavailable = computed(() => {
  return serviceStatus.value === 'unavailable' && isConnected.value && !isLoading.value
})

const displayCirxBalance = computed(() => {
  return isConnected.value ? getTokenBalance('CIRX') : '1,000'
})

// Claiming state
const claimingPositions = ref([])

// Helper function to generate correct Etherscan URL
const getEtherscanUrl = (txHash) => {
  const config = useRuntimeConfig()
  const isTestnet = config.public.testnetMode === true || config.public.testnetMode === 'true'
  return isTestnet ? `https://sepolia.etherscan.io/tx/${txHash}` : `https://etherscan.io/tx/${txHash}`
}

const claimTokens = async (positionId) => {
  if (claimingPositions.value.includes(positionId)) return
  
  try {
    claimingPositions.value.push(positionId)
    
    // Execute claim (mock for now)
    await new Promise(resolve => setTimeout(resolve, 2000))
    
    // Update the position (in real app, this would be fetched from contract)
    const position = mockVestingPositions.value.find(p => p.id === positionId)
    if (position) {
      const claimAmount = parseFloat(position.claimableAmount.replace(',', ''))
      position.claimedAmount = (parseFloat(position.claimedAmount.replace(',', '')) + claimAmount).toLocaleString()
      position.claimableAmount = '0'
    }
    
    alert(`Successfully claimed ${position?.claimableAmount || '0'} CIRX tokens!`)
  } catch (error) {
    console.error('Failed to claim tokens:', error)
    alert(`Failed to claim tokens: ${error.message}`)
  } finally {
    claimingPositions.value = claimingPositions.value.filter(id => id !== positionId)
  }
}

// Head configuration
useHead({
  title: 'Transaction History - Circular CIRX OTC Platform',
  meta: [
    { 
      name: 'description', 
      content: 'View your CIRX token purchase history and manage vesting positions.' 
    }
  ]
})
</script>