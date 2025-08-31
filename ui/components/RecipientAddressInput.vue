<template>
  <div class="mb-6">
    <label class="block text-sm font-medium text-white mb-2">
      <span class="flex items-center">
        Recipient Address
        <span 
          :class="[
            'w-2 h-2 rounded-full ml-2',
            hasFormatError ? 'bg-red-400' : 'bg-gray-400'
          ]"
        ></span>
      </span>
      <span class="text-gray-400 text-xs ml-1">(Where should we send the CIRX?)</span>
    </label>
    
    <div class="relative">
      <input
        ref="addressInput"
        :value="modelValue"
        @input="handleInput"
        @blur="handleValidation"
        @focus="() => console.log('🎯 CIRX Address input received focus!')"
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
      
      <!-- Address type indicator (only for valid Circular addresses) -->
      <div v-if="addressType === 'circular' && !error" class="absolute inset-y-0 right-0 flex items-center pr-3">
        <span class="px-2 py-1 text-xs rounded-full bg-green-500/20 text-green-400">
          CIRX
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
    <div v-else-if="modelValue && !modelValue.startsWith('0x')" class="mt-2 text-xs text-red-400">
      Incorrect address format. Please enter your Circular Protocol Address (0x...)
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useFormattedNumbers } from '../composables/useFormattedNumbers.js'

// Initialize composable
const { getAddressType } = useFormattedNumbers()

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
const addressInput = ref(null)
const addressType = ref('')

// Computed properties
const hasFormatError = computed(() => {
  return props.modelValue && !props.modelValue.startsWith('0x')
})

// Handle input changes
const handleInput = (event) => {
  const value = event.target.value
  emit('update:modelValue', value)
  
  // Clear previous validation
  addressType.value = ''
  
  // Only show type indicator for valid Circular addresses
  // Don't show indicators for Ethereum/Solana addresses as they're not supported
  if (value) {
    const detectedType = getAddressType(value)
    // Only set addressType for Circular addresses (which are valid for CIRX)
    if (detectedType === 'circular') {
      addressType.value = 'circular'
    }
    // Don't set addressType for ethereum/solana/ens as they should show error state
  }
}

// Handle validation on blur
const handleValidation = () => {
  emit('validate', props.modelValue)
}

// Focus the input field
const focusInput = () => {
  console.log('🎯 RecipientAddressInput: focusInput called')
  if (addressInput.value) {
    console.log('🎯 RecipientAddressInput: calling focus() on input element')
    addressInput.value.focus()
    console.log('🎯 RecipientAddressInput: focus() called, active element:', document.activeElement)
  } else {
    console.log('❌ RecipientAddressInput: addressInput ref is null')
  }
}

// Clear and focus the input field
const clearAndFocusInput = () => {
  emit('update:modelValue', '')
  if (addressInput.value) {
    addressInput.value.focus()
  }
}

// Expose methods to parent components
defineExpose({
  focusInput,
  clearAndFocusInput
})

</script>