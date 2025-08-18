<template>
  <div class="bg-gray-800/50 rounded-xl p-6 border border-gray-700/50">
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
      <div class="flex items-center space-x-3">
        <div class="text-2xl">{{ phaseConfig?.icon || '🔄' }}</div>
        <div>
          <h3 class="text-lg font-semibold text-white">
            {{ phaseConfig?.title || 'Processing Transaction' }}
          </h3>
          <p class="text-sm text-gray-400">
            Transaction ID: {{ shortId }}
          </p>
        </div>
      </div>
      
      <!-- Status Badge -->
      <UBadge 
        :color="phaseConfig?.color || 'gray'" 
        variant="subtle"
        size="lg"
      >
        {{ transaction?.phase?.replace('_', ' ') || 'Unknown' }}
      </UBadge>
    </div>

    <!-- Progress Bar -->
    <div class="mb-4">
      <div class="flex justify-between items-center mb-2">
        <span class="text-sm text-gray-400">Progress</span>
        <span class="text-sm font-medium text-white">{{ transaction?.progress || 0 }}%</span>
      </div>
      
      <div class="w-full bg-gray-700 rounded-full h-2">
        <div 
          class="h-2 rounded-full transition-all duration-500 ease-out"
          :class="progressBarClass"
          :style="{ width: `${transaction?.progress || 0}%` }"
        />
      </div>
    </div>

    <!-- Status Message -->
    <div class="mb-4">
      <p class="text-gray-300 text-sm leading-relaxed">
        {{ transaction?.message || phaseConfig?.message || 'Processing your transaction...' }}
      </p>
    </div>

    <!-- Estimated Time (if available) -->
    <div v-if="estimatedTime" class="mb-4">
      <div class="flex items-center space-x-2 text-sm text-gray-400">
        <UIcon name="i-heroicons-clock-20-solid" class="w-4 h-4" />
        <span>Estimated time: {{ estimatedTime }}</span>
      </div>
    </div>

    <!-- Transaction Details (collapsible) -->
    <UDisclosure v-if="showDetails">
      <template #default="{ open }">
        <UDisclosureButton class="w-full">
          <div class="flex items-center justify-between w-full text-left">
            <span class="text-sm font-medium text-gray-300">Transaction Details</span>
            <UIcon 
              :name="open ? 'i-heroicons-chevron-up-20-solid' : 'i-heroicons-chevron-down-20-solid'"
              class="w-4 h-4 text-gray-400"
            />
          </div>
        </UDisclosureButton>
        
        <UDisclosurePanel class="mt-3">
          <div class="space-y-2 text-sm">
            <div v-if="transaction?.payment_info" class="bg-gray-900/50 rounded-lg p-3">
              <h4 class="font-medium text-gray-200 mb-2">Payment Information</h4>
              <div class="space-y-1">
                <div class="flex justify-between">
                  <span class="text-gray-400">Amount:</span>
                  <span class="text-white">{{ transaction.payment_info.amount_paid }} {{ transaction.payment_info.payment_token }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-400">Chain:</span>
                  <span class="text-white">{{ transaction.payment_info.payment_chain }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-400">Tx ID:</span>
                  <span class="text-white font-mono text-xs">{{ shortPaymentId }}</span>
                </div>
              </div>
            </div>

            <div v-if="transaction?.recipient_info" class="bg-gray-900/50 rounded-lg p-3">
              <h4 class="font-medium text-gray-200 mb-2">Recipient Information</h4>
              <div class="space-y-1">
                <div class="flex justify-between">
                  <span class="text-gray-400">CIRX Address:</span>
                  <span class="text-white font-mono text-xs">{{ shortRecipientAddress }}</span>
                </div>
                <div v-if="transaction.recipient_info.cirx_transfer_tx_id" class="flex justify-between">
                  <span class="text-gray-400">CIRX Tx ID:</span>
                  <span class="text-white font-mono text-xs">{{ shortCirxTxId }}</span>
                </div>
              </div>
            </div>

            <div v-if="transaction?.timestamps" class="bg-gray-900/50 rounded-lg p-3">
              <h4 class="font-medium text-gray-200 mb-2">Timeline</h4>
              <div class="space-y-1">
                <div class="flex justify-between">
                  <span class="text-gray-400">Started:</span>
                  <span class="text-white text-xs">{{ formatTime(transaction.timestamps.created_at) }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-400">Last Update:</span>
                  <span class="text-white text-xs">{{ formatTime(transaction.timestamps.updated_at) }}</span>
                </div>
              </div>
            </div>
          </div>
        </UDisclosurePanel>
      </template>
    </UDisclosure>

    <!-- Error State -->
    <div v-if="transaction?.failure_info" class="mt-4 p-3 bg-red-900/20 border border-red-600/30 rounded-lg">
      <div class="flex items-center space-x-2 mb-2">
        <UIcon name="i-heroicons-exclamation-triangle-20-solid" class="w-5 h-5 text-red-400" />
        <span class="text-red-300 font-medium">Transaction Failed</span>
      </div>
      <p class="text-red-200 text-sm">{{ transaction.failure_info.reason }}</p>
      <div v-if="transaction.failure_info.retry_count > 0" class="mt-2 text-xs text-red-300">
        Retried {{ transaction.failure_info.retry_count }} time(s)
      </div>
    </div>

    <!-- Action Buttons -->
    <div v-if="showActions" class="mt-4 flex space-x-3">
      <UButton 
        v-if="canRetry"
        color="blue"
        variant="outline"
        size="sm"
        @click="$emit('retry')"
      >
        Retry Transaction
      </UButton>
      
      <UButton 
        v-if="isComplete"
        color="green"
        variant="outline"
        size="sm"
        @click="$emit('view-transaction')"
      >
        View on Explorer
      </UButton>
      
      <UButton 
        color="gray"
        variant="ghost"
        size="sm"
        @click="$emit('close')"
      >
        {{ isComplete ? 'Close' : 'Minimize' }}
      </UButton>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  transaction: {
    type: Object,
    required: true
  },
  phaseConfig: {
    type: Object,
    default: () => ({})
  },
  showDetails: {
    type: Boolean,
    default: true
  },
  showActions: {
    type: Boolean,
    default: true
  }
})

const emit = defineEmits(['retry', 'view-transaction', 'close'])

// Computed properties
const shortId = computed(() => {
  return props.transaction?.id?.substring(0, 8) + '...' || 'Unknown'
})

const shortPaymentId = computed(() => {
  const txId = props.transaction?.payment_info?.payment_tx_id
  return txId ? txId.substring(0, 10) + '...' + txId.substring(txId.length - 6) : 'N/A'
})

const shortRecipientAddress = computed(() => {
  const addr = props.transaction?.recipient_info?.cirx_recipient_address
  return addr ? addr.substring(0, 10) + '...' + addr.substring(addr.length - 6) : 'N/A'
})

const shortCirxTxId = computed(() => {
  const txId = props.transaction?.recipient_info?.cirx_transfer_tx_id
  return txId ? txId.substring(0, 10) + '...' + txId.substring(txId.length - 6) : 'N/A'
})

const progressBarClass = computed(() => {
  const color = props.phaseConfig?.color || 'blue'
  return {
    'bg-blue-500': color === 'blue',
    'bg-green-500': color === 'green',
    'bg-yellow-500': color === 'yellow',
    'bg-red-500': color === 'red',
    'bg-gray-500': color === 'gray'
  }
})

const estimatedTime = computed(() => {
  const minutes = props.transaction?.estimated_completion
  if (!minutes || minutes <= 0) return null
  
  if (minutes < 1) return 'Less than 1 minute'
  if (minutes === 1) return '1 minute'
  return `${minutes} minutes`
})

const canRetry = computed(() => {
  return ['payment_failed', 'transfer_failed'].includes(props.transaction?.phase)
})

const isComplete = computed(() => {
  return props.transaction?.phase === 'completed'
})

// Helper functions
function formatTime(timestamp) {
  if (!timestamp) return 'N/A'
  return new Date(timestamp).toLocaleString()
}
</script>