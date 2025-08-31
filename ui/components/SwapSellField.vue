<template>
  <div>
    <div class="bg-red-500 p-4 text-white font-bold text-2xl mb-4">🚨 SELL FIELD COMPONENT IS HERE 🚨</div>
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
        @focus="(e) => { console.log('🚨 SELL field received focus!'); handleFocus(e); }"
        @blur="handleBlur"
        @paste="handlePaste"
        type="text"
        inputmode="decimal"
        pattern="[0-9,]*\.?[0-9]*"
        placeholder="0.0"
        :class="[
          'w-full pl-4 pr-32 py-4 text-xl font-semibold bg-transparent border-4 border-green-400 border-b-0 rounded-t-xl text-white placeholder-gray-500 transition-all duration-300',
          activeTab === 'liquid' 
            ? 'hover:bg-circular-primary/5 focus:bg-circular-primary/5 focus:ring-2 focus:ring-circular-primary/50 focus:outline-none' 
            : 'hover:bg-circular-purple/5 focus:bg-circular-purple/5 focus:ring-2 focus:ring-circular-purple/50 focus:outline-none'
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
import { useNumberInput } from '~/composables/UseFormattedNumbers'
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