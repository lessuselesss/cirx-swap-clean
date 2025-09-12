<template>
  <div class="mb-6">
    <div class="flex justify-between items-center mb-3">
      <div class="flex items-center gap-3">
        <label class="text-sm font-medium text-white">Circular Chain Address</label>
        <!-- Status Light -->
        <div class="flex items-center gap-2">
          <div 
            :class="[
              'w-3 h-3 rounded-full transition-all duration-200 cursor-help',
              statusLightClass
            ]"
            :title="errorMessage || 'Validation status'"
          ></div>
        </div>
      </div>
    </div>
    
    <div class="relative">
      <input
        :ref="inputRef"
        :value="address"
        type="text"
        :readonly="readonly"
        :placeholder="placeholder"
        @input="handleInput"
        @keydown="handleKeydown"
        @blur="handleBlur"
        :class="[
          'w-full pl-4 pr-12 py-3 text-sm bg-transparent border rounded-xl text-white placeholder-gray-400 transition-all duration-300',
          activeTab === 'liquid' 
            ? 'border-gray-700/70 hover:border-circular-primary focus:border-circular-primary focus:ring-2 focus:ring-circular-primary/30 focus:outline-none' 
            : 'border-gray-700/70 hover:border-circular-purple focus:border-circular-purple focus:ring-2 focus:ring-circular-purple/30 focus:outline-none'
        ]"
        :disabled="disabled"
      />
      
      <!-- Clear Button -->
      <div class="absolute inset-y-0 right-0 flex items-center pr-4">
        <button
          v-if="address"
          @click="clearAddress"
          class="text-gray-400 hover:text-white transition-colors"
          title="Clear address"
        >
          <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
            <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
          </svg>
        </button>
      </div>
    </div>
    
    <!-- Warning Message -->
    <div v-if="showWarning" class="mt-2 flex items-center gap-2 text-sm text-yellow-400">
      <img 
        v-if="showSaturnIcon" 
        src="https://avatars.githubusercontent.com/u/saturn-wallet?s=20" 
        alt="Saturn Wallet" 
        class="w-5 h-5 rounded"
        @error="$event.target.style.display = 'none'"
      />
      <span v-if="!showSaturnIcon">⚠️ Please specify a recipient address above to receive CIRX safely</span>
    </div>
    
    <!-- Error Message -->
    <div v-if="errorMessage" class="mt-2 text-sm text-red-400">
      {{ errorMessage }}
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  address: {
    type: String,
    default: ''
  },
  addressType: {
    type: String,
    default: ''
  },
  errorMessage: {
    type: String,
    default: ''
  },
  validationState: {
    type: String,
    default: 'idle',
    validator: (value) => ['idle', 'validating', 'valid', 'invalid'].includes(value)
  },
  activeTab: {
    type: String,
    required: true,
    validator: (value) => ['liquid', 'otc'].includes(value)
  },
  placeholder: {
    type: String,
    default: 'Enter a Circular Chain wallet address'
  },
  disabled: {
    type: Boolean,
    default: false
  },
  readonly: {
    type: Boolean,
    default: false
  },
  isConnected: {
    type: Boolean,
    default: false
  },
  hasClickedEnterAddress: {
    type: Boolean,
    default: false
  },
  isSaturnWalletDetected: {
    type: Boolean,
    default: false
  },
  inputRef: {
    type: String,
    default: 'addressInput'
  }
})

const emit = defineEmits([
  'address-change',
  'address-clear',
  'input-event',
  'keydown-event',
  'blur-event'
])

const statusLightClass = computed(() => {
  if (props.errorMessage) {
    return 'bg-red-500'
  }
  
  const isValidCircular = props.address && 
                         props.addressType === 'circular' && 
                         !props.errorMessage && 
                         props.validationState === 'valid'
  
  if (isValidCircular) {
    return 'bg-green-500'
  }
  
  if (props.validationState === 'validating') {
    return 'bg-yellow-500 animate-flash'
  }
  
  if (props.address && props.address.length === 66 && props.address.startsWith('0x') && props.validationState === 'idle' && !props.errorMessage) {
    return 'bg-yellow-500'
  }
  
  if (props.address && (props.address === '0' || (props.address.startsWith('0x') && props.address.length < 66)) && !props.errorMessage) {
    return 'bg-yellow-500'
  }
  
  if (!props.address) {
    return 'bg-gray-500'
  }
  
  return 'bg-gray-500'
})

const showWarning = computed(() => {
  return props.isConnected && props.hasClickedEnterAddress && !props.address
})

const showSaturnIcon = computed(() => {
  return props.isSaturnWalletDetected
})

function handleInput(event) {
  emit('input-event', event)
  emit('address-change', event.target.value)
}

function handleKeydown(event) {
  emit('keydown-event', event)
}

function handleBlur(event) {
  emit('blur-event', event)
}

function clearAddress() {
  emit('address-clear')
  emit('address-change', '')
}
</script>

<style scoped>
@keyframes flash {
  0%, 50% { opacity: 1; }
  25%, 75% { opacity: 0.5; }
}

.animate-flash {
  animation: flash 1s infinite;
}
</style>