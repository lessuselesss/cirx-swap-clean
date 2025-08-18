<template>
  <div class="min-h-screen bg-circular-bg-primary">
    <header class="bg-transparent backdrop-blur-sm border-b border-gray-800/30 sticky top-0 z-50">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
          <div class="flex items-center gap-2 sm:gap-4">
            <span class="text-lg font-semibold text-white">Circular</span>
            <span class="text-xs sm:text-sm text-gray-400">Swap</span>
          </div>
          <div class="flex items-center gap-2 sm:gap-4">
            
            <NuxtLink 
              to="/history" 
              class="px-3 py-2 text-gray-400 hover:text-white transition-colors text-sm font-medium"
            >
              History
            </NuxtLink>
            
            <!-- Wallet connection button would go here -->
          </div>
        </div>
      </div>
    </header>

    
    <div class="min-h-[calc(100vh-4rem)] flex items-center justify-center p-4 md:p-8">
      <div :class="[
        'w-full mx-auto transition-all duration-500',
        (showChart || showStaking) ? 'max-w-none px-4' : 'max-w-lg'
      ]">
        <div :class="[
          'flex gap-6 items-start',
          (showChart || showStaking) ? 'flex-col lg:flex-row' : 'justify-center'
        ]">
          
          <!-- Chart and staking panels removed for now -->
          <div v-if="showChart && !showStaking" class="w-full lg:w-3/5 xl:w-2/3 h-[80vh] bg-gray-800 rounded-xl p-6">
            <div class="text-white">Chart placeholder</div>
            <button @click="showChart = false" class="text-gray-400 hover:text-white">Close</button>
          </div>
          
          <div v-if="showStaking && !showChart" class="w-full lg:w-3/5 xl:w-2/3 h-[80vh] bg-gray-800 rounded-xl p-6">
            <div class="text-white">Staking placeholder</div>
            <button @click="showStaking = false" class="text-gray-400 hover:text-white">Close</button>
          </div>
          
          
          <div :class="[
            'transition-all duration-500',
            (showChart || showStaking) ? 'w-full lg:w-2/5 xl:w-1/3 lg:min-w-[400px]' : 'w-full max-w-lg'
          ]">
        
        <div class="relative">
          
          <div class="relative bg-circular-bg-primary/80 backdrop-blur-sm border border-gray-700/50 rounded-2xl p-6 sm:p-8 overflow-hidden">
          
          <div class="flex mb-6 bg-gray-800/50 rounded-xl p-1 gap-1 overflow-hidden">
            <button
              @click="activeTab = 'liquid'"
              :class="[
                'flex-1 px-3 sm:px-4 py-3 text-sm font-medium font-michroma transition-all duration-300 rounded-lg flex items-center justify-center gap-1 sm:gap-2 flex-col md:flex-row min-w-0 basis-0',
                activeTab === 'liquid' 
                  ? 'text-circular-primary bg-circular-primary/20 border border-circular-primary/30' 
                  : 'text-gray-400 hover:text-white hover:bg-gray-700/50'
              ]"
            >
              <span class="text-xs sm:text-sm text-center leading-tight">
                <span class="block md:inline">Buy</span>
                <span class="block md:inline"> Liquid</span>
              </span>
              <span class="px-1.5 sm:px-2 py-1 text-xs bg-circular-primary text-gray-900 rounded-full font-semibold whitespace-nowrap flex-shrink-0">
                Immediate
              </span>
            </button>
            <button
              @click="activeTab = 'otc'"
              :class="[
                'flex-1 px-3 sm:px-4 py-3 text-sm font-medium font-michroma transition-all duration-300 rounded-lg flex items-center justify-center gap-1 sm:gap-2 flex-col md:flex-row min-w-0 basis-0',
                activeTab === 'otc' 
                  ? 'text-circular-purple bg-circular-purple/20 border border-circular-purple/30' 
                  : 'text-gray-400 hover:text-white hover:bg-gray-700/50'
              ]"
            >
              <span class="text-xs sm:text-sm text-center leading-tight">
                <span class="block md:inline">Buy</span>
                <span class="block md:inline"> OTC</span>
              </span>
              <div class="flex flex-col items-center gap-0.5 min-w-0 overflow-hidden max-w-full">
                <span class="px-1.5 sm:px-2 py-0.5 text-xs bg-circular-purple text-white rounded-full font-semibold whitespace-nowrap">
                  {{ otcConfig?.displayRange || '5-12%' }}
                </span>
                <span class="text-xs text-gray-400 font-normal hidden md:inline">
                  discount
                </span>
              </div>
            </button>
          </div>

          
          <form @submit.prevent="handleSwap">
            
            <div class="mb-6">
              <div class="flex justify-between items-center mb-3">
                <label class="text-sm font-medium text-white">Pay with</label>
                <span v-if="inputBalance" class="text-sm cursor-pointer hover:text-white transition-colors text-gray-400" @click="setMaxAmount">
                  Balance: {{ inputBalance }} {{ inputToken }}
                </span>
              </div>
              <div class="relative token-input-container">
                <input
                  :value="inputAmount"
                  @input="handleInputAmountChange($event.target.value)"
                  type="text"
                  inputmode="decimal"
                  pattern="[0-9]*\.?[0-9]*"
                  placeholder="0.0"
                  :class="[
                    'w-full pl-4 pr-32 py-4 text-xl font-semibold bg-transparent border rounded-xl text-white placeholder-gray-500 transition-all duration-300',
                    activeTab === 'liquid' 
                      ? 'border-gray-600/50 hover:border-circular-primary focus:border-circular-primary focus:ring-2 focus:ring-circular-primary/50 focus:outline-none' 
                      : 'border-gray-600/50 hover:border-circular-purple focus:border-circular-purple focus:ring-2 focus:ring-circular-purple/50 focus:outline-none'
                  ]"
                  :disabled="loading"
                  
                  
                  @keypress="validateNumberInput"
                />
                <div class="absolute inset-y-0 right-0 flex items-center pr-4">
                  <div class="relative token-dropdown-container">
                    <button
                      type="button"
                      @click="showTokenDropdown = !showTokenDropdown"
                      :class="[
                        'flex items-center gap-2 px-3 py-2 rounded-full border transition-all duration-300',
                        activeTab === 'liquid' 
                          ? 'border-circular-primary/30 hover:border-circular-primary bg-circular-primary/10' 
                          : 'border-circular-purple/30 hover:border-circular-purple bg-circular-purple/10'
                      ]"
                      :disabled="loading"
                    >
                      
                      <img 
                        :src="getTokenLogo(inputToken)" 
                        :alt="inputToken"
                        class="w-5 h-5 rounded-full"
                      />
                      
                      <span class="font-medium text-white text-sm">{{ getTokenSymbol(inputToken) }}</span>
                      
                      <svg 
                        width="12" 
                        height="12" 
                        viewBox="0 0 24 24" 
                        fill="none" 
                        :class="[
                          'transition-transform duration-200',
                          showTokenDropdown ? 'rotate-180' : '',
                          activeTab === 'liquid' ? 'text-circular-primary' : 'text-circular-purple'
                        ]"
                      >
                        <path d="M7 10L12 15L17 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                      </svg>
                    </button>
                    
                    
                    <div 
                      v-if="showTokenDropdown"
                      class="absolute top-full right-0 mt-2 bg-gray-800 border border-gray-700 rounded-xl shadow-xl z-10 min-w-[120px]"
                    >
                      
                      <template v-if="connectedWallet === 'phantom'">
                        <button
                          v-for="token in [{ value: 'SOL', label: 'SOL' }, { value: 'USDC_SOL', label: 'USDC' }]"
                          :key="token.value"
                          type="button"
                          @click="selectToken(token.value)"
                          class="w-full flex items-center gap-3 px-4 py-3 hover:bg-gray-700 transition-colors first:rounded-t-xl last:rounded-b-xl"
                        >
                          <img 
                            :src="getTokenLogo(token.value)" 
                            :alt="token.label"
                            class="w-5 h-5 rounded-full"
                          />
                          <span class="font-medium text-white text-sm">{{ token.label }}</span>
                        </button>
                      </template>
                      <template v-else>
                        <button
                          v-for="token in [{ value: 'ETH', label: 'ETH' }, { value: 'USDC', label: 'USDC' }, { value: 'USDT', label: 'USDT' }]"
                          :key="token.value"
                          type="button"
                          @click="selectToken(token.value)"
                          class="w-full flex items-center gap-3 px-4 py-3 hover:bg-gray-700 transition-colors first:rounded-t-xl last:rounded-b-xl"
                        >
                          <img 
                            :src="getTokenLogo(token.value)" 
                            :alt="token.label"
                            class="w-5 h-5 rounded-full"
                          />
                          <span class="font-medium text-white text-sm">{{ token.label }}</span>
                        </button>
                      </template>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            
            <div class="flex justify-center mb-6">
              <button
                type="button"
                :class="[
                  'p-3 bg-transparent border rounded-xl transition-all duration-300',
                  activeTab === 'liquid' 
                    ? 'border-gray-600/50 text-circular-primary hover:bg-circular-primary/10 hover:border-circular-primary' 
                    : 'border-gray-600/50 text-circular-purple hover:bg-circular-purple/10 hover:border-circular-purple'
                ]"
                @click="reverseSwap"
                :disabled="loading"
              >
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path d="M7 10L12 15L17 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </button>
            </div>

            
            <div class="mb-6">
              <div class="flex justify-between items-center mb-3">
                <label class="text-sm font-medium text-white">Receive</label>
                <span v-if="displayCirxBalance" class="text-sm text-gray-400">
                  Balance: {{ displayCirxBalance }} CIRX
                </span>
              </div>
              <div class="relative">
                <input
                  :value="cirxAmount"
                  @input="handleCirxAmountChange($event.target.value)"
                  @keypress="validateNumberInput"
                  type="text"
                  inputmode="decimal"
                  pattern="[0-9]*\.?[0-9]*"
                  placeholder="0.0"
                  :disabled="quoteLoading || reverseQuoteLoading"
                  style="-webkit-appearance: none; -moz-appearance: textfield;"
                  :class="[
                    'w-full pl-4 pr-20 py-4 text-xl font-semibold bg-transparent border rounded-xl text-white placeholder-gray-500 transition-all duration-300',
                    activeTab === 'liquid' 
                      ? 'border-circular-primary/40 focus:border-circular-primary' 
                      : 'border-circular-purple/40 focus:border-circular-purple',
                    'focus:outline-none',
                    (quoteLoading || reverseQuoteLoading) && 'opacity-50'
                  ]"
                />
                <div class="absolute inset-y-0 right-0 flex items-center pr-4">
                  
                  <!-- OTC Mode: Simple tier display -->
                  <div v-if="activeTab === 'otc'" class="text-xs text-purple-400">
                    OTC Discount
                  </div>
                  
                  <!-- Liquid Mode: Standard CIRX Display -->
                  <div 
                    v-else
                    class="flex items-center gap-2 px-3 py-2 rounded-full border border-circular-primary/30 bg-circular-primary/10"
                  >
                    <img 
                      :src="getTokenLogo('CIRX')" 
                      alt="CIRX"
                      class="w-5 h-5 rounded-full"
                      @error="$event.target.src = 'https://cdn.prod.website-files.com/65e472c0cd2f1bebcd7fcf73/65e483ab69e2314b250ed7dc_imageedit_1_8961069084.png'"
                    />
                    <span class="font-medium text-circular-primary text-sm">CIRX</span>
                  </div>
                  
                </div>
              </div>
              
              <!-- Loading indicator for quote calculation -->
              <div v-if="quoteLoading || reverseQuoteLoading" class="mt-2 flex items-center justify-center">
                <div class="flex items-center gap-2 text-sm text-gray-400">
                  <svg class="animate-spin w-4 h-4" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  <span v-if="reverseQuoteLoading">Calculating input amount...</span>
                  <span v-else>Getting best quote...</span>
                </div>
              </div>

              <!-- OTC Discount Tiers (show full range, highlight active) -->
              <div v-if="activeTab === 'otc'" class="mt-3 space-y-2">
                <h4 class="text-xs font-medium text-purple-300">OTC Discount Tiers</h4>
                <div class="grid grid-cols-1 gap-2">
                  <div
                    v-for="tier in discountTiers"
                    :key="tier.minAmount"
                    :class="[
                      'flex items-center justify-between p-3 rounded-lg border transition-all duration-200',
                      selectedTier && selectedTier.minAmount === tier.minAmount
                        ? 'border-purple-500/60 bg-purple-500/5'
                        : 'border-gray-600/30 hover:border-gray-500/40'
                    ]"
                  >
                    <div class="text-xs text-gray-400">Min: ${{ formatAmount(tier.minAmount) }}</div>
                    <div class="text-right text-xs">
                      <span :class="selectedTier && selectedTier.minAmount === tier.minAmount ? 'text-purple-400 font-medium' : 'text-gray-300 font-medium'">{{ tier.discount }}%</span>
                      <span class="text-gray-500 ml-1">{{ tier.vestingMonths || otcConfig?.vestingPeriod?.months || 6 }}mo</span>
                    </div>
                  </div>
                </div>
                <div v-if="currentUsd > 0 && (!selectedTier || currentUsd < lowestTierMin)" class="bg-gray-800/30 border border-gray-600/30 rounded-lg p-3 text-center">
                  <p class="text-xs text-gray-400">Below the minimum for the lowest tier. Minimum: ${{ formatAmount(lowestTierMin) }}</p>
                </div>
              </div>
            </div>

            
            <div v-if="quote" class="bg-transparent border border-gray-600/50 rounded-xl p-4 mb-6 hover:border-gray-500 transition-all duration-300">
              <div class="flex justify-between items-center mb-2">
                <span class="text-sm text-gray-400">Exchange Rate</span>
                <span class="text-sm font-medium text-white" :class="isPriceRefreshing ? 'animate-pulse' : ''">1 {{ inputToken }} = {{ quote.rate }} CIRX</span>
              </div>
              <div class="flex justify-between items-center mb-2">
                <span class="text-xs text-gray-500" :class="isPriceRefreshing ? 'animate-pulse' : ''">
                  Next price update in {{ priceCountdown }}s
                </span>
              </div>
              <div class="flex justify-between items-center mb-2">
                <span class="text-sm text-gray-400">CIRX Price</span>
                <span class="text-sm font-medium text-white">1 CIRX = {{ quote.inverseRate }} {{ inputToken }}</span>
              </div>
              <div class="flex justify-between items-center mb-2">
                <span class="text-sm text-gray-400">Platform Fee</span>
                <span class="text-sm font-medium text-white">{{ quote.fee }}%</span>
              </div>
              <div class="flex justify-between items-center mb-2">
                <span class="text-sm text-gray-400">Est. Network Fee</span>
                <span class="text-sm font-medium text-white">
                  ~{{ networkFee.eth }} ETH (~${{ networkFee.usd }})
                </span>
              </div>
              <div v-if="activeTab === 'otc' && quote.discount > 0" class="flex justify-between items-center mb-2">
                <span class="text-sm text-gray-400">OTC Discount</span>
                <span class="text-sm font-medium text-circular-primary">{{ quote.discount }}%</span>
              </div>
              <div v-if="activeTab === 'otc'" class="flex justify-between items-center">
                <span class="text-sm text-gray-400">Vesting Period</span>
                <span class="text-sm font-medium text-white">{{ otcConfig?.vestingPeriod?.months || 6 }} months ({{ otcConfig?.vestingPeriod?.type || 'linear' }})</span>
              </div>
            </div>

            
            <div class="mb-6">
              <div class="flex justify-between items-center mb-3">
                <label class="text-sm font-medium text-white">Send to another address (optional)</label>
                <button
                  @click="useConnectedWallet"
                  v-if="recipientAddress && isConnected"
                  class="text-xs text-circular-primary hover:text-circular-primary-hover transition-colors"
                >
                  Use connected wallet
                </button>
              </div>
              <div class="relative">
                <input
                  v-model="recipientAddress"
                  type="text"
                  :placeholder="isConnected ? 'Leave empty to use connected wallet' : 'Enter wallet address to receive CIRX'"
                  :class="[
                    'w-full pl-4 pr-12 py-3 text-sm bg-transparent border rounded-xl text-white placeholder-gray-500 transition-all duration-300',
                    activeTab === 'liquid' 
                      ? 'border-gray-600/50 hover:border-circular-primary focus:border-circular-primary focus:ring-2 focus:ring-circular-primary/50 focus:outline-none' 
                      : 'border-gray-600/50 hover:border-circular-purple focus:border-circular-purple focus:ring-2 focus:ring-circular-purple/50 focus:outline-none'
                  ]"
                  :disabled="loading"
                />
                <div class="absolute inset-y-0 right-0 flex items-center pr-4">
                  <button
                    v-if="recipientAddress"
                    @click="recipientAddress = ''"
                    class="text-gray-400 hover:text-white transition-colors"
                    title="Clear address"
                  >
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                      <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                    </svg>
                  </button>
                </div>
              </div>
              <div v-if="recipientAddressError" class="mt-2 text-sm text-red-400">
                {{ recipientAddressError }}
              </div>
              <div v-else-if="recipientAddress" class="mt-2 text-sm text-green-400">
                ✓ Valid {{ recipientAddressType }} address
              </div>
              <div v-else-if="isConnected" class="mt-2 text-sm text-gray-400">
                CIRX will be sent to your connected wallet: {{ shortAddress }}
              </div>
            </div>

            
            <button
              type="submit"
              :disabled="!canPurchase || loading || quoteLoading || reverseQuoteLoading"
              :class="[
                'w-full py-4 px-6 rounded-xl font-semibold text-lg transition-all duration-300',
                activeTab === 'liquid' 
                  ? 'bg-circular-primary text-gray-900 hover:bg-circular-primary-hover' 
                  : 'bg-circular-purple text-white hover:bg-purple-700',
                (!canPurchase || loading || quoteLoading || reverseQuoteLoading) && 'opacity-50 cursor-not-allowed'
              ]"
            >
              <span v-if="loading">{{ loadingText || 'Processing...' }}</span>
              <span v-else-if="quoteLoading || reverseQuoteLoading">
                {{ reverseQuoteLoading ? 'Calculating...' : 'Getting Quote...' }}
              </span>
              <span v-else-if="!inputAmount">Enter an amount</span>
              <span v-else-if="!isConnected && !recipientAddress">Connect Wallet or Enter Address</span>
              <span v-else-if="recipientAddress && recipientAddressError">Invalid Address</span>
              <span v-else-if="activeTab === 'liquid'">Buy Liquid CIRX</span>
              <span v-else>Buy OTC CIRX</span>
            </button>
          </form>
          
          
          <div v-if="!showChart && !showStaking" class="mt-4 flex justify-start gap-3">
            <button
              @click="showChart = true"
              class="inline-flex items-center gap-2 px-4 py-2 text-gray-400 hover:text-white border border-gray-600/50 hover:border-gray-500 transition-all text-sm font-medium hover:bg-gray-800/30 rounded-lg w-fit"
            >
              <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M3 3v18h18"/>
                <path d="M7 12l3-3 4 4 5-5"/>
                <circle cx="7" cy="12" r="1"/>
                <circle cx="10" cy="9" r="1"/>
                <circle cx="14" cy="13" r="1"/>
                <circle cx="19" cy="8" r="1"/>
              </svg>
              Expand Chart
            </button>
            <button
              @click="showStaking = true"
              class="inline-flex items-center gap-2 px-4 py-2 text-gray-400 hover:text-white border border-gray-600/50 hover:border-gray-500 transition-all text-sm font-medium hover:bg-gray-800/30 rounded-lg w-fit"
            >
              <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M12 2L2 7v10c0 5.55 3.84 9.74 9 11 5.16-1.26 9-5.45 9-11V7l-10-5z"/>
                <path d="M9 12l2 2 4-4"/>
              </svg>
              Staking
            </button>
          </div>
          </div>
        </div>
          </div>
        </div>
        
        <!-- Logo moved below the form -->
        <div class="mt-8 flex justify-center">
          <img 
            src="https://cdn.prod.website-files.com/65e472c0cd2f1bebcd7fcf73/65e483ab69e2314b250ed7dc_imageedit_1_8961069084.png" 
            alt="CIRX Token" 
            class="h-12 w-auto opacity-60 hover:opacity-80 transition-opacity"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
