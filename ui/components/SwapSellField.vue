<template>
  <div>
    <div class="flex justify-between items-center mb-3">
      <label class="text-sm font-medium text-white">Sell</label>
      <span 
        v-if="balance && balance !== '0.0'" 
        class="text-sm cursor-pointer hover:text-white transition-colors text-gray-400" 
        @click="$emit('set-max')"
      >
        Balance: {{ balance }} {{ token }}
      </span>
    </div>
    
    <div class="relative token-input-container">
      <input
        :value="displayValue"
        @input="handleAmountInput($event.target.value)"
        @focus="handleFocus"
        @blur="handleBlur"
        @paste="handlePaste"
        type="text"
        inputmode="decimal"
        pattern="[0-9,]*\.?[0-9]*"
        placeholder="0.0"
        :class="[
          'w-full pl-4 pr-32 py-4 text-xl font-semibold bg-transparent border rounded-xl text-white placeholder-gray-500 transition-all duration-300',
          activeTab === 'liquid' 
            ? 'border-gray-600/50 hover:border-circular-primary focus:border-circular-primary focus:ring-2 focus:ring-circular-primary/50 focus:outline-none' 
            : 'border-gray-600/50 hover:border-circular-purple focus:border-circular-purple focus:ring-2 focus:ring-circular-purple/50 focus:outline-none'
        ]"
        :disabled="loading"
        @keypress="handleKeypress"
      />
      
      <div class="absolute inset-y-0 right-0 flex items-center pr-4 token-selector-wrapper">
        <TokenSelector
          :selected-token="token"
          :active-tab="activeTab"
          :loading="loading"
          @select="$emit('update:token', $event)"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { useNumberInput } from '~/composables/useNumberInput'
import { watch } from 'vue'

const props = defineProps({
  amount: {
    type: String,
    required: true
  },
  token: {
    type: String,
    required: true
  },
  balance: {
    type: String,
    default: '0.0'
  },
  loading: {
    type: Boolean,
    default: false
  },
  activeTab: {
    type: String,
    required: true
  }
})

const emit = defineEmits(['update:amount', 'update:token', 'set-max', 'input-changed'])

// Use the number input composable with comma formatting
const {
  displayValue,
  rawValue,
  handleInput,
  handleFocus,
  handleBlur,
  handleKeypress,
  handlePaste
} = useNumberInput(props.amount, {
  decimals: 8,
  allowCommas: true,
  formatOnBlur: true
})

// Handle amount input changes with comma formatting
const handleAmountInput = (value) => {
  handleInput(value)
  // Emit the clean numeric value for calculations
  emit('update:amount', rawValue.value)
  emit('input-changed')
}

// External amount changes are now handled by the useNumberInput composable watcher

// Keypress validation is now handled directly by the composable's handleKeypress function
</script>