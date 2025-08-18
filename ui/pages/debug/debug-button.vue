<template>
  <div class="container mx-auto px-4 py-8">
    <div class="max-w-2xl mx-auto">
      <h1 class="text-3xl font-bold text-white mb-6">Buy Button Debug</h1>
      
      <div class="bg-gray-800/50 rounded-xl p-6 border border-gray-700/50 mb-6">
        <h2 class="text-xl font-semibold text-white mb-4">Current State Analysis</h2>
        
        <div class="space-y-3 text-sm">
          <div class="flex justify-between items-center p-3 rounded-lg bg-gray-900/50">
            <span class="text-gray-300">1. Has Amount (> 0)</span>
            <span :class="hasAmount ? 'text-green-400' : 'text-red-400'">
              {{ hasAmount ? '✓' : '✗' }} ({{ inputAmount || 'empty' }})
            </span>
          </div>
          
          <div class="flex justify-between items-center p-3 rounded-lg bg-gray-900/50">
            <span class="text-gray-300">2. Not Loading</span>
            <span :class="notLoading ? 'text-green-400' : 'text-red-400'">
              {{ notLoading ? '✓' : '✗' }}
            </span>
          </div>
          
          <div class="flex justify-between items-center p-3 rounded-lg bg-gray-900/50">
            <span class="text-gray-300">3. Wallet Connected</span>
            <span :class="hasWallet ? 'text-green-400' : 'text-red-400'">
              {{ hasWallet ? '✓' : '✗' }}
            </span>
          </div>
          
          <div class="flex justify-between items-center p-3 rounded-lg bg-gray-900/50">
            <span class="text-gray-300">4. Valid Recipient Address</span>
            <span :class="hasValidRecipient ? 'text-green-400' : 'text-red-400'">
              {{ hasValidRecipient ? '✓' : '✗' }} 
              <span class="text-xs text-gray-500">
                ({{ recipientAddress || 'empty' }}{{ recipientAddressError ? ` - ERROR: ${recipientAddressError}` : '' }})
              </span>
            </span>
          </div>
          
          <div class="flex justify-between items-center p-3 rounded-lg bg-gray-900/50">
            <span class="text-gray-300">5. Sufficient Balance</span>
            <span :class="hasSufficientBalance ? 'text-green-400' : 'text-red-400'">
              {{ hasSufficientBalance ? '✓' : '✗' }}
              <span class="text-xs text-gray-500">
                (Balance: {{ inputBalance }}, Need: {{ inputAmount }}, Gas Reserve: {{ gasReserve }})
              </span>
            </span>
          </div>
        </div>
        
        <div class="mt-6 p-4 rounded-lg" :class="canPurchase ? 'bg-green-900/30 border border-green-600/30' : 'bg-red-900/30 border border-red-600/30'">
          <div class="flex items-center justify-between">
            <span class="font-semibold" :class="canPurchase ? 'text-green-300' : 'text-red-300'">
              Can Purchase: {{ canPurchase ? 'YES' : 'NO' }}
            </span>
            <span class="text-sm text-gray-400">
              All conditions must be ✓
            </span>
          </div>
        </div>
      </div>
      
      <div class="bg-gray-800/50 rounded-xl p-6 border border-gray-700/50 mb-6">
        <h2 class="text-xl font-semibold text-white mb-4">Test Values</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Input Amount</label>
            <input 
              v-model="inputAmount" 
              type="number" 
              step="0.001"
              class="w-full px-3 py-2 bg-gray-900 border border-gray-700 rounded-lg text-white"
              placeholder="Enter amount"
            />
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Recipient Address</label>
            <input 
              v-model="recipientAddress" 
              type="text"
              class="w-full px-3 py-2 bg-gray-900 border border-gray-700 rounded-lg text-white"
              placeholder="Enter CIRX address"
            />
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Input Balance</label>
            <input 
              v-model="inputBalance" 
              type="number" 
              step="0.001"
              class="w-full px-3 py-2 bg-gray-900 border border-gray-700 rounded-lg text-white"
              placeholder="Wallet balance"
            />
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Input Token</label>
            <select 
              v-model="inputToken"
              class="w-full px-3 py-2 bg-gray-900 border border-gray-700 rounded-lg text-white"
            >
              <option value="ETH">ETH</option>
              <option value="USDC">USDC</option>
              <option value="USDT">USDT</option>
            </select>
          </div>
        </div>
        
        <div class="mt-4 flex gap-4">
          <button 
            @click="simulateWalletConnected = !simulateWalletConnected"
            :class="simulateWalletConnected ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700'"
            class="px-4 py-2 rounded-lg text-white font-medium transition-colors"
          >
            {{ simulateWalletConnected ? 'Disconnect' : 'Connect' }} Wallet
          </button>
          
          <button 
            @click="loading = !loading"
            :class="loading ? 'bg-yellow-600 hover:bg-yellow-700' : 'bg-gray-600 hover:bg-gray-700'"
            class="px-4 py-2 rounded-lg text-white font-medium transition-colors"
          >
            {{ loading ? 'Stop' : 'Start' }} Loading
          </button>
        </div>
      </div>
      
      <!-- Sample Button (same logic as real one) -->
      <div class="bg-gray-800/50 rounded-xl p-6 border border-gray-700/50">
        <h2 class="text-xl font-semibold text-white mb-4">Test Button</h2>
        <button
          :disabled="!canPurchase || loading"
          :class="[
            'w-full py-4 px-6 rounded-xl font-semibold text-lg transition-all duration-300 border-2',
            'disabled:opacity-50 disabled:cursor-not-allowed',
            canPurchase ? 'bg-circular-primary border-circular-primary text-gray-900 hover:bg-circular-primary/90' : 'bg-gray-600 border-gray-600 text-gray-300'
          ]"
          @click="handleTestClick"
        >
          {{ loading ? 'Loading...' : 'Buy Liquid CIRX' }}
        </button>
        
        <p class="text-sm text-gray-400 mt-2 text-center">
          Button should be {{ canPurchase && !loading ? 'enabled' : 'disabled' }}
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { validateWalletAddress } from '../utils/validation.js'

