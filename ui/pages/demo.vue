<template>
  <div class="container mx-auto px-4 py-8">
    <div class="max-w-2xl mx-auto">
      <!-- Header -->
      <div class="mb-8 text-center">
        <h1 class="text-3xl font-bold text-white mb-2">Transaction Status Demo</h1>
        <p class="text-gray-400">Test the transaction status tracking system</p>
      </div>

      <!-- Demo Controls -->
      <div class="bg-gray-800/50 rounded-xl p-6 border border-gray-700/50 mb-8">
        <h2 class="text-xl font-semibold text-white mb-4">Demo Controls</h2>
        
        <div class="flex gap-4 mb-4">
          <button 
            @click="createDemo"
            :disabled="demoActive"
            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Start Demo Transaction
          </button>
          
          <button 
            v-if="demoActive"
            @click="advancePhase"
            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700"
          >
            Next Phase
          </button>
          
          <button 
            v-if="demoActive"
            @click="resetDemo"
            class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700"
          >
            Reset
          </button>
        </div>

        <div v-if="demoActive" class="text-sm text-gray-400">
          <p>Demo Transaction ID: <code class="text-green-400">{{ demoTransaction.id }}</code></p>
          <p>Current Phase: <span class="text-yellow-400">{{ currentPhase }}</span> ({{ currentProgress }}%)</p>
        </div>
      </div>

      <!-- Transaction Progress Display -->
      <div v-if="demoActive" class="bg-gray-800/50 rounded-xl p-6 border border-gray-700/50 mb-8">
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center space-x-3">
            <div class="text-2xl">{{ getCurrentPhaseConfig().icon }}</div>
            <div>
              <h3 class="text-lg font-semibold text-white">{{ getCurrentPhaseConfig().title }}</h3>
              <p class="text-sm text-gray-400">{{ demoTransaction.id.substring(0, 16) }}...</p>
            </div>
          </div>
          <div class="px-3 py-1 rounded-full text-sm font-medium"
               :class="getStatusBadgeClass()">
            {{ currentPhase.replace('_', ' ') }}
          </div>
        </div>

        <!-- Progress Bar -->
        <div class="mb-4">
          <div class="flex justify-between items-center mb-2">
            <span class="text-sm text-gray-400">Progress</span>
            <span class="text-sm font-medium text-white">{{ currentProgress }}%</span>
          </div>
          
          <div class="w-full bg-gray-700 rounded-full h-2">
            <div 
              class="h-2 rounded-full transition-all duration-500 ease-out"
              :class="getProgressBarClass()"
              :style="{ width: `${currentProgress}%` }"
            />
          </div>
        </div>

        <!-- Status Message -->
        <div class="bg-gray-900/50 rounded-lg p-3">
          <p class="text-gray-300 text-sm">{{ getCurrentPhaseConfig().message }}</p>
        </div>
      </div>

      <!-- Toast Demo -->
      <div class="bg-gray-800/50 rounded-xl p-6 border border-gray-700/50">
        <h2 class="text-xl font-semibold text-white mb-4">Toast Notification Test</h2>
        <p class="text-gray-400 mb-4">Click to test individual phase notifications</p>
        
        <div class="grid grid-cols-2 gap-3">
          <button
            v-for="(phase, key) in phases"
            :key="key"
            @click="showTestToast(key, phase)"
            class="flex items-center space-x-2 px-3 py-2 rounded-lg text-sm transition-colors"
            :class="getPhaseButtonClass(phase.color)"
          >
            <span>{{ phase.icon }}</span>
            <span>{{ phase.title }}</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

useHead({
  title: 'Transaction Status Demo - CIRX Swap'
})

// Demo state
const demoActive = ref(false)
const demoTransaction = ref(null)
const currentPhase = ref('initiated')
const currentProgress = ref(0)

