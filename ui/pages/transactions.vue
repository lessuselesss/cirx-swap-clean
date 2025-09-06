<template>
  <div class="min-h-screen relative overflow-hidden bg-figma-base">
    <!-- Space Background -->
    <div key="static-background" class="absolute inset-0 bg-cover bg-center bg-no-repeat bg-fixed z-0" style="background-image: url('/buy/background.png')"></div>
    <!-- Gradient overlay -->
    <div key="static-gradient" class="absolute inset-0 z-10" style="background: linear-gradient(to bottom, rgba(0,0,0,0.98) 0%, rgba(0,0,0,0.70) 50%, transparent 100%);"></div>
    
    <header class="sticky top-0 z-50 relative">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-20">
          <!-- Logo Section -->
          <div class="flex items-center gap-4">
            <NuxtLink to="/" class="flex items-center gap-4">
              <img 
                src="/images/logo/SVG/color-logo-white-svg.svg" 
                alt="Circular Protocol" 
                class="h-8 w-auto drop-shadow-lg"
              />
            </NuxtLink>
          </div>

          <!-- Navigation -->
          <div class="flex items-center gap-4">
            <NuxtLink 
              to="/" 
              class="px-4 py-2 text-white hover:text-circular-primary transition-colors"
            >
              Back to Swap
            </NuxtLink>
          </div>
        </div>
      </div>
    </header>

    <div class="min-h-[calc(100vh-4rem)] flex items-center justify-center p-4 md:p-8 relative z-10">
      <div class="w-full max-w-2xl mx-auto">
        
        <!-- Status Check Card -->
        <div class="bg-[#000305]/90 backdrop-blur-sm border border-gray-700/50 rounded-2xl p-8 mb-6">
          <h1 class="text-2xl font-bold text-white mb-4 text-center">
            Query Transaction Status
          </h1>
          
          <!-- Timing Notice -->
          <div class="bg-blue-900/30 border border-blue-600/30 rounded-xl p-4 mb-6">
            <div class="flex items-start gap-3">
              <div class="w-5 h-5 mt-0.5 text-blue-400">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
              </div>
              <div class="text-sm text-blue-200">
                <div class="font-medium mb-1">Transaction Processing Time</div>
                <div class="text-blue-300">Transactions typically take <strong>2-5 minutes</strong> to complete. Payment verification and CIRX transfers are processed automatically in the background.</div>
              </div>
            </div>
          </div>

          <!-- Swap ID Input -->
          <div class="mb-6">
            <label class="block text-sm font-medium text-gray-300 mb-3 text-center">
              Enter your Swap ID to check status:
            </label>
            <div class="flex flex-col items-center gap-4">
              <input
                v-model="swapId"
                type="text"
                placeholder="e.g., 123e4567-e89b-12d3-a456-426614174000"
                class="w-full max-w-md px-4 py-3 bg-gray-800/70 border border-gray-600/50 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-circular-primary focus:border-transparent text-center"
              />
              <button
                @click="console.log('🔘 Check Status button clicked'); handleCheckStatus()"
                :disabled="!swapId || loading || isRateLimited"
                class="px-8 py-3 bg-circular-primary hover:bg-circular-primary/80 disabled:bg-gray-600 disabled:opacity-50 text-white font-medium rounded-xl transition-colors"
              >
                {{ getQueryButtonText() }}
              </button>
            </div>
          </div>

          <!-- Last Swap ID -->
          <div v-if="lastSwapId && !swapId" class="mb-4 text-center">
            <button
              @click="swapId = lastSwapId; handleCheckStatus()"
              :disabled="isRateLimited"
              :class="isRateLimited ? 'text-gray-500 cursor-not-allowed' : 'text-circular-primary hover:text-circular-primary/80'"
              class="text-sm underline"
            >
              Check status of last swap: {{ lastSwapId.slice(0, 8) }}...
            </button>
          </div>


          <!-- Status Display -->
          <div v-if="status" class="mt-6 p-6 bg-gray-800/50 rounded-xl border border-gray-600/30">
            <div class="space-y-4">
              <!-- Status Badge -->
              <div class="flex items-center gap-3">
                <span class="text-sm font-medium text-gray-300">Status:</span>
                <span :class="getStatusBadgeClass(status.status)" class="px-3 py-1 rounded-full text-sm font-medium">
                  {{ getStatusText(status.status) }}
                </span>
              </div>

              <!-- Status Message -->
              <div class="text-white">
                {{ status.message }}
              </div>

              <!-- Transaction Details -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-4 border-t border-gray-600/30">
                <div>
                  <span class="text-sm text-gray-400">Payment Transaction:</span>
                  <div class="text-white font-mono text-sm break-all">
                    {{ status.txId }}
                  </div>
                </div>
                <div v-if="status.cirxTransferTxId">
                  <span class="text-sm text-gray-400">CIRX Transfer Transaction:</span>
                  <div class="text-white font-mono text-sm break-all">
                    {{ status.cirxTransferTxId }}
                  </div>
                </div>
              </div>

              <!-- Refresh Button -->
              <div class="pt-4">
                <button
                  @click="console.log('🔄 Refresh Status button clicked'); handleRefreshStatus()"
                  :disabled="loading || isRateLimited"
                  class="px-4 py-2 bg-gray-700 hover:bg-gray-600 disabled:opacity-50 text-white text-sm rounded-lg transition-colors"
                >
                  {{ getRefreshButtonText() }}
                </button>
                
                <!-- Rate limit notice -->
                <div v-if="rateLimitCooldown > 0" class="mt-2 text-xs text-gray-400">
                  Please wait {{ rateLimitCooldown }}s before refreshing again
                </div>
              </div>
            </div>
          </div>

          <!-- Error Display -->
          <div v-if="error" class="mt-6 p-4 bg-red-900/50 border border-red-600/50 rounded-xl">
            <div class="text-red-200">
              <strong>Error:</strong> {{ error }}
            </div>
          </div>
        </div>

        <!-- Transaction Table -->
        <div class="space-modal rounded-2xl p-6 mb-6 shadow-2xl shadow-cyan-500/20 relative gradient-border-animated border border-white/10 overflow-hidden">
          <!-- Additional star layers for random blinking -->
          <div class="star-layer-1"></div>
          <div class="star-layer-2"></div>
          
          <h2 class="text-2xl font-semibold text-white mb-4 relative z-10 text-center">
            Recent Transactions
          </h2>
          <div class="relative z-10">
            <TransactionTable />
          </div>
        </div>


      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useApiClient } from '~/composables/core/useApiClient.js'

