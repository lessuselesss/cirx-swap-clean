<template>
  <div class="min-h-screen bg-gray-900 p-8">
    <div class="max-w-4xl mx-auto">
      <h1 class="text-3xl font-bold text-white mb-8">Browser Extension Detection Test</h1>
      
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Extension Detector Component -->
        <div class="bg-gray-800 border border-gray-600 rounded-lg p-6">
          <h2 class="text-xl font-semibold text-white mb-4">Auto Detection</h2>
          <ExtensionDetector />
        </div>
        
        <!-- Manual Testing -->
        <div class="bg-gray-800 border border-gray-600 rounded-lg p-6">
          <h2 class="text-xl font-semibold text-white mb-4">Manual Tests</h2>
          <div class="space-y-4">
            
            <div class="border border-gray-700 rounded p-4">
              <h3 class="text-green-400 font-medium mb-2">Wallet Detection</h3>
              <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                  <span class="text-gray-300">window.ethereum:</span>
                  <span :class="!!window.ethereum ? 'text-green-400' : 'text-red-400'">
                    {{ !!window.ethereum ? 'Present' : 'Not found' }}
                  </span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-300">MetaMask:</span>
                  <span :class="isMetaMask ? 'text-green-400' : 'text-red-400'">
                    {{ isMetaMask ? 'Detected' : 'Not found' }}
                  </span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-300">Phantom:</span>
                  <span :class="isPhantom ? 'text-green-400' : 'text-red-400'">
                    {{ isPhantom ? 'Detected' : 'Not found' }}
                  </span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-300">Coinbase:</span>
                  <span :class="isCoinbase ? 'text-green-400' : 'text-red-400'">
                    {{ isCoinbase ? 'Detected' : 'Not found' }}
                  </span>
                </div>
              </div>
            </div>
            
            <div class="border border-gray-700 rounded p-4">
              <h3 class="text-blue-400 font-medium mb-2">Developer Tools</h3>
              <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                  <span class="text-gray-300">React DevTools:</span>
                  <span :class="hasReactDevTools ? 'text-green-400' : 'text-red-400'">
                    {{ hasReactDevTools ? 'Detected' : 'Not found' }}
                  </span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-300">Vue DevTools:</span>
                  <span :class="hasVueDevTools ? 'text-green-400' : 'text-red-400'">
                    {{ hasVueDevTools ? 'Detected' : 'Not found' }}
                  </span>
                </div>
              </div>
            </div>
            
            <div class="border border-gray-700 rounded p-4">
              <h3 class="text-yellow-400 font-medium mb-2">Browser Info</h3>
              <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                  <span class="text-gray-300">User Agent:</span>
                  <span class="text-gray-400 text-xs">{{ navigator.userAgent.slice(0, 50) }}...</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-300">Platform:</span>
                  <span class="text-gray-400">{{ navigator.platform }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-300">Language:</span>
                  <span class="text-gray-400">{{ navigator.language }}</span>
                </div>
              </div>
            </div>
            
            <button 
              @click="runManualCheck" 
              class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded transition-colors"
            >
              Run Manual Check
            </button>
          </div>
        </div>
      </div>
      
      <!-- Console Output -->
      <div class="mt-8 bg-black border border-gray-600 rounded-lg p-6">
        <h2 class="text-xl font-semibold text-white mb-4">Console Output</h2>
        <pre class="text-green-400 text-sm overflow-auto max-h-64">{{ consoleOutput }}</pre>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import ExtensionDetector from '~/components/ExtensionDetector.vue'

const consoleOutput = ref('')

// Wallet detections
const isMetaMask = computed(() => {
  if (typeof window === 'undefined') return false
  return !!(window.ethereum?.isMetaMask)
})

const isPhantom = computed(() => {
  if (typeof window === 'undefined') return false
  return !!(window.phantom?.solana || window.solana?.isPhantom)
})

const isCoinbase = computed(() => {
  if (typeof window === 'undefined') return false
  return !!(window.ethereum?.isCoinbaseWallet || window.coinbaseWalletExtension)
})

// DevTools detection
const hasReactDevTools = computed(() => {
  if (typeof window === 'undefined') return false
  return !!(window.__REACT_DEVTOOLS_GLOBAL_HOOK__)
})

const hasVueDevTools = computed(() => {
  if (typeof window === 'undefined') return false
  return !!(window.__VUE_DEVTOOLS_GLOBAL_HOOK__)
})

const addToConsole = (message) => {
  const timestamp = new Date().toLocaleTimeString()
  consoleOutput.value += `[${timestamp}] ${message}\n`
}

const runManualCheck = () => {
  addToConsole('=== Manual Extension Check ===')
  
  // Check window properties
  if (typeof window !== 'undefined') {
    addToConsole(`window.ethereum: ${!!window.ethereum}`)
    addToConsole(`window.solana: ${!!window.solana}`)
    addToConsole(`window.phantom: ${!!window.phantom}`)
    
    // List all window properties that might be extensions
    const extensionProps = Object.keys(window).filter(key => 
      key.toLowerCase().includes('wallet') || 
      key.toLowerCase().includes('ethereum') ||
      key.toLowerCase().includes('solana') ||
      key.toLowerCase().includes('extension')
    )
    
    if (extensionProps.length > 0) {
      addToConsole(`Potential extension properties: ${extensionProps.join(', ')}`)
    }
    
    // Check specific extension patterns
    if (window.ethereum) {
      addToConsole(`Ethereum providers found:`)
      if (window.ethereum.isMetaMask) addToConsole('  - MetaMask')
      if (window.ethereum.isCoinbaseWallet) addToConsole('  - Coinbase Wallet')
      if (window.ethereum.isRabby) addToConsole('  - Rabby Wallet')
      if (window.ethereum.isTrust) addToConsole('  - Trust Wallet')
      if (window.ethereum.providers) {
        addToConsole(`  - Multiple providers: ${window.ethereum.providers.length}`)
      }
    }
    
    // DevTools check
    if (window.__REACT_DEVTOOLS_GLOBAL_HOOK__) {
      addToConsole('React DevTools detected')
    }
    if (window.__VUE_DEVTOOLS_GLOBAL_HOOK__) {
      addToConsole('Vue DevTools detected')
    }
  }
  
  addToConsole('=== Check Complete ===')
}

onMounted(() => {
  addToConsole('Extension detection test page loaded')
  setTimeout(() => {
    runManualCheck()
  }, 1000)
})

// Page meta
definePageMeta({
  layout: 'default'
})
</script>