useHead({
  title: 'Buy Button Debug - CIRX Swap'
})

// Reactive test values
const inputAmount = ref('1.0')
const recipientAddress = ref('')
const recipientAddressError = ref('')
const inputBalance = ref('2.0')
const inputToken = ref('ETH')
const loading = ref(false)
const simulateWalletConnected = ref(true)

// Computed conditions (same logic as SwapForm)
const hasAmount = computed(() => {
  return inputAmount.value && parseFloat(inputAmount.value) > 0
})

const notLoading = computed(() => {
  return !loading.value
})

const hasWallet = computed(() => {
  return simulateWalletConnected.value
})

const hasValidRecipient = computed(() => {
  return recipientAddress.value && !recipientAddressError.value
})

const gasReserve = computed(() => {
  return inputToken.value === 'ETH' ? 0.01 : 0
})

const hasSufficientBalance = computed(() => {
  if (!simulateWalletConnected.value) return true // Skip balance check if no wallet
  
  const inputAmountNum = parseFloat(inputAmount.value) || 0
  const balanceNum = parseFloat(inputBalance.value) || 0
  const availableBalance = Math.max(0, balanceNum - gasReserve.value)
  
  return inputAmountNum <= availableBalance
})

const canPurchase = computed(() => {
  return hasAmount.value && notLoading.value && hasWallet.value && hasValidRecipient.value && hasSufficientBalance.value
})

// Watch for address changes and validate
watch(recipientAddress, (newAddress) => {
  if (!newAddress) {
    recipientAddressError.value = ''
    return
  }
  
  const result = validateWalletAddress(newAddress, 'circular')
  if (!result.isValid) {
    if (newAddress.length === 42 && newAddress.startsWith('0x')) {
      recipientAddressError.value = 'Ethereum addresses are not supported. Please enter a valid CIRX address'
    } else {
      recipientAddressError.value = result.errors[0] || 'Invalid CIRX address format'
    }
  } else {
    recipientAddressError.value = ''
  }
})

function handleTestClick() {
  alert('Button clicked! All conditions are met.')
}
</script>