// Page metadata
definePageMeta({
  title: 'Transaction Status',
  layout: 'default'
})

// API client integration
const apiClient = useApiClient()

// Component state
const swapId = ref('')
const status = ref(null)
const error = ref(null)
const loading = ref(false)
const lastSwapId = ref(null)

// Rate limiting state
const lastRequestTime = ref(0)
const rateLimitCooldown = ref(0)
const isRateLimited = ref(false)
const RATE_LIMIT_DELAY = 10000 // 10 seconds between requests

// Rate limiting logic
const startCooldown = () => {
  lastRequestTime.value = Date.now()
  isRateLimited.value = true
  
  const updateCooldown = () => {
    const elapsed = Date.now() - lastRequestTime.value
    const remaining = Math.max(0, RATE_LIMIT_DELAY - elapsed)
    
    if (remaining > 0) {
      rateLimitCooldown.value = Math.ceil(remaining / 1000)
      setTimeout(updateCooldown, 1000)
    } else {
      rateLimitCooldown.value = 0
      isRateLimited.value = false
    }
  }
  
  updateCooldown()
}

// Check if rate limited
const canMakeRequest = () => {
  const elapsed = Date.now() - lastRequestTime.value
  return elapsed >= RATE_LIMIT_DELAY
}

// Load last swap ID from localStorage
onMounted(() => {
  lastSwapId.value = localStorage.getItem('lastSwapId')
  
  // Check if swap ID was passed in URL params
  const route = useRoute()
  if (route.query.swapId) {
    swapId.value = route.query.swapId
    // Allow initial load without rate limiting
    checkStatus()
  }
})

