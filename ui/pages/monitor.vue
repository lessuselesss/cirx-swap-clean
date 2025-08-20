<template>
  <div class="min-h-screen bg-gradient-to-br from-gray-900 via-purple-900 to-gray-900">
    <!-- Header -->
    <div class="border-b border-purple-800/30 backdrop-blur-lg bg-black/20">
      <div class="container mx-auto px-4 py-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-4">
            <h1 class="text-2xl font-bold bg-gradient-to-r from-purple-400 to-pink-400 bg-clip-text text-transparent">
              IROH Network Monitor
            </h1>
            <div class="flex items-center gap-2">
              <div class="w-2 h-2 rounded-full animate-pulse" 
                   :class="isConnected ? 'bg-green-400' : 'bg-red-400'"></div>
              <span class="text-sm text-gray-400">
                {{ isConnected ? 'Connected' : 'Disconnected' }}
              </span>
            </div>
          </div>
          <div class="text-xs font-mono text-gray-500">
            Node: {{ nodeId ? nodeId.substring(0, 16) + '...' : 'Not initialized' }}
          </div>
        </div>
      </div>
    </div>

    <div class="container mx-auto px-4 py-6">
      <!-- Metrics Grid -->
      <div class="grid grid-cols-4 gap-4 mb-6">
        <MetricCard 
          title="Active Transactions" 
          :value="activeTransactions.size"
          icon="📊"
          color="blue"
          :trend="transactionTrend"
        />
        <MetricCard 
          title="Network Peers" 
          :value="networkPeers"
          icon="🌐"
          color="green"
        />
        <MetricCard 
          title="Messages/sec" 
          :value="messagesPerSecond"
          icon="💬"
          color="purple"
        />
        <MetricCard 
          title="Success Rate" 
          :value="successRate + '%'"
          icon="✅"
          color="emerald"
        />
      </div>

      <!-- Main Content Grid -->
      <div class="grid grid-cols-3 gap-6">
        <!-- Live Transactions -->
        <div class="col-span-2 space-y-4">
          <div class="bg-black/40 backdrop-blur-md rounded-xl border border-purple-800/30 p-6">
            <h2 class="text-lg font-semibold mb-4 text-purple-300">Live Transactions</h2>
            
            <div class="space-y-3 max-h-[600px] overflow-y-auto custom-scrollbar">
              <TransactionCard
                v-for="[id, tx] in activeTransactions"
                :key="id"
                :transaction="tx"
                @click="selectedTransaction = tx"
              />
              
              <div v-if="activeTransactions.size === 0" 
                   class="text-center py-12 text-gray-500">
                <div class="text-4xl mb-2">🔍</div>
                <p>No active transactions</p>
                <button @click="simulateTransactions" 
                        class="mt-4 px-4 py-2 bg-purple-600 hover:bg-purple-700 rounded-lg transition">
                  Simulate Transactions
                </button>
              </div>
            </div>
          </div>

          <!-- Network Activity Graph -->
          <div class="bg-black/40 backdrop-blur-md rounded-xl border border-purple-800/30 p-6">
            <h2 class="text-lg font-semibold mb-4 text-purple-300">Network Activity</h2>
            <NetworkActivityGraph :data="networkActivityData" />
          </div>
        </div>

        <!-- Right Panel -->
        <div class="space-y-4">
          <!-- Connected Peers -->
          <div class="bg-black/40 backdrop-blur-md rounded-xl border border-purple-800/30 p-6">
            <h2 class="text-lg font-semibold mb-4 text-purple-300">Connected Peers</h2>
            <div class="space-y-2">
              <div v-for="peer in peers" :key="peer.id"
                   class="flex items-center justify-between p-2 rounded-lg bg-gray-800/50">
                <div class="flex items-center gap-2">
                  <div class="w-2 h-2 rounded-full bg-green-400"></div>
                  <span class="text-xs font-mono text-gray-400">
                    {{ peer.id.substring(0, 12) }}...
                  </span>
                </div>
                <div class="text-xs text-gray-500">
                  {{ peer.latency }}ms
                </div>
              </div>
            </div>
          </div>

          <!-- Event Log -->
          <div class="bg-black/40 backdrop-blur-md rounded-xl border border-purple-800/30 p-6">
            <h2 class="text-lg font-semibold mb-4 text-purple-300">Event Log</h2>
            <div class="space-y-1 max-h-96 overflow-y-auto custom-scrollbar">
              <div v-for="(event, idx) in eventLog" :key="idx"
                   class="text-xs font-mono">
                <span class="text-gray-600">{{ event.time }}</span>
                <span :class="getEventColor(event.type)" class="ml-2">
                  {{ event.message }}
                </span>
              </div>
            </div>
          </div>

          <!-- Quick Actions -->
          <div class="bg-black/40 backdrop-blur-md rounded-xl border border-purple-800/30 p-6">
            <h2 class="text-lg font-semibold mb-4 text-purple-300">Quick Actions</h2>
            <div class="grid grid-cols-2 gap-2">
              <button @click="refreshNetwork" 
                      class="px-3 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg text-sm transition">
                🔄 Refresh
              </button>
              <button @click="discoverPeers" 
                      class="px-3 py-2 bg-green-600 hover:bg-green-700 rounded-lg text-sm transition">
                🔍 Discover
              </button>
              <button @click="broadcastTest" 
                      class="px-3 py-2 bg-purple-600 hover:bg-purple-700 rounded-lg text-sm transition">
                📡 Broadcast
              </button>
              <button @click="clearLog" 
                      class="px-3 py-2 bg-gray-600 hover:bg-gray-700 rounded-lg text-sm transition">
                🗑️ Clear Log
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Transaction Detail Modal -->
    <TransactionDetailModal 
      v-if="selectedTransaction"
      :transaction="selectedTransaction"
      @close="selectedTransaction = null"
    />
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'

