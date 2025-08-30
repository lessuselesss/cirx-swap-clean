<template>
  <div class="min-h-screen">
    <!-- Main App Content -->
    <NuxtPage />
    
    <!-- Global Toast Notifications -->
    <ToastNotifications ref="toastManager" />
    
    <!-- Global Error Boundary -->
    <div v-if="globalError" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-gray-900 rounded-xl border border-red-500/30 p-6 max-w-md w-full">
        <div class="flex items-start gap-3">
          <svg class="w-6 h-6 text-red-400 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none">
            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
            <line x1="15" y1="9" x2="9" y2="15" stroke="currentColor" stroke-width="2"/>
            <line x1="9" y1="9" x2="15" y2="15" stroke="currentColor" stroke-width="2"/>
          </svg>
          <div class="flex-1">
            <h3 class="text-red-300 font-semibold mb-2">Critical Error</h3>
            <p class="text-sm text-gray-300 mb-4">{{ globalError }}</p>
            <div class="flex gap-2">
              <button
                @click="handleGlobalErrorRetry"
                class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 transition-colors"
              >
                Retry
              </button>
              <button
                @click="handleGlobalErrorReload"
                class="px-4 py-2 bg-gray-600 text-white rounded-lg text-sm font-medium hover:bg-gray-700 transition-colors"
              >
                Reload Page
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onErrorCaptured } from 'vue'
import { useAutoWorker } from '~/composables/useRBackendAPIs.js'
import { safeToast } from '~/utils/toast.js'

// Global error state
const globalError = ref(null)
const toastManager = ref(null)

// Auto-worker for background transaction processing
const { isProcessing, getWorkerStatus } = useAutoWorker()

// Global error handler
const handleGlobalError = (error, context = 'Unknown') => {
  console.error('🚨 GLOBAL ERROR TRIGGERED:', error, 'Context:', context)
  console.error('🔍 Error details:', {
    message: error.message,
    stack: error.stack,
    name: error.name,
    cause: error.cause
  })
  
  // For critical errors, show modal
  if (error.message?.includes('chunk') || error.message?.includes('Loading')) {
    globalError.value = 'Failed to load application resources. This may be due to a network issue or an update.'
  } else if (error.message?.includes('hydration')) {
    globalError.value = 'Application initialization failed. Please refresh the page.'
  } else {
    globalError.value = `A critical error occurred: ${error.message || 'Unknown error'}. Please try refreshing the page.`
  }
}

// Global error recovery
const handleGlobalErrorRetry = () => {
  globalError.value = null
  // Additional retry logic could go here
}

const handleGlobalErrorReload = () => {
  window.location.reload()
}

// Vue error boundary
onErrorCaptured((error, instance, info) => {
  console.error('🔴 VUE ERROR CAPTURED:', error)
  console.error('🔍 Vue error details:', {
    message: error.message,
    stack: error.stack,
    componentInfo: info,
    instanceType: instance?.$?.type?.name || 'Unknown',
    props: instance?.$?.props
  })
  
  // Check for specific error patterns that should trigger critical error
  const isCriticalError = error.message?.includes('Cannot read properties') || 
                         error.message?.includes('Cannot access before initialization') ||
                         error.message?.includes('useAccount') ||
                         error.message?.includes('useBalance') ||
                         error.message?.includes('useConnect')
  
  if (isCriticalError) {
    console.error('🚨 CRITICAL VUE ERROR - triggering global error handler')
    handleGlobalError(error, `Vue component: ${info}`)
    return false // Prevent error from propagating
  }
  
  // For non-critical errors, show toast notification
  console.warn('⚠️ Non-critical Vue error - showing toast')
  safeToast.error('A component error occurred. Some features may not work correctly.', {
    title: 'Component Error',
    autoTimeoutMs: 8000,
    actions: [{
      label: 'Refresh Page',
      handler: () => window.location.reload(),
      primary: true
    }]
  })
  
  return false
})


// Global unhandled error handlers
onMounted(() => {
  // Handle unhandled promise rejections
  window.addEventListener('unhandledrejection', (event) => {
    console.error('🔴 UNHANDLED PROMISE REJECTION:', event.reason)
    console.error('🔍 Promise rejection details:', {
      reason: event.reason,
      promise: event.promise,
      message: event.reason?.message,
      stack: event.reason?.stack
    })
    
    // Prevent default error handling
    event.preventDefault()
    
    // Check if this is a critical error
    const error = event.reason
    const isCriticalChunkError = error?.message?.includes('Loading chunk') || 
                                error?.message?.includes('ChunkLoadError')
    
    const isWalletError = error?.message?.includes('wallet') || 
                         error?.message?.includes('Web3') ||
                         error?.message?.includes('connection') ||
                         error?.message?.includes('metamask') ||
                         error?.message?.includes('ethereum')
    
    if (isCriticalChunkError) {
      console.error('🚨 CRITICAL CHUNK ERROR - triggering global error handler')
      handleGlobalError(error, 'Chunk loading')
    } else if (isWalletError) {
      console.warn('🔒 WALLET ERROR - handled without critical dialog')
      // Don't show critical error for wallet issues
      safeToast.error('Wallet connection issue. Please try again.', {
        title: 'Wallet Error',
        autoTimeoutMs: 4000
      })
    } else {
      console.warn('⚠️ OTHER ERROR - showing as toast')
      // Show as toast for other errors
      safeToast.error('An unexpected error occurred.', {
        title: 'Application Error',
        autoTimeoutMs: 6000
      })
    }
  })
  
  // Handle regular JavaScript errors
  window.addEventListener('error', (event) => {
    console.error('Global JavaScript error:', event.error)
    
    const error = event.error
    if (error?.message?.includes('Script error') || 
        error?.message?.includes('ResizeObserver')) {
      // Ignore these common but non-critical errors
      return
    }
    
    safeToast.error('A JavaScript error occurred.', {
      title: 'Script Error',
      autoTimeoutMs: 6000
    })
  })
  
  // Make toast manager globally available
  if (toastManager.value) {
    window.$toast = toastManager.value
    // Provide to all child components
    provide('toast', toastManager.value)
  }
})

// Page metadata
useHead({
  title: 'Circular CIRX OTC Platform',
  meta: [
    { name: 'description', content: 'Trade CIRX tokens with instant delivery or OTC discounts up to 12%' }
  ]
})
</script>

<style>
/* Global styles */
html {
  scroll-behavior: smooth;
}

body {
  background-color: #0a0a0a;
  color: #ffffff;
}

/* Prevent flash of unstyled content */
.nuxt-loading-indicator {
  background: linear-gradient(to right, #00ff88, #0088ff);
  height: 3px;
}
</style>