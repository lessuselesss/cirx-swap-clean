<template>
  <div>
    <div class="flex justify-between items-center mb-3">
      <label class="text-sm font-medium text-white">Sell</label>
      <span 
        v-if="token && balance !== null" 
        class="balance-display pr-3 cursor-pointer" 
        @click="handleMaxClick"
        @dblclick="$emit('balance-refresh')"
        title="Click to set max amount"
      >
        Balance: {{ balance }} {{ token }}
      </span>
      <span v-else-if="token" class="balance-display pr-3">
        Balance: -
      </span>
      <span v-else class="balance-display pr-3">
        Balance: -
      </span>
    </div>
    
    <div class="relative">
      <input
        ref="amountInputRef"
        :value="amount"
        @input="handleAmountInput($event.target.value)"
        @keypress="handleKeypress"
        type="text"
        inputmode="decimal"
        pattern="[0-9]*\.?[0-9]*"
        placeholder="0.0"
        :class="[
          'w-full pl-4 pr-20 py-4 text-xl font-semibold bg-transparent border-4 border-blue-400 border-b-0 rounded-t-xl text-white placeholder-gray-500 transition-all duration-300',
          'hover:bg-circular-primary/5 hover:border hover:border-circular-primary focus:bg-circular-primary/5 focus:ring-2 focus:ring-circular-primary/50 focus:outline-none',
          loading && 'opacity-50'
        ]"
        :disabled="loading"
      />
      
      <div class="absolute inset-y-0 right-0 flex items-center pr-4">
        <!-- Debug Info (dev mode only) -->
        <div v-if="$nuxt.isDevMode" class="text-xs text-gray-500 mr-2">
          Active Tab: {{ activeTab }}
        </div>
        
        <!-- Token Selector -->
        <div class="token-dropdown-container relative z-[100]" ref="tokenSelectorContainer">
          <button
            type="button"
            @click="toggleTokenDropdown"
            class="flex items-center gap-2 px-3 py-2 rounded-full bg-gray-700/50 hover:bg-gray-700/70 transition-colors"
            :disabled="loading"
          >
            <img 
              v-if="token"
              :src="getTokenLogo(token)" 
              :alt="token"
              class="w-4 h-4 rounded-full"
              @error="handleImageError"
            />
            <svg 
              v-else
              class="text-gray-400 w-4 h-4"
              fill="none" 
              stroke="currentColor" 
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122" />
            </svg>
            <span v-if="token" class="font-semibold text-white text-sm">
              {{ token }}
            </span>
            <span v-else class="font-semibold text-sm" style="color: #00e3a3;">
              Select
            </span>
            <svg 
              :class="['text-gray-400 transition-transform w-3 h-3', showTokenDropdown && 'rotate-180']" 
              fill="none" 
              stroke="currentColor" 
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
            
            <!-- Debug indicator -->
            <div 
              v-if="$nuxt.isDevMode"
              class="w-2 h-2 bg-blue-400 rounded-full"
              title="Token selector is visible"
            ></div>
          </button>
          
          <!-- Token Dropdown -->
          <div 
            v-if="showTokenDropdown"
            class="absolute top-full right-0 mt-2 w-48 bg-gray-800 border border-gray-700 rounded-lg shadow-xl z-50"
          >
            <div class="py-2">
              <button
                v-for="tokenOption in availableTokens"
                :key="tokenOption.value"
                type="button"
                @click="selectToken(tokenOption.value)"
                class="w-full px-4 py-3 text-left hover:bg-gray-700/50 transition-colors flex items-center gap-3"
                :class="{ 'bg-circular-primary/10 border-l-2 border-circular-primary': token === tokenOption.value }"
              >
                <img 
                  :src="getTokenLogo(tokenOption.value)" 
                  :alt="tokenOption.label"
                  class="w-6 h-6 rounded-full"
                  @error="handleImageError"
                />
                <div>
                  <div class="font-medium text-white">{{ tokenOption.label }}</div>
                  <div class="text-xs text-gray-400">{{ tokenOption.name || tokenOption.label }}</div>
                </div>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- USD Value -->
    <div v-if="amount && parseFloat(amount) > 0 && token" class="mt-2 text-right">
      <span class="text-sm text-gray-400">
        ≈ ${{ formatUsdValue(amount, token) }}
      </span>
    </div>

    <!-- Loading indicator -->
    <div v-if="loading" class="mt-2 flex items-center justify-center">
      <div class="flex items-center gap-2 text-sm text-gray-400">
        <svg class="animate-spin w-4 h-4" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <span>Loading...</span>
      </div>
    </div>

    <!-- Backdrop -->
    <div
      v-if="showTokenDropdown"
      @click="closeTokenDropdown"
      class="fixed inset-0 bg-black/5 z-40"
    ></div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useNumberInput } from '~/composables/useFormattedNumbers'

