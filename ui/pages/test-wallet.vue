<template>
  <div class="min-h-screen bg-gray-900 p-8">
    <div class="max-w-2xl mx-auto">
      <h1 class="text-3xl font-bold text-white mb-8">Wallet Connection Test</h1>
      
      <!-- AppKit Status -->
      <div class="bg-gray-800 rounded-lg p-6 mb-6">
        <h2 class="text-xl font-semibold text-white mb-4">AppKit Status</h2>
        <div class="space-y-2">
          <p class="text-gray-300">
            AppKit Instance: 
            <span :class="$appkit ? 'text-green-400' : 'text-red-400'">
              {{ $appkit ? 'Available' : 'Not Available' }}
            </span>
          </p>
          <p class="text-gray-300">
            Project ID: 
            <span class="text-blue-400">{{ projectId }}</span>
          </p>
        </div>
      </div>

      <!-- Connection Status -->
      <div class="bg-gray-800 rounded-lg p-6 mb-6">
        <h2 class="text-xl font-semibold text-white mb-4">Connection Status</h2>
        <div class="space-y-2">
          <p class="text-gray-300">
            Connected: 
            <span :class="isConnected ? 'text-green-400' : 'text-red-400'">
              {{ isConnected ? 'Yes' : 'No' }}
            </span>
          </p>
          <p class="text-gray-300" v-if="address">
            Address: <span class="text-blue-400 font-mono">{{ address }}</span>
          </p>
          <p class="text-gray-300" v-if="chainId">
            Chain ID: <span class="text-blue-400">{{ chainId }}</span>
          </p>
        </div>
      </div>

      <!-- Wallet Controls -->
      <div class="bg-gray-800 rounded-lg p-6 mb-6">
        <h2 class="text-xl font-semibold text-white mb-4">Wallet Controls</h2>
        <div class="space-y-4">
          <!-- AppKit Button -->
          <div>
            <label class="block text-gray-300 mb-2">Official AppKit Button:</label>
            <ClientOnly>
              <template #fallback>
                <div class="w-40 h-12 bg-gray-700 rounded-lg animate-pulse"></div>
              </template>
              <appkit-button v-if="$appkit" />
              <div v-else class="px-4 py-2 bg-red-600 text-white rounded-lg">
                AppKit Unavailable
              </div>
            </ClientOnly>
          </div>

          <!-- Manual Connect Button -->
          <div>
            <label class="block text-gray-300 mb-2">Manual Connect (using composable):</label>
            <button 
              @click="connectWallet"
              class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
              :disabled="!$appkit"
            >
              Connect via Composable
            </button>
          </div>

          <!-- Disconnect Button -->
          <div v-if="isConnected">
            <label class="block text-gray-300 mb-2">Disconnect:</label>
            <button 
              @click="disconnectWallet"
              class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors"
            >
              Disconnect
            </button>
          </div>
        </div>
      </div>

      <!-- Error Log -->
      <div class="bg-gray-800 rounded-lg p-6" v-if="errorLog.length > 0">
        <h2 class="text-xl font-semibold text-white mb-4">Error Log</h2>
        <div class="space-y-2 max-h-40 overflow-y-auto">
          <div 
            v-for="(error, index) in errorLog" 
            :key="index"
            class="text-red-400 text-sm font-mono bg-gray-900 p-2 rounded"
          >
            {{ error }}
          </div>
        </div>
        <button 
          @click="errorLog = []"
          class="mt-2 px-3 py-1 bg-gray-600 text-white text-sm rounded hover:bg-gray-700"
        >
          Clear Log
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useAppKitAccount, useDisconnect, useAppKit } from "@reown/appkit/vue"

// Page metadata
useHead({
  title: 'Wallet Connection Test - Circular CIRX OTC Platform'
})

// AppKit composables
const { address, isConnected, chainId } = useAppKitAccount()
const { disconnect } = useDisconnect()
const { open } = useAppKit()

// Local state
const errorLog = ref([])
const config = useRuntimeConfig()
const projectId = config.public.reownProjectId

// Methods
const connectWallet = async () => {
  try {
    if (!open) {
      throw new Error('AppKit open function not available')
    }
    await open()
  } catch (error) {
    console.error('Connect wallet error:', error)
    errorLog.value.push(`Connect Error: ${error.message}`)
  }
}

const disconnectWallet = async () => {
  try {
    if (!disconnect) {
      throw new Error('AppKit disconnect function not available')
    }
    await disconnect()
  } catch (error) {
    console.error('Disconnect wallet error:', error)
    errorLog.value.push(`Disconnect Error: ${error.message}`)
  }
}

// Initialize
onMounted(() => {
  console.log('Test page mounted')
  console.log('AppKit instance:', !!process.client ? (window.$nuxt?.$appkit || 'Not found') : 'Server side')
  console.log('Project ID:', projectId)
  
  // Add error listeners for debugging
  if (process.client) {
    window.addEventListener('error', (event) => {
      errorLog.value.push(`JS Error: ${event.error?.message || event.message}`)
    })
    
    window.addEventListener('unhandledrejection', (event) => {
      errorLog.value.push(`Promise Rejection: ${event.reason?.message || event.reason}`)
    })
  }
})
</script>