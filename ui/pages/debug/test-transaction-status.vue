<template>
  <div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
      <!-- Header -->
      <div class="mb-8">
        <h1 class="text-3xl font-bold text-white mb-2">Transaction Status Test Page</h1>
        <p class="text-gray-400">Test the transaction status tracking system with simulated transactions</p>
      </div>

      <!-- Controls -->
      <div class="bg-gray-800/50 rounded-xl p-6 border border-gray-700/50 mb-8">
        <h2 class="text-xl font-semibold text-white mb-4">Demo Controls</h2>
        
        <div class="flex flex-wrap gap-4 mb-4">
          <UButton 
            color="green" 
            @click="createDemoTransaction('initiated')"
            :loading="creatingDemo"
          >
            Create Demo Transaction
          </UButton>
          
          <UButton 
            v-if="demoTransaction"
            color="blue" 
            @click="advanceTransaction"
            :loading="advancing"
          >
            Advance to Next Phase
          </UButton>
          
          <UButton 
            v-if="demoTransaction"
            color="red" 
            variant="outline"
            @click="resetDemo"
          >
            Reset Demo
          </UButton>
        </div>

        <div v-if="demoTransaction" class="mt-4 p-4 bg-gray-900/50 rounded-lg">
          <h3 class="text-sm font-medium text-gray-300 mb-2">Demo Transaction ID</h3>
          <code class="text-xs text-green-400 bg-gray-800 px-2 py-1 rounded">{{ demoTransaction.id }}</code>
        </div>
      </div>

      <!-- Transaction Progress Display -->
      <div v-if="demoTransaction" class="mb-8">
        <h2 class="text-xl font-semibold text-white mb-4">Live Transaction Progress</h2>
        <TransactionProgress
          :transaction="demoTransaction"
          :phase-config="transactionPhases[demoTransaction.phase] || {}"
          :show-actions="false"
        />
      </div>

      <!-- Toast Demo -->
      <div class="bg-gray-800/50 rounded-xl p-6 border border-gray-700/50 mb-8">
        <h2 class="text-xl font-semibold text-white mb-4">Toast Notification Test</h2>
        <p class="text-gray-400 mb-4">Test individual toast notifications for each transaction phase</p>
        
        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
          <UButton
            v-for="(phase, key) in transactionPhases"
            :key="key"
            :color="phase.color"
            variant="outline"
            size="sm"
            @click="showPhaseToast(key, phase)"
          >
            {{ phase.icon }} {{ phase.title }}
          </UButton>
        </div>
      </div>

      <!-- All Tracked Transactions -->
      <div v-if="allTransactions.length > 0" class="mb-8">
        <h2 class="text-xl font-semibold text-white mb-4">All Tracked Transactions</h2>
        <div class="space-y-4">
          <div 
            v-for="transaction in allTransactions"
            :key="transaction.id"
            class="bg-gray-800/30 rounded-lg p-4"
          >
            <div class="flex items-center justify-between">
              <div class="flex items-center space-x-3">
                <div class="text-lg">{{ transactionPhases[transaction.phase]?.icon || '🔄' }}</div>
                <div>
                  <div class="text-white font-medium">{{ transaction.id.substring(0, 8) }}...</div>
                  <div class="text-sm text-gray-400">{{ transaction.phase }} ({{ transaction.progress }}%)</div>
                </div>
              </div>
              <div class="flex space-x-2">
                <UBadge :color="transactionPhases[transaction.phase]?.color || 'gray'">
                  {{ transaction.phase }}
                </UBadge>
                <UButton 
                  color="red" 
                  variant="ghost" 
                  size="xs"
                  @click="removeTransaction(transaction.id)"
                >
                  Remove
                </UButton>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Documentation -->
      <div class="bg-gray-800/30 rounded-xl p-6 border border-gray-700/30">
        <h2 class="text-xl font-semibold text-white mb-4">How It Works</h2>
        <div class="space-y-3 text-gray-300 text-sm">
          <p><strong>Transaction Phases:</strong> The system tracks 9 different phases from initiation to completion.</p>
          <p><strong>Real-time Updates:</strong> The frontend polls the backend every 3 seconds for status updates.</p>
          <p><strong>Toast Notifications:</strong> Users receive notifications when transactions move between phases.</p>
          <p><strong>Progress Tracking:</strong> Visual progress bars and estimated completion times keep users informed.</p>
          <p><strong>Error Handling:</strong> Failed transactions are clearly marked with retry options where appropriate.</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onUnmounted } from 'vue'