// Rate-limited handlers
const handleCheckStatus = () => {
  if (!canMakeRequest()) {
    console.log('⏱️ Request rate limited')
    return
  }
  startCooldown()
  checkStatus()
}

const handleRefreshStatus = () => {
  if (!canMakeRequest()) {
    console.log('⏱️ Refresh rate limited')
    return
  }
  startCooldown()
  checkStatus()
}

// Button text helpers
const getQueryButtonText = () => {
  if (loading.value) return 'Checking...'
  if (isRateLimited.value && rateLimitCooldown.value > 0) return `Wait ${rateLimitCooldown.value}s`
  return 'Check'
}

const getRefreshButtonText = () => {
  if (loading.value) return 'Refreshing...'
  if (isRateLimited.value && rateLimitCooldown.value > 0) return `Wait ${rateLimitCooldown.value}s`
  return 'Refresh Status'
}

// Check transaction status (internal method)
const checkStatus = async () => {
  console.log('🔍 Status check initiated for swapId:', swapId.value)
  
  if (!swapId.value) {
    error.value = 'Please enter a Swap ID'
    console.log('❌ No swap ID provided')
    return
  }

  try {
    loading.value = true
    error.value = null
    status.value = null

    console.log('📡 Calling transaction status API...')
    const result = await apiClient.get(`/transactions/${swapId.value}/status`, {
      context: { operation: 'transaction_status_check', swapId: swapId.value }
    })
    console.log('📦 API Response:', result)
    
    if (result.success) {
      // Transform the response to match expected format
      status.value = {
        success: true,
        status: result.data.status,
        message: result.data.message,
        txId: result.data.payment_info?.payment_tx_id || result.data.txId,
        cirxTransferTxId: result.data.recipient_info?.cirx_transfer_tx_id || result.data.cirxTransferTxId || null,
        phase: result.data.phase,
        progress: result.data.progress,
        transactionId: result.data.transaction_id
      }
      console.log('✅ Status updated successfully')
    } else {
      error.value = 'Failed to fetch transaction status'
      console.log('❌ API returned success:false')
    }
  } catch (err) {
    console.error('❌ Status check failed:', err)
    error.value = err.message || 'Failed to check transaction status'
  } finally {
    loading.value = false
    console.log('🏁 Status check completed')
  }
}

// Test backend connection
const testBackendConnection = async () => {
  console.log('🧪 Testing backend connection...')
  try {
    const config = useRuntimeConfig()
    const apiUrl = `${config.public.apiBaseUrl}/health`
    console.log('📡 Testing URL:', apiUrl)
    
    const response = await fetch(apiUrl)
    const data = await response.json()
    console.log('✅ Backend health check:', data)
    
    // Test transaction status endpoint with dummy ID
    const statusUrl = `${config.public.apiBaseUrl}/transactions/test-123/status`
    console.log('📡 Testing status URL:', statusUrl)
    
    const statusResponse = await fetch(statusUrl)
    console.log('📦 Status response status:', statusResponse.status)
    console.log('📦 Status response:', await statusResponse.text())
    
  } catch (err) {
    console.error('❌ Backend connection test failed:', err)
  }
}