// Components
const MetricCard = {
  props: ['title', 'value', 'icon', 'color', 'trend'],
  template: `
    <div class="bg-black/40 backdrop-blur-md rounded-xl border border-purple-800/30 p-4">
      <div class="flex items-start justify-between">
        <div>
          <p class="text-xs text-gray-400 mb-1">{{ title }}</p>
          <p class="text-2xl font-bold" :class="'text-' + color + '-400'">
            {{ value }}
          </p>
          <p v-if="trend" class="text-xs mt-1" :class="trend > 0 ? 'text-green-400' : 'text-red-400'">
            {{ trend > 0 ? '↑' : '↓' }} {{ Math.abs(trend) }}%
          </p>
        </div>
        <span class="text-2xl">{{ icon }}</span>
      </div>
    </div>
  `
}

const TransactionCard = {
  props: ['transaction'],
  emits: ['click'],
  template: `
    <div @click="$emit('click')" 
         class="bg-gray-800/50 rounded-lg p-4 cursor-pointer hover:bg-gray-800/70 transition">
      <div class="flex items-center justify-between mb-2">
        <div class="flex items-center gap-2">
          <div class="w-2 h-2 rounded-full animate-pulse"
               :class="getStatusColor(transaction.status)"></div>
          <span class="font-mono text-sm">{{ transaction.id.substring(0, 16) }}...</span>
        </div>
        <span class="text-xs text-gray-400">{{ formatTime(transaction.timestamp) }}</span>
      </div>
      <div class="grid grid-cols-3 gap-4 text-xs">
        <div>
          <span class="text-gray-500">Amount:</span>
          <span class="ml-1 text-blue-400">{{ transaction.amount }}</span>
        </div>
        <div>
          <span class="text-gray-500">Status:</span>
          <span class="ml-1 capitalize">{{ transaction.status }}</span>
        </div>
        <div>
          <span class="text-gray-500">Network:</span>
          <span class="ml-1">{{ transaction.network }}</span>
        </div>
      </div>
      <div class="mt-2 h-1 bg-gray-700 rounded-full overflow-hidden">
        <div class="h-full bg-gradient-to-r from-purple-500 to-pink-500 transition-all duration-500"
             :style="{width: getProgress(transaction.status) + '%'}"></div>
      </div>
    </div>
  `,
  methods: {
    getStatusColor(status) {
      const colors = {
        'pending': 'bg-yellow-400',
        'processing': 'bg-blue-400',
        'completed': 'bg-green-400',
        'failed': 'bg-red-400'
      }
      return colors[status] || 'bg-gray-400'
    },
    getProgress(status) {
      const progress = {
        'pending': 25,
        'payment_verified': 50,
        'cirx_transfer_initiated': 75,
        'completed': 100,
        'failed': 100
      }
      return progress[status] || 10
    },
    formatTime(timestamp) {
      return new Date(timestamp).toLocaleTimeString()
    }
  }
}