// Import components
import { getTokenPrices } from '~/services/priceService.js'

// Page metadata
definePageMeta({
  title: 'Circular Swap',
  layout: 'default'
})

// Multi-Wallet connection (using same composable as MultiWalletButton)
const { 
  isConnected, 
  account, 
  balance,
  connectedWallet,
  shortAddress,
  getTokenBalance,
  executeSwap
} = useWallet()

const walletStore = useWalletStore()
onMounted(async () => {
  try { await walletStore.initialize() } catch {}
})

// Reactive state
const activeTab = ref('liquid')
const inputAmount = ref('')
const cirxAmount = ref('')
const inputToken = ref('ETH')
const loading = ref(false)
const loadingText = ref('')
const quote = ref(null)
const showChart = ref(false)
const showStaking = ref(false)
const recipientAddress = ref('')
const recipientAddressError = ref('')
const recipientAddressType = ref('')
const showTokenDropdown = ref(false)

// Price refresh state (30s countdown)
const livePrices = ref({ ETH: 2500, USDC: 1, USDT: 1, CIRX: 1 })
const isPriceRefreshing = ref(false)
const priceCountdown = ref(30)
let countdownTimer = null

// Gas price state
const gasPriceWeiHex = ref('0x0')
const isGasRefreshing = ref(false)

