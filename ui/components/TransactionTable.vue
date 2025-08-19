<template>
  <div class="transaction-list-container">
    <h2 class="text-xl font-semibold mb-4 text-white">Transaction Status</h2>
    
    <div class="space-y-4">
      <div 
        v-for="tx in transactions" 
        :key="tx.id" 
        class="transaction-card bg-gray-800 border border-gray-700 rounded-lg p-4 hover:bg-gray-700 transition-colors"
      >
        <!-- Status and Date Row -->
        <div class="flex items-center justify-between mb-3">
          <span :class="getStatusClass(tx.status)" class="inline-flex px-2 py-1 text-xs font-semibold rounded-full">
            {{ tx.status_display }}
          </span>
          <span class="text-sm text-gray-400">
            {{ formatDate(tx.created_at) }}
          </span>
        </div>

        <!-- Transaction Flow -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
          <!-- Ethereum Payment -->
          <div class="text-center">
            <div class="text-xs text-gray-400 mb-1">Payment</div>
            <div class="text-white font-mono text-sm mb-1">
              {{ formatAmount(tx.payment.amount) }} {{ tx.payment.token }}
            </div>
            <div v-if="tx.payment.tx_hash" class="flex items-center justify-center space-x-1">
              <a 
                :href="getEtherscanUrl(tx.payment.tx_hash, tx.payment.chain)" 
                target="_blank" 
                rel="noopener noreferrer"
                class="text-blue-400 hover:text-blue-300 text-xs font-mono"
              >
                {{ formatTxHash(tx.payment.tx_hash) }}
              </a>
              <UIcon name="i-heroicons-arrow-top-right-on-square" class="w-3 h-3 text-gray-400" />
            </div>
            <div v-else class="text-gray-500 text-xs">-</div>
          </div>

          <!-- Arrow -->
          <div class="flex justify-center">
            <UIcon name="i-heroicons-arrow-right" class="w-5 h-5 text-gray-400" />
          </div>

          <!-- CIRX Transfer -->
          <div class="text-center">
            <div class="text-xs text-gray-400 mb-1">CIRX Transfer</div>
            <div class="text-white font-mono text-sm mb-1">
              {{ tx.cirx.amount }} CIRX
            </div>
            <div v-if="tx.cirx.tx_hash" class="flex items-center justify-center space-x-1">
              <a 
                :href="getCircularExplorerUrl(tx.cirx.tx_hash)" 
                target="_blank" 
                rel="noopener noreferrer"
                class="text-green-400 hover:text-green-300 text-xs font-mono"
              >
                {{ formatTxHash(tx.cirx.tx_hash) }}
              </a>
              <UIcon name="i-heroicons-arrow-top-right-on-square" class="w-3 h-3 text-gray-400" />
            </div>
            <div v-else class="text-gray-500 text-xs">
              {{ tx.status === 'completed' ? 'Processing...' : 'Pending' }}
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-if="loading" class="text-center py-4">
      <UIcon name="i-heroicons-arrow-path" class="animate-spin w-6 h-6 text-white" />
      <span class="ml-2 text-white">Loading transactions...</span>
    </div>

    <div v-if="!loading && transactions.length === 0" class="text-center py-8 text-gray-400">
      No transactions found
    </div>

    <div v-if="pagination && pagination.hasMore" class="mt-4 flex justify-center">
      <UButton @click="loadMore" variant="outline">
        Load More
      </UButton>
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
  const baseUrls = {
    'ethereum': 'https://etherscan.io/tx/',
    'eth': 'https://etherscan.io/tx/',
    'mainnet': 'https://etherscan.io/tx/',
    'sepolia': 'https://sepolia.etherscan.io/tx/',
    'goerli': 'https://goerli.etherscan.io/tx/'
  }
  const chainKey = chain?.toLowerCase() || 'ethereum'
  const baseUrl = baseUrls[chainKey] || baseUrls['ethereum']
  return baseUrl + txHash
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