const NetworkActivityGraph = {
  props: ['data'],
  template: `
    <div class="h-48 flex items-end gap-1">
      <div v-for="(value, idx) in data" :key="idx"
           class="flex-1 bg-gradient-to-t from-purple-600 to-pink-600 rounded-t-sm transition-all duration-300"
           :style="{height: value + '%'}">
      </div>
    </div>
  `
}

const TransactionDetailModal = {
  props: ['transaction'],
  emits: ['close'],
  template: `
    <div class="fixed inset-0 bg-black/80 backdrop-blur-sm flex items-center justify-center z-50"
         @click.self="$emit('close')">
      <div class="bg-gray-900 rounded-xl border border-purple-800/30 p-6 max-w-2xl w-full mx-4">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-xl font-semibold text-purple-300">Transaction Details</h3>
          <button @click="$emit('close')" class="text-gray-400 hover:text-white">✕</button>
        </div>
        <div class="space-y-4">
          <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
              <span class="text-gray-500">Transaction ID:</span>
              <p class="font-mono text-xs mt-1">{{ transaction.id }}</p>
            </div>
            <div>
              <span class="text-gray-500">Status:</span>
              <p class="capitalize mt-1">{{ transaction.status }}</p>
            </div>
            <div>
              <span class="text-gray-500">Amount:</span>
              <p class="mt-1">{{ transaction.amount }}</p>
            </div>
            <div>
              <span class="text-gray-500">Network:</span>
              <p class="mt-1">{{ transaction.network }}</p>
            </div>
          </div>
          <div class="border-t border-gray-800 pt-4">
            <span class="text-gray-500 text-sm">Event History:</span>
            <div class="mt-2 space-y-1">
              <div v-for="event in transaction.events || []" :key="event.timestamp"
                   class="text-xs font-mono text-gray-400">
                {{ formatTimestamp(event.timestamp) }} - {{ event.status }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  `,
  methods: {
    formatTimestamp(ts) {
      return new Date(ts).toLocaleString()
    }
  }
}

// State
const config = useRuntimeConfig()
const isConnected = ref(false)
const nodeId = ref(null)
const activeTransactions = ref(new Map())
const networkPeers = ref(0)
const messagesPerSecond = ref(0)
const successRate = ref(98.5)
const transactionTrend = ref(12)
const peers = ref([])
const eventLog = ref([])
const selectedTransaction = ref(null)
const networkActivityData = ref(Array(30).fill(0))

let updateInterval = null
let messageCounter = 0
let lastMessageTime = Date.now()

// Initialize IROH connection
const initializeIROH = async () => {
  try {
    const bridgeUrl = config.public.irohBridgeUrl
    
    // Check health
    const health = await $fetch(`${bridgeUrl}/health`)
    if (health.success) {
      isConnected.value = true
      
      // Get node info
      const nodeInfo = await $fetch(`${bridgeUrl}/node/info`)
      if (nodeInfo.success) {
        nodeId.value = nodeInfo.data.node_id
        networkPeers.value = nodeInfo.data.peers_connected || 0
        
        // Mock some peers
        peers.value = Array.from({length: networkPeers.value}, (_, i) => ({
          id: `12D3KooW${Math.random().toString(36).substring(2, 15)}`,
          latency: Math.floor(Math.random() * 50) + 5
        }))
      }
      
      addEvent('success', 'Connected to IROH network')
    }
  } catch (error) {
    console.error('IROH initialization failed:', error)
    addEvent('error', 'Failed to connect to IROH network')
  }
}

// Simulate transactions for demo
const simulateTransactions = () => {
  const statuses = ['pending', 'payment_verified', 'cirx_transfer_initiated', 'completed']
  const networks = ['ethereum', 'polygon', 'arbitrum', 'optimism']
  
  // Create 5 random transactions
  for (let i = 0; i < 5; i++) {
    const tx = {
      id: `tx_${Date.now()}_${i}`,
      amount: `${(Math.random() * 10).toFixed(2)} ETH`,
      status: statuses[Math.floor(Math.random() * statuses.length)],
      network: networks[Math.floor(Math.random() * networks.length)],
      timestamp: Date.now() - Math.random() * 60000,
      events: []
    }
    
    activeTransactions.value.set(tx.id, tx)
    addEvent('transaction', `New transaction: ${tx.id.substring(0, 16)}...`)
    
    // Simulate status updates
    setTimeout(() => updateTransactionStatus(tx.id), Math.random() * 5000 + 2000)
  }
}