const hexToBigInt = (hex) => {
  try {
    if (typeof hex !== 'string') return 0n
    return BigInt(hex)
  } catch { return 0n }
}

const fetchGasPrice = async () => {
  try {
    isGasRefreshing.value = true
    // Prefer wallet provider if available
    if (typeof window !== 'undefined' && window.ethereum?.request) {
      const gp = await window.ethereum.request({ method: 'eth_gasPrice' })
      if (gp) gasPriceWeiHex.value = gp
    } else {
      // Fallback to public RPC
      const res = await fetch('https://ethereum.publicnode.com', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ jsonrpc: '2.0', id: 1, method: 'eth_gasPrice', params: [] })
      })
      const json = await res.json()
      if (json?.result) gasPriceWeiHex.value = json.result
    }
  } catch (e) {
    console.warn('Gas price fetch failed', e)
  } finally {
    isGasRefreshing.value = false
  }
}

const startPriceCountdown = () => {
  if (countdownTimer) clearInterval(countdownTimer)
  priceCountdown.value = 30
  countdownTimer = setInterval(async () => {
    if (priceCountdown.value > 0) {
      priceCountdown.value -= 1
    } else {
      await Promise.all([refreshPrices(), fetchGasPrice()])
    }
  }, 1000)
}

const refreshPrices = async () => {
  try {
    isPriceRefreshing.value = true
    const prices = await getTokenPrices()
    // Update tracked tokens if present
    livePrices.value = {
      ETH: prices.ETH ?? livePrices.value.ETH,
      USDC: prices.USDC ?? livePrices.value.USDC,
      USDT: prices.USDT ?? livePrices.value.USDT,
      CIRX: prices.CIRX ?? livePrices.value.CIRX
    }
    // Recalculate quote if there is an input
    if (inputAmount.value && parseFloat(inputAmount.value) > 0 && lastEditedField.value === 'input') {
      const isOTC = activeTab.value === 'otc'
      const newQuote = await calculateQuoteAsync(inputAmount.value, inputToken.value, isOTC)
      if (newQuote) {
        quote.value = newQuote
        // keep cirxAmount consistent and numeric for the input field
        const cirxRaw = parseFloat(String(newQuote.cirxAmount).replace(/,/g, ''))
        if (isFinite(cirxRaw) && cirxRaw > 0) {
          cirxAmount.value = cirxRaw.toString()
        }
      }
    }
  } catch (e) {
    console.warn('Price refresh failed, keeping previous prices', e)
  } finally {
    isPriceRefreshing.value = false
    priceCountdown.value = 30
  }
}

