<template>
  <div class="container mx-auto p-8">
    <h1 class="text-3xl font-bold mb-8">IROH Network Status</h1>
    
    <!-- Connection Status -->
    <div class="bg-gray-800 rounded-lg p-6 mb-6">
      <h2 class="text-xl font-semibold mb-4">Connection Status</h2>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <span class="text-gray-400">IROH Enabled:</span>
          <span :class="{'text-green-500': irohEnabled, 'text-red-500': !irohEnabled}" class="ml-2 font-mono">
            {{ irohEnabled }}
          </span>
        </div>
        <div>
          <span class="text-gray-400">Connection:</span>
          <span :class="{'text-green-500': isConnected, 'text-yellow-500': !isConnected}" class="ml-2 font-mono">
            {{ connectionStatus }}
          </span>
        </div>
        <div v-if="nodeId">
          <span class="text-gray-400">Node ID:</span>
          <span class="ml-2 font-mono text-xs text-blue-400">{{ nodeId }}</span>
        </div>
        <div>
          <span class="text-gray-400">Bridge URL:</span>
          <span class="ml-2 font-mono text-xs">{{ bridgeUrl }}</span>
        </div>
      </div>
    </div>

    <!-- Network Statistics -->
    <div class="bg-gray-800 rounded-lg p-6 mb-6">
      <h2 class="text-xl font-semibold mb-4">Network Statistics</h2>
      <div class="grid grid-cols-3 gap-4">
        <div class="text-center">
          <div class="text-2xl font-bold text-blue-400">{{ discoveredServices.size }}</div>
          <div class="text-sm text-gray-400">Discovered Services</div>
        </div>
        <div class="text-center">
          <div class="text-2xl font-bold text-green-400">{{ networkStats.peers || 0 }}</div>
          <div class="text-sm text-gray-400">Connected Peers</div>
        </div>
        <div class="text-center">
          <div class="text-2xl font-bold text-purple-400">{{ messageCount }}</div>
          <div class="text-sm text-gray-400">Messages Received</div>
        </div>
      </div>
    </div>

    <!-- Discovered Services -->
    <div class="bg-gray-800 rounded-lg p-6 mb-6">
      <h2 class="text-xl font-semibold mb-4">Discovered Services</h2>
      <div v-if="discoveredServices.size > 0" class="space-y-2">
        <div v-for="[id, service] in discoveredServices" :key="id" 
             class="bg-gray-700 rounded p-3 flex justify-between items-center">
          <div>
            <div class="font-mono text-sm">{{ service.service_name }}</div>
            <div class="text-xs text-gray-400">{{ service.service_id }}</div>
          </div>
          <div class="text-xs text-gray-400">
            <div>{{ service.endpoint }}</div>
            <div>Last seen: {{ formatTime(service.last_seen) }}</div>
          </div>
        </div>
      </div>
      <div v-else class="text-gray-400 italic">No services discovered yet</div>
    </div>

    <!-- Real-time Messages -->
    <div class="bg-gray-800 rounded-lg p-6 mb-6">
      <h2 class="text-xl font-semibold mb-4">Real-time Messages</h2>
      <div class="max-h-64 overflow-y-auto bg-gray-900 rounded p-3 font-mono text-xs">
        <div v-for="(msg, idx) in messages" :key="idx" class="mb-1">
          <span class="text-gray-500">[{{ msg.timestamp }}]</span>
          <span class="text-blue-400 ml-2">{{ msg.topic }}:</span>
          <span class="text-green-400 ml-2">{{ msg.content }}</span>
        </div>
        <div v-if="messages.length === 0" class="text-gray-500 italic">
          No messages received yet...
        </div>
      </div>
    </div>

    <!-- Test Actions -->
    <div class="bg-gray-800 rounded-lg p-6">
      <h2 class="text-xl font-semibold mb-4">Test Actions</h2>
      <div class="flex gap-4">
        <button @click="discoverServices" 
                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded transition">
          Discover Services
        </button>
        <button @click="broadcastTestMessage" 
                class="px-4 py-2 bg-green-600 hover:bg-green-700 rounded transition">
          Broadcast Test Message
        </button>
        <button @click="simulateTransaction" 
                class="px-4 py-2 bg-purple-600 hover:bg-purple-700 rounded transition">
          Simulate Transaction
        </button>
        <button @click="refreshStatus" 
                class="px-4 py-2 bg-gray-600 hover:bg-gray-700 rounded transition">
          Refresh Status
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'

const config = useRuntimeConfig()
const irohEnabled = ref(config.public.irohEnabled)
const bridgeUrl = config.public.irohBridgeUrl
const isConnected = ref(false)
const connectionStatus = ref('disconnected')
const nodeId = ref(null)
const discoveredServices = ref(new Map())
const networkStats = ref({})
const messages = ref([])
const messageCount = ref(0)

