<template>
  <VDropdown :distance="10" :auto-placement="true">
    <template #trigger>
      <button
        :class="[
          'flex items-center gap-2 px-3 py-2 rounded-full border transition-all duration-300',
          activeTab === 'liquid' 
            ? 'border-circular-primary/30 hover:border-circular-primary bg-circular-primary/10' 
            : 'border-circular-purple/30 hover:border-circular-purple bg-circular-purple/10'
        ]"
        :disabled="loading"
      >
        <img 
          :src="getTokenLogo('ETH')" 
          :alt="'ETH'"
          class="w-5 h-5 rounded-full"
          @error="handleImageError"
        />
        
        <span class="font-medium text-white text-sm">
          {{ getTokenSymbol('ETH') }}
        </span>
        
        <svg 
          width="12" 
          height="12" 
          viewBox="0 0 24 24" 
          fill="none" 
          :class="[
            'transition-transform duration-200',
            activeTab === 'liquid' ? 'text-circular-primary' : 'text-circular-purple'
          ]"
        >
          <path d="M7 10L12 15L17 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
    </template>

    <template #default>
      <div class="bg-gray-800 border border-gray-700 rounded-xl shadow-xl z-10 min-w-[140px] py-1">
        <button
          v-for="token in availableTokens"
          :key="token.symbol"
          @click="selectToken(token.symbol)"
          :class="[
            'w-full flex items-center gap-2 px-3 py-2 hover:bg-gray-700 transition-colors first:rounded-t-xl last:rounded-b-xl',
            token.symbol === 'ETH' ? 'bg-gray-700' : ''
          ]"
        >
          <img 
            :src="token.logo" 
            :alt="token.symbol"
            class="w-5 h-5 rounded-full"
            @error="handleImageError"
          />
          <div class="text-left">
            <div class="font-medium text-white text-sm">{{ token.symbol }}</div>
            <div class="text-xs text-gray-400">{{ token.name }}</div>
          </div>
          
          <!-- Checkmark for selected token -->
          <svg 
            v-if="token.symbol === 'ETH'"
            class="w-4 h-4 text-circular-primary ml-auto"
            viewBox="0 0 24 24" 
            fill="none"
          >
            <polyline points="20,6 9,17 4,12" stroke="currentColor" stroke-width="2" fill="none"/>
          </svg>
        </button>
      </div>
    </template>
  </VDropdown>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  activeTab: {
    type: String,
    required: true
  },
  loading: {
    type: Boolean,
    default: false
  }
})


// Available tokens based on connected wallet chain
const availableTokens = computed(() => {
  // TODO: Get available tokens from AppKit based on connected chain
  // For now, return static list for Ethereum mainnet
  return [
    {
      symbol: 'ETH',
      name: 'Ethereum',
      logo: '/tokens/eth.svg'
    },
    {
      symbol: 'USDC',
      name: 'USD Coin',
      logo: '/tokens/usdc.svg'
    },
    {
      symbol: 'USDT',
      name: 'Tether',
      logo: '/tokens/usdt.svg'
    }
  ]
})

// Token utilities
const getTokenLogo = (tokenSymbol) => {
  const token = availableTokens.value.find(t => t.symbol === tokenSymbol)
  return token?.logo || '/tokens/default.svg'
}

const getTokenSymbol = (tokenSymbol) => {
  // Handle special cases like USDC_SOL -> USDC
  return tokenSymbol.replace('_SOL', '')
}

const selectToken = (tokenSymbol) => {
  // Update the global wallet store instead of emitting to parent
  walletStore.selectInputToken(tokenSymbol)
}

const handleImageError = (event) => {
  // Fallback to default token icon
  event.target.src = '/tokens/default.svg'
}
</script>