// OTC specific state
const selectedTier = ref(null)

// Quote calculation loading state
const quoteLoading = ref(false)
const lastQuoteRequestId = ref(0)

// Bidirectional field tracking
const lastEditedField = ref('input') // 'input' or 'output'
const reverseQuoteLoading = ref(false)
const lastReverseQuoteRequestId = ref(0)

// Use wallet balances when connected, otherwise show placeholders
const inputBalance = computed(() => {
  if (!isConnected.value) {
    return '0.0'
  }
  
  // Adjust token symbol based on connected wallet
  let tokenSymbol = inputToken.value
  if (connectedWallet.value === 'phantom' && inputToken.value === 'ETH') {
    tokenSymbol = 'SOL'
  } else if (connectedWallet.value === 'phantom' && inputToken.value === 'USDC') {
    tokenSymbol = 'USDC_SOL'
  }
  
  return getTokenBalance(tokenSymbol)
})

// ETH balance for gas gating (0 when not connected)
const awaitedEthBalance = computed(() => {
  try { return getTokenBalance('ETH') } catch { return '0.0' }
})

const displayCirxBalance = computed(() => {
  return isConnected.value ? getTokenBalance('CIRX') : '0.0'
})

// Calculate slider amount based on percentage and available balance
const formatSliderAmount = computed(() => {
  const balance = parseFloat(inputBalance.value) || 0
  const amount = (balance * sliderPercentage.value) / 100
  
  // Format with appropriate precision based on amount size
  if (amount >= 1) {
    return amount.toFixed(4).replace(/\.?0+$/, '') // Remove trailing zeros
  } else {
    return amount.toFixed(6).replace(/\.?0+$/, '') // More precision for small amounts
  }
})

