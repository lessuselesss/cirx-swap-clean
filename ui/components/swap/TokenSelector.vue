<template>
  <div class="token-dropdown-container relative z-[100]" ref="tokenSelectorContainer">
    <!-- Token Selector Button -->
    <button
      type="button"
      @click="isOpen = !isOpen"
      class="token-display-right flex items-center gap-2 rounded-full bg-gray-700/50 hover:bg-gray-700/70 transition-colors"
      :disabled="disabled"
    >
      <img 
        v-if="selectedToken"
        :src="getTokenLogo(selectedToken)" 
        :alt="selectedToken"
        class="rounded-full"
        style="width: 16px; height: 16px;"
      />
      <svg 
        v-else
        class="text-gray-400"
        style="width: 16px; height: 16px;"
        fill="none" 
        stroke="currentColor" 
        viewBox="0 0 24 24"
      >
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122" />
      </svg>
      <span v-if="selectedToken" class="font-semibold text-white" style="font-size: 0.8rem; letter-spacing: -0.01em;">
        {{ getTokenSymbol(selectedToken) }}
      </span>
      <span v-else class="font-semibold" style="color: #00e3a3; font-size: 0.8rem; letter-spacing: -0.01em;">
        Select
      </span>
      <svg 
        :class="['text-gray-400 transition-transform', isOpen && 'rotate-180']" 
        style="width: 12px; height: 12px;"
        fill="none" 
        stroke="currentColor" 
        viewBox="0 0 24 24"
      >
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
      </svg>
    </button>
    
    <!-- Token Dropdown -->
    <div 
      v-if="isOpen"
      class="token-dropdown-simple"
    >
      <template v-if="connectedWallet === 'phantom'">
        <button
          v-for="token in solanaTokens"
          :key="token.value"
          type="button"
          @click="selectToken(token.value)"
          class="token-option"
        >
          <img 
            :src="getTokenLogo(token.value)" 
            :alt="token.label"
            class="token-icon"
          />
          <span class="token-symbol">{{ token.label }}</span>
        </button>
      </template>
      <template v-else>
        <button
          v-for="token in ethereumTokens"
          :key="token.value"
          type="button"
          @click="selectToken(token.value)"
          class="token-option"
        >
          <img 
            :src="getTokenLogo(token.value)" 
            :alt="token.label"
            class="token-icon"
          />
          <span class="token-symbol">{{ token.label }}</span>
        </button>
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useTokenHelpers } from '~/composables/iuseTokenHelpers'

const props = defineProps({
  selectedToken: {
    type: String,
    default: null
  },
  connectedWallet: {
    type: String,
    default: null
  },
  disabled: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['token-selected'])

const { getTokenLogo, getTokenSymbol } = useTokenHelpers()
const isOpen = ref(false)

const solanaTokens = [
  { value: 'SOL', label: 'SOL' },
  { value: 'USDC_SOL', label: 'USDC' }
]

const ethereumTokens = [
  { value: 'ETH', label: 'ETH' },
  { value: 'USDC', label: 'USDC' },
  { value: 'USDT', label: 'USDT' }
]

function selectToken(tokenValue) {
  emit('token-selected', tokenValue)
  isOpen.value = false
}
</script>

<style scoped>
.token-dropdown-simple {
  @apply absolute top-full right-0 mt-1 bg-gray-800 border border-gray-600 rounded-lg shadow-lg z-50 min-w-[120px];
}

.token-option {
  @apply flex items-center gap-2 w-full px-3 py-2 text-left hover:bg-gray-700 transition-colors first:rounded-t-lg last:rounded-b-lg;
}

.token-icon {
  @apply w-4 h-4 rounded-full;
}

.token-symbol {
  @apply text-white font-medium;
}

.token-display-right {
  @apply px-3 py-2;
}
</style>