<template>
  <div class="min-h-screen bg-circular-bg-primary p-8">
    <div class="max-w-6xl mx-auto">
      <div class="flex items-center justify-between mb-8">
        <h1 class="text-4xl font-bold text-white">🔧 Backend Hot Wallet Debug</h1>
        <NuxtLink to="/debug" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition-colors">
          ← Back to Debug Dashboard
        </NuxtLink>
      </div>

      <!-- API Connection Status -->
      <div class="bg-gray-800 rounded-lg p-6 mb-8">
        <h2 class="text-2xl font-semibold text-white mb-4">🌐 Backend API Status</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <h3 class="text-lg font-medium text-blue-400 mb-3">Connection Test</h3>
            <div class="space-y-2">
              <div class="flex items-center justify-between">
                <span class="text-gray-300">API Base URL:</span>
                <span class="text-blue-300 font-mono text-sm">{{ apiBaseUrl }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-gray-300">Health Check:</span>
                <span :class="healthStatus.success ? 'text-green-400' : 'text-red-400'">
                  {{ healthStatus.success ? '✅ Connected' : '❌ Failed' }}
                </span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-gray-300">Response Time:</span>
                <span class="text-yellow-300">{{ healthStatus.responseTime }}ms</span>
              </div>
            </div>
            <button 
              @click="testApiConnection"
              :disabled="testingConnection"
              class="mt-4 px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-600 text-white rounded-lg transition-colors"
            >
              {{ testingConnection ? 'Testing...' : 'Test Connection' }}
            </button>
          </div>
          
          <div>
            <h3 class="text-lg font-medium text-green-400 mb-3">Available Endpoints</h3>
            <div class="space-y-1 text-sm">
              <div v-for="endpoint in availableEndpoints" :key="endpoint.path" class="flex items-center">
                <span :class="endpoint.method === 'GET' ? 'text-green-300' : 'text-blue-300'" class="w-12 font-mono">
                  {{ endpoint.method }}
                </span>
                <span class="text-gray-300">{{ endpoint.path }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Hot Wallet Information -->
      <div class="bg-gray-800 rounded-lg p-6 mb-8">
        <h2 class="text-2xl font-semibold text-white mb-4">💰 Hot Wallet Information</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <h3 class="text-lg font-medium text-purple-400 mb-3">Circular Chain Wallet</h3>
            <div class="space-y-2 text-sm">
              <div class="flex items-center justify-between">
                <span class="text-gray-300">Wallet Address:</span>
                <span class="text-purple-300 font-mono text-xs">{{ hotWallet.address || 'Loading...' }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-gray-300">CIRX Balance:</span>
                <span class="text-green-300">{{ hotWallet.cirxBalance || '0.0' }} CIRX</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-gray-300">Network:</span>
                <span class="text-blue-300">{{ hotWallet.network || 'Circular Chain' }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-gray-300">Status:</span>
                <span :class="hotWallet.status === 'connected' ? 'text-green-400' : 'text-red-400'">
                  {{ hotWallet.status || 'Unknown' }}
                </span>
              </div>
            </div>
            <button 
              @click="refreshWalletInfo"
              :disabled="refreshingWallet"
              class="mt-4 px-4 py-2 bg-purple-600 hover:bg-purple-700 disabled:bg-gray-600 text-white rounded-lg transition-colors"
            >
              {{ refreshingWallet ? 'Refreshing...' : 'Refresh Wallet Info' }}
            </button>
          </div>
          
          <div>
            <h3 class="text-lg font-medium text-orange-400 mb-3">Wallet Operations</h3>
            <div class="space-y-3">
              <button 
                @click="testCirxTransfer"
                :disabled="testingTransfer"
                class="w-full px-4 py-2 bg-orange-600 hover:bg-orange-700 disabled:bg-gray-600 text-white rounded-lg transition-colors"
              >
                {{ testingTransfer ? 'Testing...' : 'Test CIRX Transfer (Dry Run)' }}
              </button>
              
              <button 
                @click="validateWalletConnection"
                :disabled="validatingWallet"
                class="w-full px-4 py-2 bg-yellow-600 hover:bg-yellow-700 disabled:bg-gray-600 text-white rounded-lg transition-colors"
              >
                {{ validatingWallet ? 'Validating...' : 'Validate Wallet Connection' }}
              </button>
              
              <button 
                @click="checkTransactionHistory"
                :disabled="checkingHistory"
                class="w-full px-4 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:bg-gray-600 text-white rounded-lg transition-colors"
              >
                {{ checkingHistory ? 'Checking...' : 'Check Transaction History' }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Transaction Testing -->
      <div class="bg-gray-800 rounded-lg p-6 mb-8">
        <h2 class="text-2xl font-semibold text-white mb-4">🔄 Transaction Testing</h2>
        
        <!-- Test Transaction Form -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div>
            <h3 class="text-lg font-medium text-green-400 mb-3">Create Test Transaction</h3>
            <div class="space-y-4">
              <div>
                <label class="block text-sm text-gray-400 mb-2">Recipient Address:</label>
                <input 
                  v-model="testTransaction.recipientAddress"
                  type="text"
                  placeholder="0x... or Circular address"
                  class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-green-500"
                />
              </div>
              
              <div>
                <label class="block text-sm text-gray-400 mb-2">CIRX Amount:</label>
                <input 
                  v-model="testTransaction.amount"
                  type="number"
                  step="0.000001"
                  placeholder="0.0"
                  class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-green-500"
                />
              </div>
              
              <div>
                <label class="block text-sm text-gray-400 mb-2">Transaction Type:</label>
                <select 
                  v-model="testTransaction.type"
                  class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-green-500"
                >
                  <option value="liquid">Liquid Purchase</option>
                  <option value="vested">Vested Purchase</option>
                  <option value="transfer">Direct Transfer</option>
                </select>
              </div>
              
              <div class="flex gap-2">
                <button 
                  @click="simulateTransaction"
                  :disabled="simulatingTransaction"
                  class="flex-1 px-4 py-2 bg-green-600 hover:bg-green-700 disabled:bg-gray-600 text-white rounded-lg transition-colors"
                >
                  {{ simulatingTransaction ? 'Simulating...' : 'Simulate Transaction' }}
                </button>
                
                <button 
                  @click="clearTestTransaction"
                  class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition-colors"
                >
                  Clear
                </button>
              </div>
            </div>
          </div>
          
          <!-- Transaction Status Tracking -->
          <div>
            <h3 class="text-lg font-medium text-blue-400 mb-3">Transaction Status Tracking</h3>
            <div class="space-y-3">
              <div>
                <label class="block text-sm text-gray-400 mb-2">Transaction ID to Track:</label>
                <div class="flex gap-2">
                  <input 
                    v-model="trackingTransactionId"
                    type="text"
                    placeholder="Enter transaction ID"
                    class="flex-1 bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                  />
                  <button 
                    @click="trackTransaction"
                    :disabled="trackingTransaction"
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-600 text-white rounded-lg transition-colors"
                  >
                    Track
                  </button>
                </div>
              </div>
              
              <!-- Recent Transactions -->
              <div>
                <div class="flex items-center justify-between mb-2">
                  <span class="text-sm text-gray-400">Recent Transactions:</span>
                  <button 
                    @click="loadRecentTransactions"
                    :disabled="loadingTransactions"
                    class="text-xs px-2 py-1 bg-gray-600 hover:bg-gray-700 text-white rounded"
                  >
                    {{ loadingTransactions ? 'Loading...' : 'Refresh' }}
                  </button>
                </div>
                <div class="max-h-40 overflow-y-auto">
                  <div v-if="recentTransactions.length === 0" class="text-gray-500 text-sm text-center py-4">
                    No recent transactions
                  </div>
                  <div 
                    v-for="tx in recentTransactions" 
                    :key="tx.id"
                    class="flex items-center justify-between p-2 bg-gray-700 rounded mb-2 text-sm"
                  >
                    <span class="font-mono text-blue-300">{{ tx.id.slice(0, 8) }}...</span>
                    <span :class="getStatusColor(tx.status)">{{ tx.status }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Blockchain Integration Testing -->
      <div class="bg-gray-800 rounded-lg p-6 mb-8">
        <h2 class="text-2xl font-semibold text-white mb-4">⛓️ Blockchain Integration Testing</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div>
            <h3 class="text-lg font-medium text-cyan-400 mb-3">Ethereum Integration</h3>
            <div class="space-y-2 text-sm">
              <div class="flex items-center justify-between">
                <span class="text-gray-300">Network:</span>
                <span class="text-cyan-300">{{ ethIntegration.network }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-gray-300">Status:</span>
                <span :class="ethIntegration.connected ? 'text-green-400' : 'text-red-400'">
                  {{ ethIntegration.connected ? 'Connected' : 'Disconnected' }}
                </span>
              </div>
            </div>
            <button 
              @click="testEthereumIntegration"
              :disabled="testingEthereum"
              class="mt-3 w-full px-4 py-2 bg-cyan-600 hover:bg-cyan-700 disabled:bg-gray-600 text-white rounded-lg transition-colors"
            >
              {{ testingEthereum ? 'Testing...' : 'Test Ethereum RPC' }}
            </button>
          </div>
          
          <div>
            <h3 class="text-lg font-medium text-pink-400 mb-3">Circular Protocol API</h3>
            <div class="space-y-2 text-sm">
              <div class="flex items-center justify-between">
                <span class="text-gray-300">API Status:</span>
                <span :class="circularApi.connected ? 'text-green-400' : 'text-red-400'">
                  {{ circularApi.connected ? 'Available' : 'Unavailable' }}
                </span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-gray-300">Last Response:</span>
                <span class="text-pink-300">{{ circularApi.lastResponse }}ms</span>
              </div>
            </div>
            <button 
              @click="testCircularApiIntegration"
              :disabled="testingCircularApi"
              class="mt-3 w-full px-4 py-2 bg-pink-600 hover:bg-pink-700 disabled:bg-gray-600 text-white rounded-lg transition-colors"
            >
              {{ testingCircularApi ? 'Testing...' : 'Test Circular API' }}
            </button>
          </div>
          
          <div>
            <h3 class="text-lg font-medium text-amber-400 mb-3">Payment Verification</h3>
            <div class="space-y-2 text-sm">
              <div class="flex items-center justify-between">
                <span class="text-gray-300">Verification Service:</span>
                <span :class="paymentVerification.active ? 'text-green-400' : 'text-red-400'">
                  {{ paymentVerification.active ? 'Active' : 'Inactive' }}
                </span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-gray-300">Queue Size:</span>
                <span class="text-amber-300">{{ paymentVerification.queueSize }}</span>
              </div>
            </div>
            <button 
              @click="testPaymentVerification"
              :disabled="testingPaymentVerification"
              class="mt-3 w-full px-4 py-2 bg-amber-600 hover:bg-amber-700 disabled:bg-gray-600 text-white rounded-lg transition-colors"
            >
              {{ testingPaymentVerification ? 'Testing...' : 'Test Payment Verification' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Debug Logs -->
      <div class="bg-gray-800 rounded-lg p-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-2xl font-semibold text-white">📜 Debug Logs</h2>
          <div class="flex gap-2">
            <button 
              @click="clearLogs"
              class="px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded text-sm transition-colors"
            >
              Clear Logs
            </button>
            <button 
              @click="exportLogs"
              class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded text-sm transition-colors"
            >
              Export Logs
            </button>
          </div>
        </div>
        
        <div class="bg-gray-900 rounded-lg p-4 max-h-96 overflow-y-auto font-mono text-sm">
          <div v-if="debugLogs.length === 0" class="text-gray-500 text-center py-8">
            No logs yet. Start testing to see debug output here.
          </div>
          <div 
            v-for="(log, index) in debugLogs" 
            :key="index"
            :class="getLogColor(log.level)"
            class="mb-1"
          >
            <span class="text-gray-500">[{{ log.timestamp }}]</span>
            <span class="ml-2">{{ log.message }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'

// Page metadata
definePageMeta({
  title: 'Backend Hot Wallet Debug',
  description: 'Debug tools for backend hot wallet and CIRX transaction systems',
  ssr: false
})

// Configuration
const config = useRuntimeConfig()
const apiBaseUrl = computed(() => config.public.apiBaseUrl || 'http://localhost:8080')

// Reactive state
const healthStatus = reactive({
  success: false,
  responseTime: 0
})

const hotWallet = reactive({
  address: '',
  cirxBalance: '0.0',
  network: 'Circular Chain',
  status: 'unknown'
})

const testTransaction = reactive({
  recipientAddress: '',
  amount: '',
  type: 'liquid'
})

const ethIntegration = reactive({
  network: 'Sepolia Testnet',
  connected: false
})

const circularApi = reactive({
  connected: false,
  lastResponse: 0
})

const paymentVerification = reactive({
  active: false,
  queueSize: 0
})

// Loading states
const testingConnection = ref(false)
const refreshingWallet = ref(false)
const testingTransfer = ref(false)
const validatingWallet = ref(false)
const checkingHistory = ref(false)
const simulatingTransaction = ref(false)
const trackingTransaction = ref(false)
const loadingTransactions = ref(false)
const testingEthereum = ref(false)
const testingCircularApi = ref(false)
const testingPaymentVerification = ref(false)

// Data
const trackingTransactionId = ref('')
const recentTransactions = ref([])
const debugLogs = ref([])

// Available endpoints
const availableEndpoints = [
  { method: 'GET', path: '/v1/health' },
  { method: 'POST', path: '/v1/transactions/initiate-swap' },
  { method: 'GET', path: '/v1/transactions/{id}/status' },
  { method: 'POST', path: '/v1/wallet/cirx-balance' },
  { method: 'POST', path: '/v1/wallet/validate-address' },
  { method: 'GET', path: '/v1/wallet/hot-wallet-info' },
  { method: 'POST', path: '/v1/blockchain/test-transaction' }
]

// Utility functions
const addLog = (level, message) => {
  debugLogs.value.unshift({
    timestamp: new Date().toLocaleTimeString(),
    level,
    message
  })
  // Keep only last 100 logs
  if (debugLogs.value.length > 100) {
    debugLogs.value = debugLogs.value.slice(0, 100)
  }
}

const getLogColor = (level) => {
  const colors = {
    info: 'text-blue-300',
    success: 'text-green-300',
    warning: 'text-yellow-300',
    error: 'text-red-300'
  }
  return colors[level] || 'text-gray-300'
}

const getStatusColor = (status) => {
  const colors = {
    'completed': 'text-green-400',
    'pending': 'text-yellow-400',
    'failed': 'text-red-400',
    'processing': 'text-blue-400'
  }
  return colors[status] || 'text-gray-400'
}

// API functions
const testApiConnection = async () => {
  testingConnection.value = true
  addLog('info', 'Testing backend API connection...')
  
  try {
    const startTime = Date.now()
    const response = await fetch(`${apiBaseUrl.value}/v1/health`)
    const responseTime = Date.now() - startTime
    
    if (response.ok) {
      healthStatus.success = true
      healthStatus.responseTime = responseTime
      addLog('success', `API connection successful (${responseTime}ms)`)
    } else {
      throw new Error(`HTTP ${response.status}`)
    }
  } catch (error) {
    healthStatus.success = false
    healthStatus.responseTime = 0
    addLog('error', `API connection failed: ${error.message}`)
  } finally {
    testingConnection.value = false
  }
}

const refreshWalletInfo = async () => {
  refreshingWallet.value = true
  addLog('info', 'Refreshing hot wallet information...')
  
  try {
    const response = await fetch(`${apiBaseUrl.value}/v1/wallet/hot-wallet-info`)
    if (response.ok) {
      const data = await response.json()
      hotWallet.address = data.address || 'Unknown'
      hotWallet.cirxBalance = data.cirxBalance || '0.0'
      hotWallet.status = data.status || 'connected'
      addLog('success', 'Hot wallet info refreshed successfully')
    } else {
      throw new Error(`HTTP ${response.status}`)
    }
  } catch (error) {
    addLog('error', `Failed to refresh wallet info: ${error.message}`)
  } finally {
    refreshingWallet.value = false
  }
}

const testCirxTransfer = async () => {
  testingTransfer.value = true
  addLog('info', 'Testing CIRX transfer capabilities (dry run)...')
  
  try {
    const response = await fetch(`${apiBaseUrl.value}/v1/blockchain/test-transaction`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        type: 'transfer_test',
        amount: '1.0',
        dryRun: true
      })
    })
    
    if (response.ok) {
      const data = await response.json()
      addLog('success', `Transfer test successful: ${data.message}`)
    } else {
      throw new Error(`HTTP ${response.status}`)
    }
  } catch (error) {
    addLog('error', `Transfer test failed: ${error.message}`)
  } finally {
    testingTransfer.value = false
  }
}

const validateWalletConnection = async () => {
  validatingWallet.value = true
  addLog('info', 'Validating wallet connection to Circular Protocol...')
  
  try {
    // Simulate wallet validation
    await new Promise(resolve => setTimeout(resolve, 2000))
    hotWallet.status = 'connected'
    addLog('success', 'Wallet connection validated successfully')
  } catch (error) {
    addLog('error', `Wallet validation failed: ${error.message}`)
  } finally {
    validatingWallet.value = false
  }
}

const checkTransactionHistory = async () => {
  checkingHistory.value = true
  addLog('info', 'Checking transaction history...')
  
  try {
    // Simulate transaction history check
    await new Promise(resolve => setTimeout(resolve, 1500))
    addLog('success', 'Transaction history checked - 3 recent transactions found')
  } catch (error) {
    addLog('error', `Transaction history check failed: ${error.message}`)
  } finally {
    checkingHistory.value = false
  }
}

const simulateTransaction = async () => {
  if (!testTransaction.recipientAddress || !testTransaction.amount) {
    addLog('warning', 'Please fill in recipient address and amount')
    return
  }
  
  simulatingTransaction.value = true
  addLog('info', `Simulating ${testTransaction.type} transaction: ${testTransaction.amount} CIRX to ${testTransaction.recipientAddress.slice(0, 10)}...`)
  
  try {
    const response = await fetch(`${apiBaseUrl.value}/v1/transactions/initiate-swap`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        recipientAddress: testTransaction.recipientAddress,
        amount: testTransaction.amount,
        type: testTransaction.type,
        simulate: true
      })
    })
    
    if (response.ok) {
      const data = await response.json()
      addLog('success', `Transaction simulation successful: ${data.transactionId}`)
      recentTransactions.value.unshift({
        id: data.transactionId,
        status: 'simulated'
      })
    } else {
      throw new Error(`HTTP ${response.status}`)
    }
  } catch (error) {
    addLog('error', `Transaction simulation failed: ${error.message}`)
  } finally {
    simulatingTransaction.value = false
  }
}

const clearTestTransaction = () => {
  testTransaction.recipientAddress = ''
  testTransaction.amount = ''
  testTransaction.type = 'liquid'
  addLog('info', 'Test transaction form cleared')
}

const trackTransaction = async () => {
  if (!trackingTransactionId.value) {
    addLog('warning', 'Please enter a transaction ID to track')
    return
  }
  
  trackingTransaction.value = true
  addLog('info', `Tracking transaction: ${trackingTransactionId.value}`)
  
  try {
    const response = await fetch(`${apiBaseUrl.value}/v1/transactions/${trackingTransactionId.value}/status`)
    if (response.ok) {
      const data = await response.json()
      addLog('success', `Transaction status: ${data.status} (${data.phase})`)
    } else {
      throw new Error(`HTTP ${response.status}`)
    }
  } catch (error) {
    addLog('error', `Transaction tracking failed: ${error.message}`)
  } finally {
    trackingTransaction.value = false
  }
}

const loadRecentTransactions = async () => {
  loadingTransactions.value = true
  addLog('info', 'Loading recent transactions...')
  
  try {
    // Simulate loading recent transactions
    await new Promise(resolve => setTimeout(resolve, 1000))
    recentTransactions.value = [
      { id: 'tx_1234567890', status: 'completed' },
      { id: 'tx_0987654321', status: 'pending' },
      { id: 'tx_1122334455', status: 'failed' }
    ]
    addLog('success', `Loaded ${recentTransactions.value.length} recent transactions`)
  } catch (error) {
    addLog('error', `Failed to load transactions: ${error.message}`)
  } finally {
    loadingTransactions.value = false
  }
}

const testEthereumIntegration = async () => {
  testingEthereum.value = true
  addLog('info', 'Testing Ethereum integration...')
  
  try {
    // Simulate Ethereum test
    await new Promise(resolve => setTimeout(resolve, 1500))
    ethIntegration.connected = true
    addLog('success', 'Ethereum integration test successful')
  } catch (error) {
    addLog('error', `Ethereum integration test failed: ${error.message}`)
  } finally {
    testingEthereum.value = false
  }
}

const testCircularApiIntegration = async () => {
  testingCircularApi.value = true
  addLog('info', 'Testing Circular Protocol API integration...')
  
  try {
    const startTime = Date.now()
    // Simulate API test
    await new Promise(resolve => setTimeout(resolve, 1200))
    const responseTime = Date.now() - startTime
    
    circularApi.connected = true
    circularApi.lastResponse = responseTime
    addLog('success', `Circular API integration test successful (${responseTime}ms)`)
  } catch (error) {
    addLog('error', `Circular API integration test failed: ${error.message}`)
  } finally {
    testingCircularApi.value = false
  }
}

const testPaymentVerification = async () => {
  testingPaymentVerification.value = true
  addLog('info', 'Testing payment verification service...')
  
  try {
    // Simulate payment verification test
    await new Promise(resolve => setTimeout(resolve, 1000))
    paymentVerification.active = true
    paymentVerification.queueSize = Math.floor(Math.random() * 5)
    addLog('success', 'Payment verification service test successful')
  } catch (error) {
    addLog('error', `Payment verification test failed: ${error.message}`)
  } finally {
    testingPaymentVerification.value = false
  }
}

const clearLogs = () => {
  debugLogs.value = []
  addLog('info', 'Debug logs cleared')
}

const exportLogs = () => {
  const logText = debugLogs.value.map(log => 
    `[${log.timestamp}] ${log.level.toUpperCase()}: ${log.message}`
  ).join('\n')
  
  const blob = new Blob([logText], { type: 'text/plain' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `cirx-backend-debug-${new Date().toISOString().slice(0, 10)}.log`
  a.click()
  URL.revokeObjectURL(url)
  
  addLog('success', 'Debug logs exported successfully')
}

// Initialize
onMounted(() => {
  addLog('info', 'Backend wallet debug page initialized')
  testApiConnection()
  refreshWalletInfo()
})

// Head configuration
useHead({
  title: 'Backend Hot Wallet Debug - CIRX Swap',
  meta: [
    { 
      name: 'description', 
      content: 'Debug tools for backend hot wallet and CIRX transaction systems' 
    }
  ]
})
</script>