// Token prices (live via price service, with sane defaults)
// const tokenPrices = {
//   ETH: 2500,
//   USDC: 1,
//   USDT: 1
// }

// Dynamic fee structure
const fees = computed(() => otcConfig.value?.fees || { eth: 0.001, usdc: 0.5, usdt: 0.5 })

// Dynamic OTC configuration from hosted JSON
const otcConfig = ref({
  discountTiers: [
    { minAmount: 50000, discount: 12, vestingMonths: 6 },  // $50K+: 12%
    { minAmount: 10000, discount: 8, vestingMonths: 6 },   // $10K+: 8%  
    { minAmount: 1000, discount: 5, vestingMonths: 6 }     // $1K+: 5%
  ],
  vestingPeriod: {
    months: 6,
    type: 'linear'
  },
  fees: {
    otc: 0.15,
    liquid: 0.3
  },
  displayRange: '5-12%',
  enabled: true
})

// Fetch OTC configuration from hosted JSON
const fetchOtcConfig = async () => {
  try {
    // Fetch from local JSON file
    const configUrl = '/swap/discount.json'
    
    const response = await fetch(configUrl, {
      method: 'GET',
      headers: {
        'Accept': 'application/json',
        'Cache-Control': 'no-cache'
      }
    })
    
    if (response.ok) {
      const config = await response.json()
      
      // Validate and merge config
      if (config.discountTiers && Array.isArray(config.discountTiers)) {
        otcConfig.value = { ...otcConfig.value, ...config }
        console.log('OTC config updated from hosted JSON:', config)
      }
    } else {
      console.warn('Failed to fetch OTC config, using defaults')
    }
  } catch (error) {
    console.warn('Error fetching OTC config:', error.message)
    // Continue with default config
  }
}

// Use dynamic discount tiers
const discountTiers = computed(() => otcConfig.value?.discountTiers || [])

// Helpers for tier UI
const currentUsd = computed(() => {
  const amt = parseFloat(inputAmount.value) || 0
  const px = livePrices.value[inputToken.value] || 0
  return +(amt * px).toFixed(2)
})
const lowestTierMin = computed(() => {
  const tiers = discountTiers.value || []
  if (!tiers.length) return 0
  return Math.min(...tiers.map(t => t.minAmount))
})

// Computed properties  
const canPurchase = computed(() => {
  // Basic requirements
  const hasAmount = inputAmount.value && parseFloat(inputAmount.value) > 0
  const notLoading = !loading.value && !quoteLoading.value && !reverseQuoteLoading.value
  
  // Address validation
  const addressValid = validateRecipientAddress(recipientAddress.value)
  
  // Either connected wallet OR valid recipient address required
  const hasValidRecipient = isConnected.value || (recipientAddress.value && addressValid)
  
  // Balance validation - only check if wallet is connected
  const hasSufficientBalance = !isConnected.value || (() => {
    const inputAmountNum = parseFloat(inputAmount.value) || 0
    const balanceNum = parseFloat(inputBalance.value) || 0
    
    // For ETH, reserve gas fees (0.01 ETH)
    const gasReserve = inputToken.value === 'ETH' ? 0.01 : 0
    const availableBalance = Math.max(0, balanceNum - gasReserve)
    
    return inputAmountNum <= availableBalance
  })()
  
  // Network fee gating
  const ethBal = parseFloat(awaitedEthBalance.value)
  const feeEth = parseFloat(networkFee.value.eth)
  const tokenBal = parseFloat(inputBalance.value)

  let hasSufficientForFees = true
  if (walletStore.isConnected) {
    if (inputToken.value === 'ETH') {
      hasSufficientForFees = ethBal >= ((parseFloat(inputAmount.value) || 0) + (feeEth || 0))
    } else {
      hasSufficientForFees = tokenBal >= (parseFloat(inputAmount.value) || 0) && ethBal >= (feeEth || 0)
    }
  }

  return hasAmount && notLoading && hasValidRecipient && hasSufficientBalance && hasSufficientForFees
})

// Calculate discount based on USD amount and return both percent and tier
const getTierForUsd = (usdAmount) => {
  // Tiers are defined as minAmount thresholds (e.g., 1000, 10000, 50000)
  // Choose the highest tier that the amount qualifies for
  const tiers = [...discountTiers.value].sort((a, b) => b.minAmount - a.minAmount)
  for (const t of tiers) {
    if (usdAmount >= t.minAmount) return t
  }
  return null
}

const calculateDiscount = (usdAmount) => {
  const tier = getTierForUsd(usdAmount)
  return tier ? tier.discount : 0
}

