<template>
  <div class="mb-6">
    <label class="block text-sm font-medium text-white mb-2">
      Recipient Address
      <span class="text-gray-400 text-xs ml-1">(Where should we send the CIRX?)</span>
    </label>
    
    <div class="relative">
      <input
        :value="modelValue"
        @input="handleInput"
        @blur="handleValidation"
        type="text"
        placeholder="0x1234...abcd (ETH/CIRX) or name.eth"
        :class="[
          'w-full px-4 py-3 text-sm bg-transparent border rounded-xl text-white placeholder-gray-500 transition-all duration-300',
          error 
            ? 'border-red-500/50 focus:border-red-500 focus:ring-2 focus:ring-red-500/50' 
            : 'border-gray-600/50 hover:border-gray-500 focus:border-circular-primary focus:ring-2 focus:ring-circular-primary/50',
          'focus:outline-none'
        ]"
      />
      
      <!-- Address type indicator -->
      <div v-if="addressType && !error" class="absolute inset-y-0 right-0 flex items-center pr-3">
        <span :class="[
          'px-2 py-1 text-xs rounded-full',
          addressType === 'ethereum' ? 'bg-blue-500/20 text-blue-400' : 
          addressType === 'circular' ? 'bg-green-500/20 text-green-400' : 
          'bg-purple-500/20 text-purple-400'
        ]">
          {{ addressType === 'ethereum' ? 'ETH' : 
             addressType === 'circular' ? 'CIRX' : 
             'ENS' }}
        </span>
      </div>
      
      <!-- Error icon -->
      <div v-if="error" class="absolute inset-y-0 right-0 flex items-center pr-3">
        <svg class="w-5 h-5 text-red-400" viewBox="0 0 24 24" fill="none">
          <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
          <line x1="15" y1="9" x2="9" y2="15" stroke="currentColor" stroke-width="2"/>
          <line x1="9" y1="9" x2="15" y2="15" stroke="currentColor" stroke-width="2"/>
        </svg>
      </div>
    </div>
    
    <!-- Error message -->
    <div v-if="error" class="mt-2 text-sm text-red-400">
      {{ error }}
    </div>
    
    <!-- Help text -->
    <div v-else class="mt-2 text-xs text-gray-500">
      Enter a Circular (0x...64 chars), Ethereum (0x...40 chars), or ENS name (name.eth)
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { getAddressType } from '../utils/addressFormatting.js'

const props = defineProps({
  modelValue: {
    type: String,
    default: ''
  },
  error: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['update:modelValue', 'validate'])

// Local state
const addressType = ref('')

// Handle input changes
const handleInput = (event) => {
  const value = event.target.value
  emit('update:modelValue', value)
  
  // Clear previous validation
  addressType.value = ''
  
  // Determine address type using our utility
  if (value) {
    const detectedType = getAddressType(value)
    if (detectedType) {
      addressType.value = detectedType
    } else if (value.endsWith('.eth')) {
      addressType.value = 'ens'
    }
  }
}

// Handle validation on blur
const handleValidation = () => {
  emit('validate', props.modelValue)
}
</script>