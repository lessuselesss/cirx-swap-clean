<template>
  <div v-if="showDetector" class="extension-detector">
    <div class="bg-gray-800/90 border border-gray-600/30 rounded-lg p-4 mb-4">
      <div class="flex items-center justify-between mb-3">
        <h3 class="text-sm font-medium text-white">Browser Extensions</h3>
        <button 
          @click="detectExtensions" 
          :disabled="isDetecting"
          class="text-xs text-gray-400 hover:text-white transition-colors"
        >
          {{ isDetecting ? 'Detecting...' : 'Refresh' }}
        </button>
      </div>
      
      <div v-if="isDetecting" class="flex items-center gap-2 text-sm text-gray-400">
        <svg class="animate-spin w-4 h-4" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        Detecting extensions...
      </div>
      
      <div v-else-if="detectedExtensions.length === 0" class="text-sm text-gray-400">
        No extensions detected
      </div>
      
      <div v-else class="space-y-2">
        <!-- Wallet Extensions -->
        <div v-if="walletExtensions.length > 0">
          <h4 class="text-xs font-medium text-green-400 mb-2">💳 Wallet Extensions</h4>
          <div class="grid grid-cols-2 gap-2">
            <div 
              v-for="ext in walletExtensions" 
              :key="ext.id"
              class="flex items-center gap-2 p-2 bg-green-500/10 border border-green-500/20 rounded"
            >
              <img v-if="ext.icon.startsWith('http')" :src="ext.icon" :alt="ext.name" class="w-4 h-4 rounded">
              <span v-else class="text-sm">{{ ext.icon }}</span>
              <span class="text-xs text-green-300">{{ ext.name }}</span>
            </div>
          </div>
        </div>
        
        <!-- Other Extensions -->
        <div v-if="otherExtensions.length > 0">
          <h4 class="text-xs font-medium text-blue-400 mb-2">🔧 Other Extensions</h4>
          <div class="grid grid-cols-2 gap-2">
            <div 
              v-for="ext in otherExtensions" 
              :key="ext.id"
              class="flex items-center gap-2 p-2 bg-gray-700/30 border border-gray-600/20 rounded"
            >
              <img v-if="ext.icon.startsWith('http')" :src="ext.icon" :alt="ext.name" class="w-4 h-4 rounded">
              <span v-else class="text-sm">{{ ext.icon }}</span>
              <span class="text-xs text-gray-300">{{ ext.name }}</span>
            </div>
          </div>
        </div>
        
        <!-- Extension Stats -->
        <div class="mt-3 pt-3 border-t border-gray-600/30">
          <div class="flex justify-between text-xs text-gray-400">
            <span>Total: {{ detectedExtensions.length }}</span>
            <span v-if="hasAdBlockers">🚫 Ad Blocker Active</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useExtensionDetection } from '~/composables/useExtensionDetection'

// Props
defineProps({
  showDetector: {
    type: Boolean,
    default: true
  }
})

// Use extension detection composable
const {
  detectedExtensions,
  isDetecting,
  detectExtensions,
  hasAdBlockers,
  getWalletExtensions
} = useExtensionDetection()

// Computed properties
const walletExtensions = computed(() => getWalletExtensions())
const otherExtensions = computed(() => 
  detectedExtensions.value.filter(ext => 
    !['metamask', 'coinbaseWallet', 'phantom', 'rabby', 'trust'].includes(ext.id)
  )
)
</script>

<style scoped>
.extension-detector {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}
</style>