// Calculate quote for purchase (forward: input token -> CIRX)
const calculateQuote = (amount, token, isOTC = false) => {
  if (!amount || parseFloat(amount) <= 0) return null

  const inputValue = parseFloat(amount)
  const tokenPriceUsd = livePrices.value[token] || 0
  const cirxPriceUsd = livePrices.value.CIRX || 0
  if (tokenPriceUsd <= 0 || cirxPriceUsd <= 0) return null

  const totalUsdValue = inputValue * tokenPriceUsd

  // Calculate fee
  const feeRate = isOTC ? fees.value.otc : fees.value.liquid
  const fee = (inputValue * feeRate) / 100
  const amountAfterFee = Math.max(0, inputValue - fee)
  const usdAfterFee = amountAfterFee * tokenPriceUsd

  // Convert USD to CIRX using live CIRX/USD price
  let cirxReceived = usdAfterFee / cirxPriceUsd
  let discount = 0

  // Apply OTC discount as additional CIRX
  if (isOTC) {
    discount = calculateDiscount(totalUsdValue)
    cirxReceived = cirxReceived * (1 + discount / 100)
  }

  // Rates for display (numeric strings, no grouping)
  const rateCirxPerToken = tokenPriceUsd / cirxPriceUsd
  const inverseRateTokenPerCirx = cirxPriceUsd / tokenPriceUsd

  return {
    rate: rateCirxPerToken.toFixed(6),
    inverseRate: inverseRateTokenPerCirx.toFixed(8),
    fee: feeRate,
    discount: discount,
    cirxAmount: cirxReceived.toFixed(6),
    usdValue: totalUsdValue.toFixed(2)
  }
}

// Async quote calculation with loading states
const calculateQuoteAsync = async (amount, token, isOTC = false) => {
  if (!amount || parseFloat(amount) <= 0) return null
  const requestId = ++lastQuoteRequestId.value
  quoteLoading.value = true
  try {
    await new Promise(resolve => setTimeout(resolve, 300))
    if (requestId !== lastQuoteRequestId.value) return null
    return calculateQuote(amount, token, isOTC)
  } finally {
    if (requestId === lastQuoteRequestId.value) {
      quoteLoading.value = false
    }
  }
}

// Reverse quote (output CIRX -> required input token amount)
const calculateReverseQuote = (cirxAmt, token, isOTC = false) => {
  const cirxValue = parseFloat(cirxAmt)
  if (!cirxValue || cirxValue <= 0) return null

  const tokenPriceUsd = livePrices.value[token] || 0
  const cirxPriceUsd = livePrices.value.CIRX || 0
  if (tokenPriceUsd <= 0 || cirxPriceUsd <= 0) return null

  // USD value of desired CIRX
  let usdNeeded = cirxValue * cirxPriceUsd

  // Remove OTC bonus to find base after-fee requirement
  let discount = 0
  if (isOTC) {
    discount = calculateDiscount(usdNeeded)
    const bonusMultiplier = 1 + discount / 100
    usdNeeded = usdNeeded / bonusMultiplier
  }

  // amountAfterFee (in input token units)
  const feeRate = isOTC ? fees.value.otc : fees.value.liquid
  const feeMultiplier = 1 - feeRate / 100
  if (feeMultiplier <= 0) return null

  const amountAfterFeeTokens = usdNeeded / tokenPriceUsd
  const inputAmountNeeded = amountAfterFeeTokens / feeMultiplier

  // Build forward quote for UI consistency
  const forward = calculateQuote(inputAmountNeeded.toString(), token, isOTC)

  return {
    inputAmount: inputAmountNeeded,
    forwardQuote: forward
  }
}

const calculateReverseQuoteAsync = async (cirxAmt, token, isOTC = false) => {
  if (!cirxAmt || parseFloat(cirxAmt) <= 0) return null
  const requestId = ++lastReverseQuoteRequestId.value
  reverseQuoteLoading.value = true
  try {
    await new Promise(resolve => setTimeout(resolve, 300))
    if (requestId !== lastReverseQuoteRequestId.value) return null
    return calculateReverseQuote(cirxAmt, token, isOTC)
  } finally {
    if (requestId === lastReverseQuoteRequestId.value) {
      reverseQuoteLoading.value = false
    }
  }
}

// Computed network fee estimation
const GAS_ESTIMATES = {
  approval: 50000,       // conservative ERC-20 approve
  liquid: 180000,        // liquid swap placeholder
  otc: 220000            // otc (mint + vesting) placeholder
}

const estimatedGasUnits = computed(() => {
  const base = activeTab.value === 'otc' ? GAS_ESTIMATES.otc : GAS_ESTIMATES.liquid
  // If paying with ERC-20 (non-ETH), add approval buffer
  const needsApproval = ['USDC', 'USDT'].includes(inputToken.value)
  return base + (needsApproval ? GAS_ESTIMATES.approval : 0)
})

const networkFee = computed(() => {
  const gasPriceWei = hexToBigInt(gasPriceWeiHex.value)
  if (gasPriceWei === 0n || !estimatedGasUnits.value) return { eth: '0.0000', usd: '0.00' }
  const feeWei = gasPriceWei * BigInt(estimatedGasUnits.value)
  // Convert wei to ETH: divide by 1e18 using number math safely for display
  const feeEth = Number(feeWei) / 1e18
  const feeEthSafe = isFinite(feeEth) ? feeEth : 0
  const ethUsd = livePrices.value.ETH || 0
  const feeUsd = feeEthSafe * ethUsd
  return {
    eth: feeEthSafe.toFixed(5),
    usd: feeUsd.toFixed(2)
  }
})

// Address validation functions
const validateEthereumAddress = (address) => {
  // Basic Ethereum address validation (0x + 40 hex characters)
  return /^0x[a-fA-F0-9]{40}$/.test(address)
}

const validateSolanaAddress = (address) => {
  // Basic Solana address validation (base58, 32-44 characters)
  return /^[1-9A-HJ-NP-Za-km-z]{32,44}$/.test(address)
}

const validateRecipientAddress = (address) => {
  if (!address) {
    recipientAddressError.value = ''
    recipientAddressType.value = ''
    return true
  }

  // Check if it's a valid Ethereum address
  if (validateEthereumAddress(address)) {
    recipientAddressError.value = ''
    recipientAddressType.value = 'Ethereum'
    return true
  }

  // Check if it's a valid Solana address
  if (validateSolanaAddress(address)) {
    recipientAddressError.value = ''
    recipientAddressType.value = 'Solana'
    return true
  }

  // Invalid address
  recipientAddressError.value = 'Invalid wallet address format'
  recipientAddressType.value = ''
  return false
}