// Page metadata
useHead({
  title: 'Transaction Status Test - CIRX Swap',
  meta: [
    { name: 'description', content: 'Test page for transaction status tracking system' }
  ]
})

// Create a mock transaction status system for testing
const transactionPhases = {
  initiated: { title: 'Transaction Started', message: 'Your swap has been initiated', icon: '🚀', color: 'blue' },
  awaiting_payment: { title: 'Awaiting Payment', message: 'Please complete your payment', icon: '⏳', color: 'yellow' },
  verifying_payment: { title: 'Verifying Payment', message: 'Confirming payment on blockchain', icon: '🔍', color: 'blue' },
  payment_confirmed: { title: 'Payment Confirmed', message: 'Payment verified! Preparing transfer', icon: '✅', color: 'green' },
  preparing_transfer: { title: 'Preparing Transfer', message: 'Setting up CIRX transfer', icon: '⚙️', color: 'blue' },
  transferring_cirx: { title: 'Sending CIRX', message: 'Transferring tokens to your address', icon: '📤', color: 'blue' },
  completed: { title: 'Swap Complete!', message: 'CIRX tokens sent successfully', icon: '🎉', color: 'green' },
  payment_failed: { title: 'Payment Failed', message: 'Payment verification failed', icon: '❌', color: 'red' },
  transfer_failed: { title: 'Transfer Failed', message: 'CIRX transfer failed', icon: '❌', color: 'red' }
}

// Mock functions
const allTransactions = ref([])
const trackTransaction = (id, options) => ({ id, phase: 'initiated', progress: 10 })
const removeTransaction = (id) => {}
const updateTransactionStatus = (id, data) => {}

// Demo state
const demoTransaction = ref(null)
const creatingDemo = ref(false)
const advancing = ref(false)

// Demo phases in order
const demoPhases = [
  'initiated',
  'awaiting_payment', 
  'verifying_payment',
  'payment_confirmed',
  'preparing_transfer',
  'transferring_cirx',
  'completed'
]

/**
 * Create a demo transaction that simulates the full flow
 */
async function createDemoTransaction(startPhase = 'initiated') {
  creatingDemo.value = true
  
  try {
    // Generate a mock transaction ID
    const transactionId = 'demo-' + Math.random().toString(36).substring(2, 15)
    
    // Create the transaction using our composable
    demoTransaction.value = trackTransaction(transactionId, {
      showToasts: true,
      pollingInterval: 2000, // 2 second polling for demo
      onStatusChange: (statusData, previousPhase) => {
        console.log(`Demo transaction moved from ${previousPhase} to ${statusData.phase}`)
      },
      onComplete: (statusData) => {
        console.log('Demo transaction completed!', statusData)
      },
      onError: (error) => {
        console.error('Demo transaction error:', error)
      }
    })
    
    // Simulate initial status
    updateDemoTransactionStatus(startPhase)
    
  } catch (error) {
    console.error('Failed to create demo transaction:', error)
    useNuxtApp().$toast?.add({
      title: 'Demo Error',
      description: 'Failed to create demo transaction',
      color: 'red'
    })
  } finally {
    creatingDemo.value = false
  }
}

/**
 * Advance the demo transaction to the next phase
 */