// Update transaction status
const updateTransactionStatus = (txId) => {
  const tx = activeTransactions.value.get(txId)
  if (!tx) return
  
  const statusFlow = ['pending', 'payment_verified', 'cirx_transfer_initiated', 'completed']
  const currentIndex = statusFlow.indexOf(tx.status)
  
  if (currentIndex < statusFlow.length - 1) {
    tx.status = statusFlow[currentIndex + 1]
    tx.events = tx.events || []
    tx.events.push({
      timestamp: Date.now(),
      status: tx.status
    })
    
    addEvent('update', `Transaction ${txId.substring(0, 16)}... → ${tx.status}`)
    
    // Continue updating
    if (tx.status !== 'completed') {
      setTimeout(() => updateTransactionStatus(txId), Math.random() * 3000 + 2000)
    } else {
      // Remove completed transactions after delay
      setTimeout(() => {
        activeTransactions.value.delete(txId)
        addEvent('complete', `Transaction ${txId.substring(0, 16)}... completed`)
      }, 5000)
    }
  }
}

// Network actions
const refreshNetwork = async () => {
  await initializeIROH()
  addEvent('info', 'Network refreshed')
}

const discoverPeers = async () => {
  try {
    const bridgeUrl = config.public.irohBridgeUrl
    const response = await $fetch(`${bridgeUrl}/services/discover`, {
      method: 'POST',
      body: { service_name: '', max_results: 10 }
    })
    
    if (response.success) {
      addEvent('discovery', `Found ${response.data.length} services`)
    }
  } catch (error) {
    addEvent('error', 'Peer discovery failed')
  }
}

const broadcastTest = async () => {
  try {
    const bridgeUrl = config.public.irohBridgeUrl
    const response = await $fetch(`${bridgeUrl}/broadcast`, {
      method: 'POST',
      body: {
        topic: 'monitor-test',
        message: { type: 'PING', timestamp: Date.now() }
      }
    })
    
    if (response.success) {
      addEvent('broadcast', `Message sent to ${response.recipients || 0} recipients`)
    }
  } catch (error) {
    addEvent('error', 'Broadcast failed')
  }
}

const clearLog = () => {
  eventLog.value = []
  addEvent('info', 'Event log cleared')
}

// Add event to log
const addEvent = (type, message) => {
  const time = new Date().toLocaleTimeString()
  eventLog.value.unshift({ time, type, message })
  if (eventLog.value.length > 100) {
    eventLog.value.pop()
  }
  
  // Update message counter
  messageCounter++
  updateMessagesPerSecond()
}

// Update messages per second
const updateMessagesPerSecond = () => {
  const now = Date.now()
  const elapsed = (now - lastMessageTime) / 1000
  if (elapsed > 1) {
    messagesPerSecond.value = Math.round(messageCounter / elapsed)
    messageCounter = 0
    lastMessageTime = now
  }
}

// Update network activity graph
const updateNetworkActivity = () => {
  networkActivityData.value.shift()
  networkActivityData.value.push(Math.random() * 100)
}

// Get event color based on type
const getEventColor = (type) => {
  const colors = {
    'success': 'text-green-400',
    'error': 'text-red-400',
    'info': 'text-blue-400',
    'warning': 'text-yellow-400',
    'transaction': 'text-purple-400',
    'update': 'text-cyan-400',
    'complete': 'text-emerald-400',
    'discovery': 'text-pink-400',
    'broadcast': 'text-indigo-400'
  }
  return colors[type] || 'text-gray-400'
}

// Lifecycle
onMounted(async () => {
  await initializeIROH()
  
  // Start periodic updates
  updateInterval = setInterval(() => {
    updateNetworkActivity()
    
    // Update metrics with some variation
    successRate.value = Math.min(100, Math.max(95, successRate.value + (Math.random() - 0.5)))
    transactionTrend.value = Math.round((Math.random() - 0.5) * 20)
  }, 1000)
  
  // Simulate initial transactions
  setTimeout(simulateTransactions, 1000)
})

onUnmounted(() => {
  if (updateInterval) {
    clearInterval(updateInterval)
  }
})
</script>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
}

.custom-scrollbar::-webkit-scrollbar-track {
  background: rgba(107, 114, 128, 0.1);
  border-radius: 3px;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
  background: rgba(139, 92, 246, 0.5);
  border-radius: 3px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: rgba(139, 92, 246, 0.7);
}
</style>