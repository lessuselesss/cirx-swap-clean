<template>
  <div :class="['input-section', fieldType === 'input' ? 'input-section-top' : 'input-section-bottom']">
    <div class="input-header">
      <label class="text-sm font-medium text-white">{{ label }}</label>
      <span 
        v-if="showBalance"
        :class="['balance-display pr-3', { 'cursor-pointer': canClickBalance }]" 
        @click="canClickBalance && $emit('balance-click')"
        @dblclick="canClickBalance && $emit('balance-refresh')"
      >
        Balance: {{ formattedBalance }}
      </span>
    </div>
    
    <div class="input-content">
      <input
        :ref="inputRef"
        :value="amount"
        @input="$emit('amount-change', $event.target.value)"
        type="text"
        inputmode="decimal"
        pattern="[0-9]*\.?[0-9]*"
        :placeholder="placeholder"
        class="amount-input"
        :disabled="disabled"
        :readonly="readonly"
        @keypress="$emit('keypress', $event)"
      />
      
      <TokenSelector
        :selectedToken="selectedToken"
        :connectedWallet="connectedWallet"
        :disabled="disabled"
        @token-selected="$emit('token-selected', $event)"
      />
    </div>

    <!-- USD Value Display -->
    <div v-if="showUsdValue && usdValue" class="usd-value">
      ~${{ usdValue }}
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import TokenSelector from './TokenSelector.vue'

const props = defineProps({
  fieldType: {
    type: String,
    required: true,
    validator: (value) => ['input', 'output'].includes(value)
  },
  label: {
    type: String,
    required: true
  },
  amount: {
    type: String,
    default: ''
  },
  selectedToken: {
    type: String,
    default: null
  },
  balance: {
    type: [String, Number, null],
    default: null
  },
  connectedWallet: {
    type: String,
    default: null
  },
  placeholder: {
    type: String,
    default: '0.0'
  },
  disabled: {
    type: Boolean,
    default: false
  },
  readonly: {
    type: Boolean,
    default: false
  },
  showBalance: {
    type: Boolean,
    default: true
  },
  showUsdValue: {
    type: Boolean,
    default: false
  },
  usdValue: {
    type: String,
    default: null
  },
  inputRef: {
    type: String,
    default: 'fieldInput'
  }
})

defineEmits([
  'amount-change',
  'token-selected', 
  'balance-click',
  'balance-refresh',
  'keypress'
])

const formattedBalance = computed(() => {
  if (props.balance === null) return '-'
  if (props.selectedToken && props.balance !== null) {
    return `${props.balance} ${props.selectedToken}`
  }
  return '-'
})

const canClickBalance = computed(() => {
  return props.fieldType === 'input' && props.balance !== null && props.selectedToken
})
</script>

<style scoped>
.input-section {
  @apply relative;
}

.input-section-top {
  @apply mb-4;
}

.input-section-bottom {
  @apply mt-4;
}

.input-header {
  @apply flex justify-between items-center mb-2;
}

.balance-display {
  @apply text-xs text-gray-400;
}

.balance-display.cursor-pointer:hover {
  @apply text-gray-300;
}

.input-content {
  @apply flex items-center gap-3 p-4 bg-gray-800/50 border border-gray-600/50 rounded-xl focus-within:border-cyan-500/50 transition-colors;
}

.amount-input {
  @apply flex-1 bg-transparent text-white text-lg font-medium placeholder-gray-400 outline-none;
}

.usd-value {
  @apply text-xs text-gray-400 mt-1 text-right;
}
</style>