async function advanceTransaction() {
  if (!demoTransaction.value) return
  
  advancing.value = true
  
  try {
    const currentPhaseIndex = demoPhases.indexOf(demoTransaction.value.phase)
    const nextPhaseIndex = currentPhaseIndex + 1
    
    if (nextPhaseIndex < demoPhases.length) {
      const nextPhase = demoPhases[nextPhaseIndex]
      updateDemoTransactionStatus(nextPhase)
    } else {
      useNuxtApp().$toast?.add({
        title: 'Demo Complete',
        description: 'Transaction has reached the final phase',
        color: 'green'
      })
    }
    
  } catch (error) {
    console.error('Failed to advance transaction:', error)
  } finally {
    advancing.value = false
  }
}

/**
 * Update the demo transaction status manually
 */
function updateDemoTransactionStatus(phase) {
  if (!demoTransaction.value) return
  
  // Simulate backend response
  const mockStatusData = {
    transaction_id: demoTransaction.value.id,
    status: getStatusFromPhase(phase),
    phase: phase,
    progress: getProgressFromPhase(phase),
    message: getMessageFromPhase(phase),
    payment_info: {
      payment_tx_id: '0x' + Math.random().toString(16).substring(2, 42),
      payment_chain: 'ethereum',
      amount_paid: '1000.00',
      payment_token: 'USDC'
    },
    recipient_info: {
      cirx_recipient_address: '0x' + Math.random().toString(16).substring(2, 42),
      cirx_transfer_tx_id: phase === 'completed' ? '0x' + Math.random().toString(16).substring(2, 42) : null
    },
    timestamps: {
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString()
    },
    estimated_completion: getEstimatedCompletion(phase)
  }
  
  // Update the transaction status using our composable
  const { updateTransactionStatus } = useTransactionStatus()
  updateTransactionStatus(demoTransaction.value.id, mockStatusData)
}

/**
 * Show a toast for a specific phase
 */
function showPhaseToast(phaseKey, phaseConfig) {
  // Import and use safe toast
  import('~/utils/toast.js').then(({ safeToast }) => {
    if (phaseConfig.color === 'green') {
      safeToast.success(`${phaseConfig.title}: ${phaseConfig.message}`)
    } else {
      safeToast.error(`${phaseConfig.title}: ${phaseConfig.message}`)
    }
  })
}

/**
 * Reset the demo
 */
function resetDemo() {
  if (demoTransaction.value) {
    removeTransaction(demoTransaction.value.id)
    demoTransaction.value = null
  }
}

// Helper functions for mock data
function getStatusFromPhase(phase) {
  const statusMap = {
    'initiated': 'initiated',
    'awaiting_payment': 'payment_pending',
    'verifying_payment': 'pending_payment_verification',
    'payment_confirmed': 'payment_verified',
    'preparing_transfer': 'cirx_transfer_pending',
    'transferring_cirx': 'cirx_transfer_initiated',
    'completed': 'completed'
  }
  return statusMap[phase] || 'initiated'
}

function getProgressFromPhase(phase) {
  const progressMap = {
    'initiated': 10,
    'awaiting_payment': 20,
    'verifying_payment': 40,
    'payment_confirmed': 60,
    'preparing_transfer': 70,
    'transferring_cirx': 85,
    'completed': 100
  }
  return progressMap[phase] || 0
}

function getMessageFromPhase(phase) {
  return transactionPhases[phase]?.message || 'Processing transaction...'
}

function getEstimatedCompletion(phase) {
  const estimateMap = {
    'initiated': null,
    'awaiting_payment': null,
    'verifying_payment': 5,
    'payment_confirmed': 3,
    'preparing_transfer': 3,
    'transferring_cirx': 2,
    'completed': 0
  }
  return estimateMap[phase]
}

// Auto-cleanup on unmount
onUnmounted(() => {
  if (demoTransaction.value) {
    removeTransaction(demoTransaction.value.id)
  }
})
</script>