// Token utility functions
const getTokenLogo = (token) => {
  const logoMap = {
    'ETH': 'https://assets.coingecko.com/coins/images/279/small/ethereum.png',
    'USDC': 'https://assets.coingecko.com/coins/images/6319/small/USD_Coin_icon.png',
    'USDT': 'https://assets.coingecko.com/coins/images/325/small/Tether.png',
    'SOL': 'https://assets.coingecko.com/coins/images/4128/small/solana.png',
    'USDC_SOL': 'https://assets.coingecko.com/coins/images/6319/small/USD_Coin_icon.png',
    'CIRX': '/cirx-icon.svg'
  }
  
  return logoMap[token] || 'https://assets.coingecko.com/coins/images/279/small/ethereum.png'
}

const getTokenSymbol = (token) => {
  const symbolMap = {
    'ETH': 'ETH',
    'USDC': 'USDC',
    'USDT': 'USDT',
    'SOL': 'SOL',
    'USDC_SOL': 'USDC',
    'CIRX': 'CIRX'
  }
  
  return symbolMap[token] || token
}

// Methods
const useConnectedWallet = () => {
  recipientAddress.value = ''
}

// Format number input to prevent invalid formats like "05", "00.5", etc.
const formatNumberInput = (value) => {
  if (!value || value === '') return ''
  
  // Remove any non-numeric characters except decimal point
  let cleaned = value.replace(/[^0-9.]/g, '')
  
  // Handle multiple decimal points - keep only the first one
  const decimalCount = (cleaned.match(/\./g) || []).length
  if (decimalCount > 1) {
    const firstDecimalIndex = cleaned.indexOf('.')
    cleaned = cleaned.slice(0, firstDecimalIndex + 1) + cleaned.slice(firstDecimalIndex + 1).replace(/\./g, '')
  }
  
  // Handle leading zeros
  if (cleaned.length > 1 && cleaned[0] === '0' && cleaned[1] !== '.') {
    // Remove leading zeros unless it's "0." 
    cleaned = cleaned.replace(/^0+/, '')
    if (cleaned === '' || cleaned[0] === '.') {
      cleaned = '0' + cleaned
    }
  }
  
  // Ensure we don't start with a decimal point
  if (cleaned.startsWith('.')) {
    cleaned = '0' + cleaned
  }
  
  // Limit decimal places to 8 (reasonable for token amounts)
  const parts = cleaned.split('.')
  if (parts.length === 2 && parts[1].length > 8) {
    cleaned = parts[0] + '.' + parts[1].slice(0, 8)
  }
  
  return cleaned
}

// Handle input amount changes with formatting
const handleInputAmountChange = (value) => {
  const formatted = formatNumberInput(value)
  inputAmount.value = formatted
  lastEditedField.value = 'input'
}

// Handle CIRX amount changes with formatting  
const handleCirxAmountChange = (value) => {
  const formatted = formatNumberInput(value)
  cirxAmount.value = formatted
  lastEditedField.value = 'output'
}

const setMaxAmount = () => {
  if (isConnected.value) {
    // Set to 95% of balance to account for gas fees
    const balance = parseFloat(getTokenBalance(inputToken.value))
    const maxAmount = inputToken.value === 'ETH' ? balance * 0.95 : balance * 0.99
    inputAmount.value = maxAmount.toFixed(6)
  } else {
    inputAmount.value = '1.0' // Fallback for demo
  }

  // Set edit state to input when using max amount
  lastEditedField.value = 'input'
}

const selectToken = (token) => {
  inputToken.value = token
  try { useWalletStore().setSelectedToken(token) } catch {}
  showTokenDropdown.value = false
  // Reset input when token changes
  inputAmount.value = ''
  lastEditedField.value = 'input'
}