// Get status badge styling
const getStatusBadgeClass = (status) => {
  const classes = {
    'pending_payment_verification': 'bg-yellow-500/20 text-yellow-300 border border-yellow-500/30',
    'payment_verified': 'bg-blue-500/20 text-blue-300 border border-blue-500/30',
    'cirx_transfer_pending': 'bg-blue-500/20 text-blue-300 border border-blue-500/30',
    'cirx_transfer_initiated': 'bg-blue-500/20 text-blue-300 border border-blue-500/30',
    'completed': 'bg-green-500/20 text-green-300 border border-green-500/30',
    'failed_payment_verification': 'bg-red-500/20 text-red-300 border border-red-500/30',
    'failed_cirx_transfer': 'bg-red-500/20 text-red-300 border border-red-500/30'
  }
  
  return classes[status] || 'bg-gray-500/20 text-gray-300 border border-gray-500/30'
}

// Get human-readable status text
const getStatusText = (status) => {
  const texts = {
    'pending_payment_verification': 'Payment Verification Pending',
    'payment_verified': 'Payment Verified',
    'cirx_transfer_pending': 'CIRX Transfer Pending',
    'cirx_transfer_initiated': 'CIRX Transfer Initiated',
    'completed': 'Completed',
    'failed_payment_verification': 'Payment Verification Failed',
    'failed_cirx_transfer': 'CIRX Transfer Failed'
  }
  
  return texts[status] || status
}
</script>

<style scoped>
/* Gradient border that's always animated (for modal) */
.gradient-border-animated {
  position: relative;
  border: 1px solid #00ff88;
  border-radius: 1rem;
  transition: all 0.3s ease;
  animation: border-color-cycle 75s ease infinite;
}

@keyframes border-color-cycle {
  0% { border-color: #00ff88; }
  25% { border-color: #00d9ff; }
  50% { border-color: #8b5cf6; }
  75% { border-color: #a855f7; }
  100% { border-color: #00ff88; }
}

/* Space Theme Modal with Particles */
.space-modal {
  background: linear-gradient(135deg, rgba(0, 0, 0, 0.95), rgba(15, 15, 35, 0.9));
  border: 1px solid rgba(255, 255, 255, 0.1);
  box-shadow: 
    0 8px 32px 0 rgba(0, 0, 0, 0.6),
    inset 0 1px 0 rgba(255, 255, 255, 0.05);
  position: relative;
  overflow: hidden;
}

.space-modal::before {
  content: '';
  position: absolute;
  top: -50%;
  left: -50%;
  width: 200%;
  height: 200%;
  background-image: 
    radial-gradient(2px 2px at 20px 30px, rgba(255, 255, 255, 0.8), transparent),
    radial-gradient(2px 2px at 40px 70px, rgba(200, 200, 255, 0.6), transparent),
    radial-gradient(1px 1px at 90px 40px, rgba(255, 255, 255, 0.9), transparent),
    radial-gradient(1px 1px at 130px 80px, rgba(255, 200, 200, 0.5), transparent),
    radial-gradient(2px 2px at 160px 30px, rgba(255, 255, 255, 0.7), transparent),
    radial-gradient(1px 1px at 200px 90px, rgba(200, 255, 255, 0.6), transparent),
    radial-gradient(1px 1px at 240px 50px, rgba(255, 255, 255, 0.8), transparent),
    radial-gradient(2px 2px at 280px 10px, rgba(255, 255, 255, 0.9), transparent),
    radial-gradient(1px 1px at 320px 70px, rgba(255, 255, 200, 0.4), transparent),
    radial-gradient(1px 1px at 360px 20px, rgba(255, 255, 255, 0.7), transparent),
    radial-gradient(3px 3px at 80px 120px, rgba(255, 255, 255, 0.8), transparent),
    radial-gradient(1px 1px at 220px 140px, rgba(200, 200, 255, 0.5), transparent),
    radial-gradient(2px 2px at 300px 100px, rgba(255, 255, 255, 0.6), transparent),
    radial-gradient(1px 1px at 180px 60px, rgba(255, 200, 255, 0.3), transparent);
  background-repeat: repeat;
  background-size: 400px 200px;
  animation: starsMove 40s linear infinite;
  pointer-events: none;
  z-index: 0;
}

.space-modal::after {
  content: '';
  position: absolute;
  top: -50%;
  left: -50%;
  width: 200%;
  height: 200%;
  background-image: 
    radial-gradient(2px 2px at 45px 85px, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.9) 50%, transparent),
    radial-gradient(1px 1px at 125px 45px, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.7) 50%, transparent),
    radial-gradient(2px 2px at 245px 125px, rgba(200, 200, 255, 0), rgba(200, 200, 255, 0.8) 50%, transparent),
    radial-gradient(1px 1px at 315px 65px, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.6) 50%, transparent),
    radial-gradient(3px 3px at 185px 25px, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.9) 50%, transparent);
  background-repeat: repeat;
  background-size: 400px 200px;
  animation: starsMove 40s linear infinite, randomBlink1 8s ease-in-out infinite;
  pointer-events: none;
  z-index: 0;
}

/* Additional star layers for random blinking */
.space-modal .star-layer-1 {
  position: absolute;
  top: -50%;
  left: -50%;
  width: 200%;
  height: 200%;
  background-image: 
    radial-gradient(1px 1px at 60px 110px, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.8) 50%, transparent),
    radial-gradient(2px 2px at 340px 45px, rgba(200, 255, 200, 0), rgba(200, 255, 200, 0.7) 50%, transparent),
    radial-gradient(1px 1px at 150px 180px, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.6) 50%, transparent);
  background-repeat: repeat;
  background-size: 400px 200px;
  animation: starsMove 40s linear infinite, randomBlink2 12s ease-in-out infinite;
  pointer-events: none;
  z-index: 0;
}

