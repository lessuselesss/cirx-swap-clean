<template>
  <div class="transaction-list-container">
    <h2 class="text-xl font-semibold mb-4 text-white">Transaction Status</h2>
    
    <div class="space-y-4">
      <div 
        v-for="tx in transactions" 
        :key="tx.id" 
        :class="[
          'transaction-card rounded-lg p-4 hover:bg-gray-700 transition-colors',
          tx.is_test_transaction 
            ? 'bg-gray-800 border border-dashed border-yellow-600 relative' 
            : 'bg-gray-800 border border-gray-700'
        ]"
      >
        <!-- Test Transaction Badge -->
        <div v-if="tx.is_test_transaction" class="absolute top-2 right-2">
          <span class="inline-flex items-center px-2 py-1 text-xs font-bold text-yellow-800 bg-yellow-200 rounded-full">
            🧪 TEST
          </span>
        </div>

        <!-- Status and Date Row -->
        <div class="flex items-center justify-between mb-3">
          <div class="flex items-center space-x-2">
            <span :class="getStatusClass(tx.status)" class="inline-flex px-2 py-1 text-xs font-semibold rounded-full">
              {{ tx.status_display }}
            </span>
          </div>
          <span class="text-sm text-gray-400">
            {{ formatDate(tx.created_at) }}
          </span>
        </div>

        <!-- Transaction Flow -->
        <div :class="['grid grid-cols-1 md:grid-cols-3 gap-4 items-center', tx.is_test_transaction ? 'opacity-80' : '']">
          <!-- Ethereum Payment -->
          <div class="text-left">
            <div class="text-xs text-gray-400 mb-1">Payment</div>
            <div class="text-white font-mono text-sm mb-1">
              {{ formatAmount(tx.payment.amount) }} {{ tx.payment.token }}
            </div>
            <div v-if="tx.payment.tx_hash" class="flex items-center space-x-1">
              <a 
                :href="getEtherscanUrl(tx.payment.tx_hash, tx.payment.chain)" 
                target="_blank" 
                rel="noopener noreferrer"
                :class="[
                  'text-xs font-mono',
                  tx.is_test_transaction 
                    ? 'text-blue-300 hover:text-blue-200' 
                    : 'text-blue-400 hover:text-blue-300'
                ]"
              >
                {{ formatTxHash(tx.payment.tx_hash) }}
              </a>
              <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
              </svg>
            </div>
            <div v-else class="text-gray-500 text-xs">-</div>
          </div>

          <!-- Arrow -->
          <div class="flex justify-center">
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
            </svg>
          </div>

          <!-- CIRX Transfer -->
          <div class="text-left">
            <div class="text-xs text-gray-400 mb-1">CIRX Transfer</div>
            <div class="text-white font-mono text-sm mb-1">
              {{ tx.cirx.amount }} CIRX
            </div>
            <div v-if="tx.cirx.tx_hash" class="flex items-center space-x-1">
              <a 
                :href="getCircularExplorerUrl(tx.cirx.tx_hash)" 
                target="_blank" 
                rel="noopener noreferrer"
                :class="[
                  'text-xs font-mono',
                  tx.is_test_transaction 
                    ? 'text-green-300 hover:text-green-200' 
                    : 'text-green-400 hover:text-green-300'
                ]"
              >
                {{ formatTxHash(tx.cirx.tx_hash) }}
              </a>
              <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
              </svg>
            </div>
            <div v-else class="text-gray-500 text-xs">
              {{ tx.status === 'completed' ? 'Processing...' : 'Pending' }}
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-if="loading" class="text-center py-4">
      <svg class="animate-spin w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
      </svg>
      <span class="ml-2 text-white">Loading transactions...</span>
    </div>

    <div v-if="!loading && transactions.length === 0" class="text-center py-8 text-gray-400">
      No transactions found
    </div>

    <div v-if="pagination && pagination.hasMore" class="mt-4 flex justify-center">
      <button 
        @click="loadMore" 
        class="px-4 py-2 border border-gray-600 hover:border-gray-500 bg-transparent text-white rounded-lg hover:bg-gray-800 transition-colors"
      >
        Load More
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'

const transactions = ref([])
const loading = ref(false)
const pagination = ref(null)
const pollInterval = ref(null)

const fetchTransactions = async () => {
  loading.value = true
  try {
    const config = useRuntimeConfig()
    const response = await fetch(`${config.public.apiBaseUrl}/v1/transactions/table?limit=20`)
    
    if (response.ok) {
      const data = await response.json()
      if (data.success && data.data && data.data.transactions) {
        transactions.value = data.data.transactions
        pagination.value = data.data.pagination
        console.log('Fetched transactions:', data.data.transactions.length)
      } else {
        console.error('Invalid response format:', data)
      }
    } else {
      console.error('Failed to fetch transactions:', response.status, response.statusText)
    }
  } catch (error) {
    console.error('Failed to fetch transactions:', error)
  } finally {
    loading.value = false
  }
}

const loadMore = () => {
  fetchTransactions(true)
}

const formatTxHash = (hash) => {
  if (!hash) return ''
  return `${hash.slice(0, 6)}...${hash.slice(-4)}`
}

const formatAmount = (amount) => {
  if (!amount) return '0'
  // Convert to number and remove trailing zeros
  const num = parseFloat(amount)
  return num.toString()
}

const formatDate = (dateString) => {
  if (!dateString) return ''
  const date = new Date(dateString)
  return date.toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const getEtherscanUrl = (txHash, chain = 'ethereum') => {
  const config = useRuntimeConfig()
  const isTestnet = config.public.testnetMode === true || config.public.testnetMode === 'true'
  const network = config.public.ethereumNetwork || 'mainnet'
  
  // Use environment-driven network configuration
  if (isTestnet || network === 'sepolia') {
    return `https://sepolia.etherscan.io/tx/${txHash}`
  } else {
    return `https://etherscan.io/tx/${txHash}`
  }
}

const getCircularExplorerUrl = (txHash) => {
  // Use sandbox explorer for now
  return `https://sandbox-explorer.circular.net/tx/${txHash}`
}

const getStatusClass = (status) => {
  const statusClasses = {
    'initiated': 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
    'payment_pending': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
    'pending_payment_verification': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
    'payment_verified': 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-300',
    'cirx_transfer_pending': 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300',
    'cirx_transfer_initiated': 'bg-teal-100 text-teal-800 dark:bg-teal-900 dark:text-teal-300',
    'completed': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
    'failed_payment_verification': 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
    'failed_cirx_transfer': 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300'
  }
  return statusClasses[status] || statusClasses['initiated']
}

onMounted(() => {
  fetchTransactions()
  // Poll for updates every 10 seconds
  pollInterval.value = setInterval(() => {
    fetchTransactions()
  }, 10000)
})

onUnmounted(() => {
  if (pollInterval.value) {
    clearInterval(pollInterval.value)
  }
})
</script>

<style scoped>
.transaction-list-container {
  @apply bg-gray-900 rounded-lg shadow-sm p-6;
}

.transaction-card {
  transition: background-color 0.2s ease;
}
</style>