let pollInterval = null

// Initialize IROH connection
const initialize = async () => {
  try {
    if (!irohEnabled.value) {
      connectionStatus.value = 'disabled'
      return
    }

    connectionStatus.value = 'connecting...'

    // Check bridge health
    const healthResponse = await $fetch(`${bridgeUrl}/health`)
    if (!healthResponse.success) {
      connectionStatus.value = 'bridge unhealthy'
      return
    }

    // Get node info
    const nodeResponse = await $fetch(`${bridgeUrl}/node/info`)
    if (nodeResponse.success) {
      nodeId.value = nodeResponse.data.node_id
      networkStats.value = {
        peers: nodeResponse.data.peers_connected,
        services: nodeResponse.data.services_registered
      }
      isConnected.value = true
      connectionStatus.value = 'connected'
      
      addMessage('system', 'IROH network connected successfully')
    }

    // Initial service discovery
    await discoverServices()

  } catch (error) {
    console.error('IROH initialization failed:', error)
    connectionStatus.value = 'error'
    isConnected.value = false
  }
}

// Discover services on the network
const discoverServices = async () => {
  try {
    const response = await $fetch(`${bridgeUrl}/services/discover`, {
      method: 'POST',
      body: {
        service_name: '',
        max_results: 10
      }
    })

    if (response.success && response.data) {
      discoveredServices.value.clear()
      response.data.forEach(service => {
        discoveredServices.value.set(service.service_id, service)
      })
      addMessage('discovery', `Found ${response.data.length} services`)
    }
  } catch (error) {
    console.error('Service discovery failed:', error)
    addMessage('error', 'Service discovery failed')
  }
}

// Broadcast a test message
const broadcastTestMessage = async () => {
  try {
    const message = {
      type: 'TEST_BROADCAST',
      timestamp: Date.now(),
      data: 'Hello from IROH network debug page!',
      nodeId: nodeId.value
    }

    const response = await $fetch(`${bridgeUrl}/broadcast`, {
      method: 'POST',
      body: {
        topic: 'debug-test',
        message
      }
    })

    if (response.success) {
      addMessage('broadcast', `Message sent to ${response.recipients || 0} recipients`)
    }
  } catch (error) {
    console.error('Broadcast failed:', error)
    addMessage('error', 'Broadcast failed')
  }
}

// Simulate a transaction broadcast
const simulateTransaction = async () => {
  try {
    const txData = {
      type: 'TRANSACTION_STATUS_UPDATE',
      transaction_id: `tx_${Date.now()}`,
      status: 'completed',
      amount: '100 CIRX',
      from: '0x1234...5678',
      to: '0xabcd...efgh',
      timestamp: Date.now()
    }

    const response = await $fetch(`${bridgeUrl}/broadcast`, {
      method: 'POST',
      body: {
        topic: 'transaction-updates',
        message: txData
      }
    })

    if (response.success) {
      addMessage('transaction', `Transaction broadcast: ${txData.transaction_id}`)
    }
  } catch (error) {
    console.error('Transaction broadcast failed:', error)
    addMessage('error', 'Transaction broadcast failed')
  }
}

// Refresh network status
const refreshStatus = async () => {
  await initialize()
}

// Add message to the log
const addMessage = (topic, content) => {
  const timestamp = new Date().toLocaleTimeString()
  messages.value.unshift({ timestamp, topic, content })
  if (messages.value.length > 50) {
    messages.value.pop()
  }
  messageCount.value++
}

// Format timestamp
const formatTime = (timestamp) => {
  if (!timestamp) return 'never'
  const date = new Date(timestamp)
  return date.toLocaleTimeString()
}

// Periodic status updates
const startPolling = () => {
  pollInterval = setInterval(async () => {
    if (isConnected.value) {
      try {
        // Get updated node info
        const nodeResponse = await $fetch(`${bridgeUrl}/node/info`)
        if (nodeResponse.success) {
          networkStats.value = {
            peers: nodeResponse.data.peers_connected,
            services: nodeResponse.data.services_registered
          }
        }
      } catch (error) {
        console.error('Status update failed:', error)
      }
    }
  }, 5000) // Update every 5 seconds
}

// Lifecycle hooks
onMounted(async () => {
  await initialize()
  startPolling()
})

onUnmounted(() => {
  if (pollInterval) {
    clearInterval(pollInterval)
  }
})
</script>

<style scoped>
/* Custom scrollbar for message log */
.max-h-64::-webkit-scrollbar {
  width: 8px;
}

.max-h-64::-webkit-scrollbar-track {
  background: #1f2937;
}

.max-h-64::-webkit-scrollbar-thumb {
  background: #4b5563;
  border-radius: 4px;
}

.max-h-64::-webkit-scrollbar-thumb:hover {
  background: #6b7280;
}
</style>