.space-modal .star-layer-2 {
  position: absolute;
  top: -50%;
  left: -50%;
  width: 200%;
  height: 200%;
  background-image: 
    radial-gradient(1px 1px at 275px 155px, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.9) 50%, transparent),
    radial-gradient(2px 2px at 95px 75px, rgba(255, 200, 255, 0), rgba(255, 200, 255, 0.6) 50%, transparent),
    radial-gradient(1px 1px at 385px 135px, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.7) 50%, transparent);
  background-repeat: repeat;
  background-size: 400px 200px;
  animation: starsMove 40s linear infinite, randomBlink3 16s ease-in-out infinite;
  pointer-events: none;
  z-index: 0;
}

.space-modal > * {
  position: relative;
  z-index: 1;
}

/* Animation keyframes */
@keyframes starsMove {
  0% {
    transform: translateX(0px) translateY(0px);
  }
  100% {
    transform: translateX(-400px) translateY(-200px);
  }
}

@keyframes randomBlink1 {
  0% { opacity: 0.3; }
  15% { opacity: 0.8; }
  20% { opacity: 0.3; }
  45% { opacity: 0.3; }
  50% { opacity: 1; }
  55% { opacity: 0.3; }
  80% { opacity: 0.3; }
  85% { opacity: 0.9; }
  90% { opacity: 0.3; }
  100% { opacity: 0.3; }
}

@keyframes randomBlink2 {
  0% { opacity: 0.4; }
  25% { opacity: 0.4; }
  30% { opacity: 0.9; }
  35% { opacity: 0.4; }
  65% { opacity: 0.4; }
  70% { opacity: 1; }
  75% { opacity: 0.4; }
  100% { opacity: 0.4; }
}

@keyframes randomBlink3 {
  0% { opacity: 0.2; }
  10% { opacity: 0.7; }
  15% { opacity: 0.2; }
  40% { opacity: 0.2; }
  60% { opacity: 0.2; }
  65% { opacity: 0.8; }
  70% { opacity: 0.2; }
  90% { opacity: 0.2; }
  95% { opacity: 0.6; }
  100% { opacity: 0.2; }
}
</style>