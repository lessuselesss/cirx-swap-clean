<template>
  <div class="min-h-screen bg-circular-bg-primary p-8">
    <div class="max-w-6xl mx-auto">
      <div class="flex items-center justify-between mb-8">
        <h1 class="text-4xl font-bold text-white">🪐 Circular Chain Debug</h1>
        <NuxtLink to="/debug" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition-colors">
          ← Back to Debug Dashboard
        </NuxtLink>
      </div>


      <!-- Circular Chain Connection Status -->
      <div class="bg-gray-800 rounded-lg p-6 mb-8">
        <h2 class="text-2xl font-semibold text-white mb-4">⛓️ Circular Chain Connection</h2>
        
        <!-- Status Component -->
        <div class="mb-6">
          <CircularChainStatus 
            :show-status="true"
            @help-needed="showHelp"
          />
        </div>

        <!-- Detailed Connection Info -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <h3 class="text-lg font-medium text-orange-400 mb-3">Connection Details</h3>
            <div class="space-y-2 text-sm">
              <div class="flex items-center justify-between">
                <span class="text-gray-300">Is Connected:</span>
                <span :class="isCircularChainConnected ? 'text-green-400' : 'text-red-400'">
                  {{ isCircularChainConnected ? 'Yes' : 'No' }}
                </span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-gray-300">CIRX Address:</span>
                <span class="text-blue-300 font-mono text-xs">{{ cirxAddress || 'None' }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-gray-300">CIRX Balance:</span>
                <span class="text-green-300">{{ formatCirxBalance }} CIRX</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-gray-300">Loading Balance:</span>
                <span :class="isLoadingBalance ? 'text-yellow-400' : 'text-gray-400'">
                  {{ isLoadingBalance ? 'Yes' : 'No' }}
                </span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-gray-300">Detecting Chain:</span>
                <span :class="isDetectingChain ? 'text-yellow-400' : 'text-gray-400'">
                  {{ isDetectingChain ? 'Yes' : 'No' }}
                </span>
              </div>
            </div>
          </div>
          
          <div>
            <h3 class="text-lg font-medium text-pink-400 mb-3">Chain Configuration</h3>
            
            <!-- Chain Selector -->
            <div class="mb-4">
              <label class="block text-sm text-gray-400 mb-2">Select Chain:</label>
              <select 
                v-model="selectedChain"
                @change="switchChain"
                class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-pink-500"
              >
                <option value="testnet">Circular Protocol Testnet</option>
                <option value="sandbox">Circular Protocol Sandbox</option>
                <option value="mainnet">Circular Protocol Mainnet (Not Live)</option>
              </select>
            </div>
            
            <div class="space-y-2 text-sm mb-3">
              <div class="flex items-center justify-between">
                <span class="text-gray-300">Chain ID:</span>
                <span class="text-pink-300 font-mono">{{ currentChainConfig.chainId }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-gray-300">Chain Name:</span>
                <span class="text-pink-300">{{ currentChainConfig.chainName }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-gray-300">NAG URL:</span>
                <span class="text-pink-300 text-xs font-mono">{{ currentChainConfig.nagUrl }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-gray-300">Explorer:</span>
                <span class="text-pink-300 text-xs">{{ currentChainConfig.explorerUrl }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-gray-300">Balance Endpoint:</span>
                <span class="text-pink-300 text-xs font-mono">{{ currentChainConfig.endpoints.balance }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-gray-300">Symbol:</span>
                <span class="text-pink-300">{{ currentChainConfig.nativeCurrency.symbol }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-gray-300">Decimals:</span>
                <span class="text-pink-300">{{ currentChainConfig.nativeCurrency.decimals }}</span>
              </div>
            </div>
            
            <div :class="getChainWarningStyle(selectedChain)">
              <div class="text-xs">
                {{ getChainDescription(selectedChain) }}
              </div>
            </div>
          </div>
        </div>

        <!-- Hot Wallet Configuration -->
        <div class="mt-6">
          <h3 class="text-lg font-medium text-amber-400 mb-3">🔑 Hot Wallet Configuration</h3>
          
          <!-- Environment Variables Display -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm mb-4">
            <div>
              <span class="text-gray-300">Backend API URL:</span>
              <span class="ml-2 text-amber-300 font-mono text-xs">{{ config?.public?.apiBaseUrl || 'Not set' }}</span>
            </div>
            <div>
              <span class="text-gray-300">Default Address:</span>
              <span class="ml-2 text-amber-300 font-mono text-xs">{{ config?.public?.defaultCircularAddress || 'Not set' }}</span>
            </div>
          </div>

          <!-- Private Key Input -->
          <div class="mb-4">
            <label class="block text-sm text-gray-400 mb-2">Private Key:</label>
            <div class="flex gap-3">
              <input 
                v-model="privateKey"
                :type="showPrivateKey ? 'text' : 'password'"
                placeholder="Enter Circular chain private key (hex format)"
                class="flex-1 bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-amber-500"
                @input="addLog('debug', `Private key input changed, length: ${privateKey.length}`)"
              />
              <button 
                @click="showPrivateKey = !showPrivateKey"
                class="px-3 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition-colors"
                title="Toggle visibility"
              >
                {{ showPrivateKey ? '👁️' : '🔒' }}
              </button>
            </div>
            <div class="text-xs text-gray-400 mt-1">
              This will be used to derive the public key and address for testing
              <span v-if="privateKey" class="text-amber-300">(Current length: {{ privateKey.length }})</span>
            </div>
          </div>

          <!-- Public Key Input -->
          <div class="mb-4">
            <label class="block text-sm text-gray-400 mb-2">Public Key:</label>
            <input 
              v-model="publicKey"
              type="text"
              placeholder="Enter Circular chain public key"
              class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-amber-500"
            />
            <div class="text-xs text-gray-400 mt-1">
              Enter the corresponding public key for the private key
            </div>
          </div>

          <!-- Address Input -->
          <div class="mb-4">
            <label class="block text-sm text-gray-400 mb-2">Circular Address:</label>
            <input 
              v-model="hotWalletAddress"
              type="text"
              placeholder="Enter Circular chain address corresponding to the private key"
              class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-amber-500"
              @input="addLog('debug', `Hot wallet address changed, length: ${hotWalletAddress.length}`)"
            />
            <div class="text-xs text-gray-400 mt-1">
              This should be the address that corresponds to the private key above
              <span v-if="hotWalletAddress" class="text-amber-300">(Current length: {{ hotWalletAddress.length }})</span>
            </div>
          </div>

          <!-- Debug Button States -->
          <div class="mb-3 text-xs text-gray-400">
            <div>Button States Debug:</div>
            <div>• Private Key: {{ privateKey ? `${privateKey.length} chars` : 'empty' }}</div>
            <div>• Public Key: {{ publicKey ? `${publicKey.length} chars` : 'empty' }}</div>
            <div>• Hot Wallet Address: {{ hotWalletAddress ? `${hotWalletAddress.length} chars` : 'empty' }}</div>
            <div>• Setting Keys: {{ settingKeys }}</div>
            <div>• Validate Button Enabled: {{ !(!privateKey || !publicKey || !hotWalletAddress) }}</div>
            <div>• Set Wallet Button Enabled: {{ !(!privateKey || !hotWalletAddress || settingKeys) }}</div>
          </div>

          <!-- Action Buttons -->
          <div class="flex gap-3 flex-wrap">
            <button 
              @click="validateHotWalletKeys"
              :disabled="!privateKey || !publicKey || !hotWalletAddress"
              class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-600 text-white rounded-lg transition-colors"
            >
              Validate Keys
            </button>
            
            <button 
              @click="setHotWalletKeys"
              :disabled="!privateKey || !hotWalletAddress || settingKeys"
              class="px-4 py-2 bg-green-600 hover:bg-green-700 disabled:bg-gray-600 text-white rounded-lg transition-colors"
            >
              {{ settingKeys ? 'Setting...' : 'Set Hot Wallet' }}
            </button>
            
            <button 
              @click="clearHotWalletKeys"
              class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors"
            >
              Clear All Keys
            </button>
            
            <div v-if="config?.public?.defaultCircularAddress" class="ml-auto">
              <button 
                @click="useDefaultAddress"
                :disabled="settingAddress"
                class="px-3 py-1 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-600 text-white rounded text-sm transition-colors"
              >
                {{ settingAddress ? 'Loading...' : 'Use Default Env Address' }}
              </button>
            </div>
          </div>

          <!-- Security Warning -->
          <div class="bg-red-900/20 border border-red-700 rounded p-3 mt-4">
            <div class="text-red-400 text-xs">
              ⚠️ <strong>Security Warning:</strong> Private keys entered here are for testing only. Never use real private keys with funds in a debug interface.
            </div>
          </div>
        </div>

        <!-- Manual Address Input -->
        <div class="mt-6">
          <h3 class="text-lg font-medium text-blue-400 mb-3">Manual Address Input</h3>
          <div class="flex gap-3">
            <input 
              v-model="manualCircularAddress"
              type="text"
              placeholder="Enter Circular chain address (e.g., 0x5e9784e938a527625dde0c4f88bede4d86f8ab025377c1c5f3624135bbcdc5bb)"
              class="flex-1 bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
            <button 
              @click="setManualAddress"
              :disabled="!manualCircularAddress || settingAddress"
              class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-600 text-white rounded-lg transition-colors"
            >
              {{ settingAddress ? 'Setting...' : 'Set Address' }}
            </button>
          </div>
          <div class="text-xs text-gray-400 mt-2">
            This will validate the address and simulate connecting to test balance fetching
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-wrap gap-3 mt-6">
          <button 
            @click="detectCircularChain"
            :disabled="isDetectingChain"
            class="px-4 py-2 bg-orange-600 hover:bg-orange-700 disabled:bg-gray-600 text-white rounded-lg transition-colors"
          >
            {{ isDetectingChain ? 'Detecting...' : 'Detect Circular Chain' }}
          </button>
          
          <button 
            @click="fetchCirxBalance"
            :disabled="isLoadingBalance || !cirxAddress"
            class="px-4 py-2 bg-green-600 hover:bg-green-700 disabled:bg-gray-600 text-white rounded-lg transition-colors"
          >
            {{ isLoadingBalance ? 'Loading...' : 'Refresh Balance' }}
          </button>
          
          <button 
            @click="testAddressValidation"
            :disabled="!manualCircularAddress"
            class="px-4 py-2 bg-purple-600 hover:bg-purple-700 disabled:bg-gray-600 text-white rounded-lg transition-colors"
          >
            Test Address Validation
          </button>
          
          <button 
            @click="clearCircularAddress"
            :disabled="!cirxAddress"
            class="px-4 py-2 bg-red-600 hover:bg-red-700 disabled:bg-gray-600 text-white rounded-lg transition-colors"
          >
            Clear Address
          </button>
        </div>
      </div>

      <!-- CIRX Balance Testing -->
      <div class="bg-gray-800 rounded-lg p-6 mb-8">
        <h2 class="text-2xl font-semibent text-white mb-4">💰 CIRX Balance Testing</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <h3 class="text-lg font-medium text-green-400 mb-3">Current Balance</h3>
            <div class="bg-gray-900 rounded-lg p-4">
              <div class="text-center">
                <div class="text-3xl font-bold text-green-400 mb-2">{{ formatCirxBalance }}</div>
                <div class="text-sm text-gray-400">CIRX</div>
                <div class="text-xs text-gray-500 mt-2">{{ cirxAddress || 'No address' }}</div>
              </div>
            </div>
            
            <div class="mt-4 space-y-2">
              <button 
                @click="testBalanceFetching"
                :disabled="testingBalance"
                class="w-full px-4 py-2 bg-green-600 hover:bg-green-700 disabled:bg-gray-600 text-white rounded-lg transition-colors"
              >
                {{ testingBalance ? 'Testing...' : 'Test Balance Fetching' }}
              </button>
              
              <button 
                @click="testRpcBalance"
                :disabled="testingRpc"
                class="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-600 text-white rounded-lg transition-colors"
              >
                {{ testingRpc ? 'Testing...' : 'Test NAG Balance Call' }}
              </button>
            </div>
          </div>
          
          <div>
            <h3 class="text-lg font-medium text-yellow-400 mb-3">Balance Test Results</h3>
            <div class="bg-gray-900 rounded-lg p-4 max-h-64 overflow-y-auto">
              <div v-if="balanceTestResults.length === 0" class="text-gray-500 text-center py-4">
                No balance tests run yet
              </div>
              <div 
                v-for="(result, index) in balanceTestResults" 
                :key="index"
                class="mb-2 p-2 bg-gray-800 rounded text-sm"
              >
                <div class="flex items-center justify-between">
                  <span class="font-mono text-blue-300">{{ result.method }}</span>
                  <span :class="result.success ? 'text-green-400' : 'text-red-400'">
                    {{ result.success ? '✅' : '❌' }}
                  </span>
                </div>
                <div class="text-gray-400 text-xs mt-1">{{ result.result }}</div>
                <div class="text-gray-500 text-xs">{{ result.timestamp }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- UX Guidance Display -->
      <div class="bg-gray-800 rounded-lg p-6 mb-8">
        <h2 class="text-2xl font-semibold text-white mb-4">🎯 UX Guidance</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <h3 class="text-lg font-medium text-indigo-400 mb-3">Current Guidance</h3>
            <div class="bg-gray-900 rounded-lg p-4">
              <div class="space-y-2">
                <div class="flex items-center justify-between">
                  <span class="text-gray-300">Status:</span>
                  <span :class="getStatusColor(getUxGuidance.status)" class="font-medium">
                    {{ getUxGuidance.status }}
                  </span>
                </div>
                <div class="text-gray-400 text-sm">{{ getUxGuidance.message }}</div>
                <div v-if="getUxGuidance.action" class="flex items-center justify-between">
                  <span class="text-gray-300">Suggested Action:</span>
                  <span class="text-blue-400">{{ getUxGuidance.action }}</span>
                </div>
              </div>
            </div>
          </div>
          
          <div>
            <h3 class="text-lg font-medium text-teal-400 mb-3">Connection Error</h3>
            <div class="bg-gray-900 rounded-lg p-4">
              <div v-if="chainConnectionError" class="text-red-400 text-sm">
                {{ chainConnectionError }}
              </div>
              <div v-else class="text-gray-500 text-sm text-center py-2">
                No connection errors
              </div>
            </div>
            
            <button 
              v-if="chainConnectionError"
              @click="clearError"
              class="mt-2 w-full px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors"
            >
              Clear Error
            </button>
          </div>
        </div>
      </div>

      <!-- Debug Logs -->
      <div class="bg-gray-800 rounded-lg p-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-2xl font-semibold text-white">🔬 Debug Logs</h2>
          <div class="flex gap-2">
            <button 
              @click="refreshExtensionData"
              class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded text-sm transition-colors"
            >
              Refresh
            </button>
            <button 
              @click="clearLogs"
              class="px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded text-sm transition-colors"
            >
              Clear Logs
            </button>
          </div>
        </div>
        
        <div class="bg-gray-900 rounded-lg p-4 max-h-80 overflow-y-auto font-mono text-xs">
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
import { ref, computed, onMounted } from 'vue'
import { useCircularChain } from '~/composables/useCircularChain'

// Page metadata
definePageMeta({
  title: 'Circular Chain Debug',
  description: 'Debug tools for Circular chain integration and NAG API testing',
  ssr: false
})

// Configuration (client-side safe)
const config = ref({})

// Initialize config on client side only  
onMounted(() => {
  try {
    const runtimeConfig = useRuntimeConfig()
    config.value = runtimeConfig
  } catch (error) {
    console.warn('Failed to load runtime config:', error)
    config.value = { public: {} }
  }
})

// Toast callback for circular chain composable
const showToast = (toast) => {
  console.log(`🍞 Toast: ${toast.type} - ${toast.title}: ${toast.message}`)
  addLog(toast.type, `${toast.title}: ${toast.message}`)
}

// Use Circular chain composable with toast callback
const {
  cirxAddress,
  isCircularChainConnected,
  cirxBalance,
  isLoadingBalance,
  chainConnectionError,
  isDetectingChain,
  isCircularChainAvailable,
  formatCirxBalance,
  getUxGuidance,
  detectCircularChain,
  fetchCirxBalance,
  CIRCULAR_CHAIN_CONFIG
} = useCircularChain(showToast)

// Local state
const testingBalance = ref(false)
const testingRpc = ref(false)
const settingAddress = ref(false)
const manualCircularAddress = ref('')
const selectedChain = ref('testnet')

// Hot wallet key management
const privateKey = ref('')
const publicKey = ref('')
const hotWalletAddress = ref('')
const showPrivateKey = ref(false)
const settingKeys = ref(false)
const balanceTestResults = ref([])
const debugLogs = ref([])

// Chain configurations with correct NAG URLs
const CHAIN_CONFIGS = {
  testnet: {
    chainId: 'circular-testnet', // Circular Protocol doesn't use hex chain IDs
    chainName: 'Circular Protocol Testnet',
    nativeCurrency: {
      name: 'CIRX',
      symbol: 'CIRX',
      decimals: 18
    },
    nagUrl: 'https://nag.circularlabs.io/NAG.php?cep=', // Network Access Gateway for Testnet
    explorerUrl: 'https://explorer.circularlabs.io', // Circular Labs Explorer
    endpoints: {
      balance: 'GetWalletBalance_',
      transaction: 'SendTransaction_',
      account: 'GetWallet_'
    }
  },
  sandbox: {
    chainId: 'circular-sandbox',
    chainName: 'Circular Protocol Sandbox', 
    nativeCurrency: {
      name: 'CIRX',
      symbol: 'CIRX',
      decimals: 18
    },
    nagUrl: 'https://nag.circularlabs.io/NAG.php?cep=', // Same NAG, different endpoints
    explorerUrl: 'https://explorer.circularlabs.io',
    endpoints: {
      balance: 'GetWalletBalance_',
      transaction: 'SendTransaction_',
      account: 'GetWallet_'
    }
  },
  mainnet: {
    chainId: 'circular-mainnet',
    chainName: 'Circular Protocol Mainnet',
    nativeCurrency: {
      name: 'CIRX',
      symbol: 'CIRX',
      decimals: 18
    },
    nagUrl: 'https://nag.circularlabs.io/NAG_Mainnet.php?cep=', // Mainnet NAG
    explorerUrl: 'https://explorer.circularlabs.io',
    endpoints: {
      balance: 'GetWalletBalance_',
      transaction: 'SendTransaction_',
      account: 'GetWallet_'
    }
  }
}

// Computed
const currentChainConfig = computed(() => {
  return CHAIN_CONFIGS[selectedChain.value] || CHAIN_CONFIGS.testnet
})

const apiBaseUrl = computed(() => {
  return config.value?.public?.apiBaseUrl || 'http://localhost:8080'
})

const getStatusColor = (status) => {
  const colors = {
    'detecting': 'text-blue-400',
    'connected': 'text-green-400',
    'saturn-no-circular': 'text-yellow-400',
    'no-circular': 'text-blue-400',
    'error': 'text-red-400'
  }
  return colors[status] || 'text-gray-400'
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

const getChainWarningStyle = (chain) => {
  const styles = {
    testnet: 'bg-blue-900/20 border border-blue-700 rounded p-2',
    sandbox: 'bg-yellow-900/20 border border-yellow-700 rounded p-2', 
    mainnet: 'bg-red-900/20 border border-red-700 rounded p-2'
  }
  return styles[chain] || styles.testnet
}

const getChainDescription = (chain) => {
  const descriptions = {
    testnet: '🧪 Testnet: Stable testing environment with persistent data. Use for integration testing.',
    sandbox: '🏖️ Sandbox: Experimental environment for development. Data may be reset frequently.',
    mainnet: '🚫 Mainnet: Production network (not yet live). Do not use real funds.'
  }
  return descriptions[chain] || descriptions.testnet
}

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

const addBalanceTestResult = (method, success, result) => {
  balanceTestResults.value.unshift({
    method,
    success,
    result,
    timestamp: new Date().toLocaleTimeString()
  })
  // Keep only last 20 results
  if (balanceTestResults.value.length > 20) {
    balanceTestResults.value = balanceTestResults.value.slice(0, 20)
  }
}


// Balance testing functions
const testBalanceFetching = async () => {
  testingBalance.value = true
  addLog('info', 'Testing balance fetching methods...')
  
  try {
    // Test 1: Composable balance fetch
    try {
      await fetchCirxBalance()
      addBalanceTestResult('fetchCirxBalance', true, `Balance: ${cirxBalance.value}`)
      addLog('success', 'Composable balance fetch successful')
    } catch (err) {
      addBalanceTestResult('fetchCirxBalance', false, err.message)
      addLog('error', `Composable balance fetch failed: ${err.message}`)
    }
    
    // Only test composable balance fetch (Saturn logic removed)
    
  } catch (error) {
    addLog('error', `Balance testing error: ${error.message}`)
  } finally {
    testingBalance.value = false
  }
}

const testRpcBalance = async () => {
  testingRpc.value = true
  addLog('info', 'Testing NAG balance call via backend proxy...')
  
  try {
    const config = currentChainConfig.value
    const testAddress = cirxAddress.value || '0x5e9784e938a527625dde0c4f88bede4d86f8ab025377c1c5f3624135bbcdc5bb'
    
    addLog('info', `Testing NAG: ${config.nagUrl}`)
    addLog('info', `Endpoint: ${config.endpoints.balance}`)
    addLog('info', `Test Address: ${testAddress}`)
    
    // Use backend proxy to avoid CORS issues
    const backendUrl = config?.public?.apiBaseUrl || 'http://localhost:8080'
    const proxyUrl = `${backendUrl}/api/v1/debug/nag-balance`
    
    const requestBody = {
      nagUrl: config.nagUrl,
      endpoint: config.endpoints.balance,
      address: testAddress
    }
    
    addLog('info', `Backend Proxy URL: ${proxyUrl}`)
    addLog('info', `Request Body: ${JSON.stringify(requestBody, null, 2)}`)
    
    try {
      const response = await fetch(proxyUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: JSON.stringify(requestBody)
      })
      
      const result = await response.json()
      addLog('info', `Backend Response Status: ${response.status}`)
      addLog('info', `Backend Response: ${JSON.stringify(result, null, 2)}`)
      
      if (response.ok && result.success) {
        addBalanceTestResult('NAG Call (via Backend)', true, `NAG Response: ${JSON.stringify(result.nag_response.body, null, 2)}`)
        addLog('success', 'NAG balance call successful via backend proxy!')
        addLog('info', `NAG URL Used: ${result.nag_request.url}`)
        addLog('info', `NAG Status: ${result.nag_response.status}`)
      } else {
        addBalanceTestResult('NAG Call (via Backend)', false, `Error: ${result.error || 'Unknown error'}`)
        addLog('error', `NAG call failed: ${result.error || result.message || 'Unknown error'}`)
        if (result.nag_response) {
          addLog('error', `NAG Response: ${JSON.stringify(result.nag_response.body)}`)
        }
      }
    } catch (fetchError) {
      addBalanceTestResult('NAG Call (via Backend)', false, `Backend proxy error: ${fetchError.message}`)
      addLog('error', `Backend proxy error: ${fetchError.message}`)
      addLog('info', 'Make sure the backend server is running on the configured API base URL')
    }
    
  } catch (error) {
    addBalanceTestResult('NAG Call', false, error.message)
    addLog('error', `NAG test error: ${error.message}`)
  } finally {
    testingRpc.value = false
  }
}

// Event handlers
const showHelp = () => {
  addLog('info', 'Help requested for Circular chain setup')
  alert('Circular chain connection help - please configure your address manually below.')
}


const clearError = () => {
  chainConnectionError.value = ''
  addLog('info', 'Connection error cleared')
}

const refreshExtensionData = () => {
  addLog('info', 'Debug data refreshed')
}

const clearLogs = () => {
  debugLogs.value = []
  addLog('info', 'Debug logs cleared')
}

// Address validation and setting handlers
const setManualAddress = async () => {
  settingAddress.value = true
  addLog('info', `Setting manual Circular address: ${manualCircularAddress.value}`)
  
  try {
    // Import validation function
    const { validateWalletAddress } = await import('~/utils/validation.js')
    
    // Validate the address
    const validation = validateWalletAddress(manualCircularAddress.value, 'circular')
    
    if (!validation.isValid) {
      addLog('error', `Address validation failed: ${validation.errors.join(', ')}`)
      return
    }
    
    if (validation.detectedType !== 'circular') {
      addLog('warning', `Address detected as ${validation.detectedType} type, not Circular`)
    }
    
    // Set the address manually (simulating connection)
    cirxAddress.value = manualCircularAddress.value
    isCircularChainConnected.value = true
    addLog('success', `Circular address set: ${manualCircularAddress.value}`)
    
    // Try to fetch balance for this address
    addLog('info', 'Attempting to fetch CIRX balance for the address...')
    await fetchCirxBalance()
    
  } catch (error) {
    addLog('error', `Failed to set address: ${error.message}`)
  } finally {
    settingAddress.value = false
  }
}

const testAddressValidation = async () => {
  addLog('info', `Testing address validation for: ${manualCircularAddress.value}`)
  
  try {
    // Import validation functions
    const { validateWalletAddress } = await import('~/utils/validation.js')
    const { isValidCircularAddress } = await import('~/utils/addressFormatting.js')
    
    // Test comprehensive validation
    const validation = validateWalletAddress(manualCircularAddress.value, 'auto')
    addLog('info', `Auto-detection result: ${JSON.stringify(validation)}`)
    
    // Test specific Circular validation
    const isCircular = isValidCircularAddress(manualCircularAddress.value)
    addLog('info', `Circular address validation: ${isCircular}`)
    
    // Test specific validations
    const circularValidation = validateWalletAddress(manualCircularAddress.value, 'circular')
    addLog('info', `Circular-specific validation: ${JSON.stringify(circularValidation)}`)
    
    const ethereumValidation = validateWalletAddress(manualCircularAddress.value, 'ethereum')
    addLog('info', `Ethereum validation: ${JSON.stringify(ethereumValidation)}`)
    
  } catch (error) {
    addLog('error', `Address validation test failed: ${error.message}`)
  }
}

const clearCircularAddress = () => {
  cirxAddress.value = ''
  isCircularChainConnected.value = false
  manualCircularAddress.value = ''
  cirxBalance.value = '0'
  addLog('info', 'Circular address cleared')
}

const useDefaultAddress = async () => {
  if (config.value?.public?.defaultCircularAddress) {
    manualCircularAddress.value = config.value.public.defaultCircularAddress
    addLog('info', `Loaded default address from config: ${config.value.public.defaultCircularAddress}`)
    await setManualAddress()
  } else {
    addLog('warning', 'No default Circular address configured in environment')
  }
}

// Hot wallet key management functions
const validateHotWalletKeys = async () => {
  addLog('info', 'Validating hot wallet keys...')
  
  try {
    // Validate private key format
    if (!privateKey.value) {
      addLog('error', 'Private key is required')
      return
    }
    
    const cleanPrivateKey = privateKey.value.replace(/^0x/, '')
    if (!/^[0-9a-fA-F]{64}$/.test(cleanPrivateKey)) {
      addLog('warning', 'Private key should be 64 hex characters (with or without 0x prefix)')
    } else {
      addLog('success', 'Private key format is valid')
    }
    
    // Validate public key format
    if (!publicKey.value) {
      addLog('warning', 'Public key is not provided')
    } else {
      addLog('success', 'Public key is provided')
    }
    
    // Validate address format using existing validation
    if (!hotWalletAddress.value) {
      addLog('error', 'Circular address is required')
      return
    }
    
    const { validateWalletAddress } = await import('~/utils/validation.js')
    const validation = validateWalletAddress(hotWalletAddress.value, 'circular')
    
    if (validation.isValid) {
      addLog('success', `Address validation passed: ${validation.detectedType} address`)
    } else {
      addLog('error', `Address validation failed: ${validation.errors.join(', ')}`)
    }
    
    addLog('info', 'Key validation complete')
    
  } catch (error) {
    addLog('error', `Key validation failed: ${error.message}`)
  }
}

const setHotWalletKeys = async () => {
  settingKeys.value = true
  addLog('info', 'Setting hot wallet keys for testing...')
  
  try {
    if (!privateKey.value || !hotWalletAddress.value) {
      addLog('error', 'Private key and address are required')
      return
    }
    
    // Validate address first
    await validateHotWalletKeys()
    
    // Set the address as the current Circular address
    cirxAddress.value = hotWalletAddress.value
    isCircularChainConnected.value = true
    addLog('success', `Hot wallet configured with address: ${hotWalletAddress.value}`)
    
    // Try to fetch balance for this hot wallet
    addLog('info', 'Attempting to fetch CIRX balance for hot wallet...')
    await fetchCirxBalance()
    
    // TODO: Send keys to backend for actual hot wallet operations
    addLog('info', 'Hot wallet keys set - ready for backend operations')
    addLog('warning', 'Note: Backend integration for hot wallet operations not yet implemented')
    
  } catch (error) {
    addLog('error', `Failed to set hot wallet keys: ${error.message}`)
  } finally {
    settingKeys.value = false
  }
}

const clearHotWalletKeys = () => {
  privateKey.value = ''
  publicKey.value = ''
  hotWalletAddress.value = ''
  cirxAddress.value = ''
  isCircularChainConnected.value = false
  cirxBalance.value = '0'
  addLog('info', 'Hot wallet keys and connection cleared')
}

// Chain switching function
const switchChain = () => {
  const config = currentChainConfig.value
  addLog('info', `Switching to ${config.chainName} (Network: ${config.chainId})`)
  addLog('info', `NAG URL: ${config.nagUrl}`)
  addLog('info', `Balance Endpoint: ${config.endpoints.balance}`)
  addLog('info', `Explorer: ${config.explorerUrl}`)
  
  // Clear current connection state when switching chains
  cirxAddress.value = ''
  isCircularChainConnected.value = false
  cirxBalance.value = '0'
  
  addLog('success', `Chain switched to ${selectedChain.value}. Connection cleared - please reconnect.`)
}

// Initialize
onMounted(() => {
  addLog('info', 'Circular chain wallet debug page initialized')
  
  // Set initial chain from environment variable
  if (config.value?.public?.circularChainEnvironment) {
    const envChain = config.value.public.circularChainEnvironment
    if (CHAIN_CONFIGS[envChain]) {
      selectedChain.value = envChain
      addLog('info', `Chain initialized from environment: ${envChain}`)
    }
  }
  
  // Saturn wallet detection removed
  
  // Log initial state
  setTimeout(() => {
    addLog('info', `Initial connection state: ${isCircularChainConnected.value}`)
    addLog('info', `Initial address: ${cirxAddress.value || 'None'}`)
    addLog('info', `Initial balance: ${cirxBalance.value} CIRX`)
    addLog('info', `Active chain: ${selectedChain.value} (${currentChainConfig.value.chainName})`)
  }, 1000)
})

// Head configuration
useHead({
  title: 'Circular Chain Debug - CIRX Swap',
  meta: [
    { 
      name: 'description', 
      content: 'Debug tools for Circular chain integration and NAG API testing' 
    }
  ]
})
</script>