// Transaction phases
const phases = {
  initiated: { title: 'Transaction Started', message: 'Your swap has been initiated', icon: '🚀', color: 'blue' },
  awaiting_payment: { title: 'Awaiting Payment', message: 'Please complete your payment', icon: '⏳', color: 'yellow' },
  verifying_payment: { title: 'Verifying Payment', message: 'Confirming payment on blockchain', icon: '🔍', color: 'blue' },
  payment_confirmed: { title: 'Payment Confirmed', message: 'Payment verified! Preparing transfer', icon: '✅', color: 'green' },
  preparing_transfer: { title: 'Preparing Transfer', message: 'Setting up CIRX transfer', icon: '⚙️', color: 'blue' },
  transferring_cirx: { title: 'Sending CIRX', message: 'Transferring tokens to your address', icon: '📤', color: 'blue' },
  completed: { title: 'Swap Complete!', message: 'CIRX tokens sent successfully', icon: '🎉', color: 'green' }
}

const phaseOrder = ['initiated', 'awaiting_payment', 'verifying_payment', 'payment_confirmed', 'preparing_transfer', 'transferring_cirx', 'completed']

const progressMap = {
  initiated: 10,
  awaiting_payment: 20,
  verifying_payment: 40,
  payment_confirmed: 60,
  preparing_transfer: 70,
  transferring_cirx: 85,
  completed: 100
}

// Computed properties
const getCurrentPhaseConfig = () => phases[currentPhase.value] || phases.initiated

const getStatusBadgeClass = () => {
  const color = getCurrentPhaseConfig().color
  return {
    'bg-blue-900/50 text-blue-300 border border-blue-600/30': color === 'blue',
    'bg-green-900/50 text-green-300 border border-green-600/30': color === 'green',
    'bg-yellow-900/50 text-yellow-300 border border-yellow-600/30': color === 'yellow',
    'bg-red-900/50 text-red-300 border border-red-600/30': color === 'red'
  }
}

const getProgressBarClass = () => {
  const color = getCurrentPhaseConfig().color
  return {
    'bg-blue-500': color === 'blue',
    'bg-green-500': color === 'green',
    'bg-yellow-500': color === 'yellow',
    'bg-red-500': color === 'red'
  }
}

const getPhaseButtonClass = (color) => {
  return {
    'bg-blue-900/30 hover:bg-blue-900/50 text-blue-300 border border-blue-600/30': color === 'blue',
    'bg-green-900/30 hover:bg-green-900/50 text-green-300 border border-green-600/30': color === 'green',
    'bg-yellow-900/30 hover:bg-yellow-900/50 text-yellow-300 border border-yellow-600/30': color === 'yellow',
    'bg-red-900/30 hover:bg-red-900/50 text-red-300 border border-red-600/30': color === 'red'
  }
}

// Demo functions
function createDemo() {
  demoTransaction.value = {
    id: 'demo-' + Math.random().toString(36).substring(2, 15)
  }
  demoActive.value = true
  currentPhase.value = 'initiated'
  currentProgress.value = progressMap.initiated
  
  showToast('Transaction Started', 'Demo transaction initiated successfully!', 'blue')
}

function advancePhase() {
  const currentIndex = phaseOrder.indexOf(currentPhase.value)
  if (currentIndex < phaseOrder.length - 1) {
    const nextPhase = phaseOrder[currentIndex + 1]
    currentPhase.value = nextPhase
    currentProgress.value = progressMap[nextPhase]
    
    const phaseConfig = phases[nextPhase]
    showToast(phaseConfig.title, phaseConfig.message, phaseConfig.color)
  }
}

function resetDemo() {
  demoActive.value = false
  demoTransaction.value = null
  currentPhase.value = 'initiated'
  currentProgress.value = 0
}

function showTestToast(phaseKey, phase) {
  showToast(phase.title, phase.message, phase.color)
}

async function showToast(title, message, color = 'blue') {
  // Simple browser notification simulation
  console.log(`${title}: ${message}`)
  
  // Use safe toast system
  try {
    const { safeToast } = await import('~/utils/toast.js')
    if (color === 'green') {
      safeToast.success(`${title}: ${message}`)
    } else {
      safeToast.error(`${title}: ${message}`)
    }
  } catch (error) {
    // Fallback to browser notification
    if ('Notification' in window) {
      new Notification(title, { body: message })
    } else {
      alert(`${title}: ${message}`)
    }
  }
}
</script>