const props = defineProps({
  amount: {
    type: String,
    default: '0.0'
  },
  token: {
    type: String,
    default: null
  },
  balance: {
    type: [String, Number],
    default: null
  },
  activeTab: {
    type: String,
    required: true
  },
  loading: {
    type: Boolean,
    default: false
  },
  livePrices: {
    type: Object,
    default: () => ({})
  }
})

const emit = defineEmits(['update:amount', 'update:token', 'input-changed', 'set-max', 'balance-refresh'])

// Component state
const showTokenDropdown = ref(false)
const tokenSelectorContainer = ref(null)
const amountInputRef = ref(null)

// Available tokens based on context
const availableTokens = computed(() => [
  { value: 'ETH', label: 'ETH', name: 'Ethereum' },
  { value: 'USDC', label: 'USDC', name: 'USD Coin' },
  { value: 'USDT', label: 'USDT', name: 'Tether USD' }
])

// Use the number input composable
const {
  handleKeypress
} = useNumberInput(props.amount, {
  decimals: 8,
  allowCommas: true,
  formatOnBlur: true
})

// Handle amount input
const handleAmountInput = (value) => {
  emit('update:amount', value)
  emit('input-changed')
}

// Handle max balance click
const handleMaxClick = () => {
  emit('set-max')
}

// Token selection
const toggleTokenDropdown = () => {
  showTokenDropdown.value = !showTokenDropdown.value
}

const closeTokenDropdown = () => {
  showTokenDropdown.value = false
}

const selectToken = (tokenValue) => {
  emit('update:token', tokenValue)
  closeTokenDropdown()
}

// Token logo helper
const getTokenLogo = (token) => {
  const logos = {
    ETH: '/eth-icon.svg',
    USDC: '/usdc-icon.svg',
    USDT: '/usdt-icon.svg',
    SOL: '/sol-icon.svg'
  }
  return logos[token] || '/placeholder-token.svg'
}

// USD value formatting
const formatUsdValue = (amount, token) => {
  const numAmount = parseFloat(amount)
  if (isNaN(numAmount) || !props.livePrices[token]) return '0.00'
  
  const usdValue = numAmount * props.livePrices[token]
  return new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(usdValue)
}

// Image error fallback
const handleImageError = (event) => {
  event.target.style.display = 'none'
  const fallback = document.createElement('div')
  fallback.className = 'w-4 h-4 rounded-full bg-gradient-to-r from-blue-400 to-purple-500 flex items-center justify-center text-white text-xs font-bold'
  fallback.textContent = '?'
  event.target.parentNode.replaceChild(fallback, event.target)
}

// Close dropdown on outside click
const handleOutsideClick = (event) => {
  if (tokenSelectorContainer.value && !tokenSelectorContainer.value.contains(event.target)) {
    closeTokenDropdown()
  }
}

onMounted(() => {
  document.addEventListener('click', handleOutsideClick)
})

onUnmounted(() => {
  document.removeEventListener('click', handleOutsideClick)
})
</script>

<style scoped>
/* Input styling for consistent appearance */
input[type="text"] {
  -webkit-appearance: none;
  -moz-appearance: textfield;
}

/* Balance display styling */
.balance-display {
  @apply text-sm text-gray-400 font-medium transition-colors;
}

.balance-display:hover {
  @apply text-gray-300;
}
</style>