// Input validation for keypress events  
const validateNumberInput = (event) => {
  const char = event.key
  const currentValue = event.target.value
  const cursorPosition = event.target.selectionStart
  
  // Allow control keys (backspace, delete, tab, escape, enter, etc.)
  if (event.ctrlKey || event.metaKey || 
      ['Backspace', 'Delete', 'Tab', 'Escape', 'Enter', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(char)) {
    return true
  }
  
  // Allow only numbers and decimal point
  if (!/[0-9.]/.test(char)) {
    event.preventDefault()
    return false
  }
  
  // Prevent multiple decimal points
  if (char === '.' && currentValue.includes('.')) {
    event.preventDefault()
    return false
  }
  
  // Prevent leading zeros followed by digits (but allow "0.")
  if (char !== '.' && currentValue === '0' && cursorPosition === 1) {
    event.preventDefault()
    return false
  }
  
  return true
}

const reverseSwap = () => {
  console.log('Reverse swap not supported yet')
}

// Format amount for display (e.g., "$1K", "$50K", "$1M")
const formatAmount = (amount) => {
  if (amount >= 1000000) return `${(amount / 1000000).toFixed(1)}M`.replace('.0M', 'M')
  if (amount >= 1000) return `${(amount / 1000).toFixed(0)}K`
  return amount.toString()
}

const handleSwap = async () => {
  if (!canPurchase.value) return
  
  // Check wallet connection or recipient fallback
  if (!isConnected.value && !recipientAddress.value) {
    try { useWalletStore().openWalletModal() } catch {}
    return
  }
  
  try {
    loading.value = true
    loadingText.value = activeTab.value === 'liquid' ? 'Executing liquid purchase...' : 'Creating OTC vesting position...'
    
    const isOTC = activeTab.value === 'otc'
    const minCirxOut = parseFloat(cirxAmount.value) * 0.99 // 1% slippage tolerance
    
    // Execute the swap via connected wallet
    const result = await executeSwap(
      inputToken.value,
      inputAmount.value,
      'CIRX',
      isOTC
    )
    
    if (result.success) {
      // Show success message
      const message = isOTC 
        ? `OTC purchase successful! Your ${cirxAmount.value} CIRX will vest over 6 months. Transaction: ${result.hash.slice(0, 10)}...`
        : `Liquid purchase successful! You received ${cirxAmount.value} CIRX immediately. Transaction: ${result.hash.slice(0, 10)}...`
      
      alert(message)
      
      // Reset form
      inputAmount.value = ''
      cirxAmount.value = ''
      quote.value = null
    }
  } catch (error) {
    console.error('Swap failed:', error)
    alert(`Transaction failed: ${error.message}`)
  } finally {
    loading.value = false
    loadingText.value = ''
  }
}

// Handle OTC tier selection
const handleTierChange = (tier) => {
  selectedTier.value = tier

  if (activeTab.value !== 'otc') return

  // When user picks a tier, set the input amount to the minimum USD required for that tier
  // Convert tier.minAmount USD into selected input token units
  const tokenPriceUsd = livePrices.value[inputToken.value] || 0
  if (tokenPriceUsd > 0 && tier?.minAmount) {
    const feeRate = fees.value.otc
    const feeMultiplier = 1 - feeRate / 100
    if (feeMultiplier > 0) {
      // We want amountAfterFee * tokenPriceUsd >= tier.minAmount
      // amountAfterFee = inputAmount * feeMultiplier => inputAmount = minUsd / (tokenPriceUsd * feeMultiplier)
      const requiredInput = tier.minAmount / (tokenPriceUsd * feeMultiplier)
      inputAmount.value = requiredInput.toFixed(6)
      lastEditedField.value = 'input'
    }
  }

  // Recalculate quote with new tier
  if (inputAmount.value && parseFloat(inputAmount.value) > 0) {
    const newQuote = calculateQuote(inputAmount.value, inputToken.value, true)
    if (newQuote) {
      quote.value = newQuote
      cirxAmount.value = newQuote.cirxAmount
    }
  }
}

// Debounced quote calculation for better UX
let quoteTimeout = null

// Watch for amount/token/tab changes (forward path)
watch([inputAmount, inputToken, activeTab], async () => {
  if (lastEditedField.value !== 'input') return
  if (quoteTimeout) clearTimeout(quoteTimeout)

  if (!inputAmount.value || parseFloat(inputAmount.value) <= 0) {
    cirxAmount.value = ''
    quote.value = null
    quoteLoading.value = false
    return
  }

  quoteTimeout = setTimeout(async () => {
    const isOTC = activeTab.value === 'otc'
    try {
      // Auto-select tier when in OTC based on current USD
      if (isOTC) {
        const tokenPriceUsd = livePrices.value[inputToken.value] || 0
        const inputVal = parseFloat(inputAmount.value) || 0
        // Use gross USD amount (before fees) for tier selection to prevent tier dropping
        const grossUsdAmount = tokenPriceUsd * inputVal
        const autoTier = getTierForUsd(grossUsdAmount)
        selectedTier.value = autoTier
      } else {
        selectedTier.value = null
      }

      const newQuote = await calculateQuoteAsync(inputAmount.value, inputToken.value, isOTC)
      if (newQuote) {
        quote.value = newQuote
        const cirxRaw = parseFloat(newQuote.cirxAmount.replace(/,/g, ''))
        cirxAmount.value = isFinite(cirxRaw) ? cirxRaw.toString() : newQuote.cirxAmount
      }
    } catch (error) {
      console.error('Quote calculation failed:', error)
      quoteLoading.value = false
    }
  }, 200)
}, { immediate: true })

// Watch for CIRX edits (reverse path)
watch([cirxAmount, inputToken, activeTab], async () => {
  if (lastEditedField.value !== 'output') return
  if (quoteTimeout) clearTimeout(quoteTimeout)

  if (!cirxAmount.value || parseFloat(cirxAmount.value) <= 0) {
    inputAmount.value = ''
    quote.value = null
    reverseQuoteLoading.value = false
    return
  }

  quoteTimeout = setTimeout(async () => {
    const isOTC = activeTab.value === 'otc'
    try {
      const result = await calculateReverseQuoteAsync(cirxAmount.value, inputToken.value, isOTC)
      if (result) {
        inputAmount.value = parseFloat(result.inputAmount.toFixed(6)).toString()
        if (result.forwardQuote) {
          quote.value = result.forwardQuote
        }
      }
    } catch (error) {
      console.error('Reverse quote calculation failed:', error)
      reverseQuoteLoading.value = false
    }
  }, 200)
})

// Watch recipient address for validation
watch(recipientAddress, (newAddress) => {
  validateRecipientAddress(newAddress)
})

// Close dropdown and slider when clicking outside
onMounted(async () => {
  // Fetch OTC configuration on component mount
  await fetchOtcConfig()
  
  const handleClickOutside = (event) => {
    if (showTokenDropdown.value && !event.target.closest('.token-dropdown-container')) {
      showTokenDropdown.value = false
    }
  }
  
  document.addEventListener('click', handleClickOutside)
  
  onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
  })
})

// Initialize timers
onMounted(async () => {
  await Promise.all([refreshPrices(), fetchGasPrice()])
  startPriceCountdown()

  // Existing outside click handler setup remains below
  // ... existing code ...
})

onUnmounted(() => {
  if (countdownTimer) clearInterval(countdownTimer)
})

// Head configuration
useHead({
  title: 'Circular Swap - Buy CIRX Tokens',
  meta: [
    { 
      name: 'description', 
      content: 'Circular Swap - Buy CIRX tokens with liquid delivery or OTC discounts up to 12%. Modern swap interface with staking coming soon.' 
    }
  ]
})
</script>

<style scoped>
@keyframes gradient-rotate {
  0% {
    background-position: 0% 50%;
  }
  50% {
    background-position: 100% 50%;
  }
  100% {
    background-position: 0% 50%;
  }
}

.animate-gradient-rotate {
  animation: gradient-rotate 12s ease infinite;
}

/* Hide number input spinner arrows */
input[type="number"]::-webkit-outer-spin-button,
input[type="number"]::-webkit-inner-spin-button {
  -webkit-appearance: none !important;
  margin: 0 !important;
  display: none !important;
}

/* Firefox */
input[type="number"] {
  -moz-appearance: textfield !important;
}
</style>