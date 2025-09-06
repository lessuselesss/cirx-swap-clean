<template>
  <div class="min-h-screen relative overflow-hidden bg-figma-base">
    <!-- Space Background -->
    <div key="static-background" class="absolute inset-0 bg-cover bg-center bg-no-repeat bg-fixed z-0" style="background-image: url('/buy/background.png')"></div>
    <!-- Gradient overlay -->
    <div key="static-gradient" class="absolute inset-0 z-10" style="background: linear-gradient(to bottom, rgba(0,0,0,0.98) 0%, rgba(0,0,0,0.70) 50%, transparent 100%);"></div>
    <header class="sticky top-0 z-50 relative">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-20">
          <!-- Logo Section -->
          <div class="flex items-center gap-4">
            <img 
              src="/images/logo/SVG/color-logo-white-svg.svg" 
              alt="Circular Protocol" 
              class="h-8 w-auto drop-shadow-lg"
            />
          </div>

          <!-- Navigation & Wallet Section -->
          <div class="flex items-center gap-4">
            <!-- Status Tracking Link -->
            <NuxtLink 
              to="/transactions" 
              class="px-3 py-2 text-sm text-gray-300 hover:text-white transition-colors rounded-lg hover:bg-gray-800/50"
            >
              Transactions
            </NuxtLink>
            
            <!-- Debug Info (TEMPORARY) -->
            <div class="px-2 py-1 bg-red-900 text-red-200 rounded text-xs mb-2">
              DEBUG: ProjectID: {{ $config.public.reownProjectId ? ($config.public.reownProjectId.slice(0, 6) + '***') : 'UNDEFINED' }}
              TestMode: {{ $config.public.testnetMode }}
            </div>
            
            <!-- AppKit Wallet Connection -->
            <ClientOnly>
              <template #fallback>
                <div class="w-32 h-10 bg-gray-800 rounded-lg animate-pulse"></div>
              </template>
              <AppKitButton v-if="$appkit && typeof $appkit === 'object' && !$appkit.disabled" />
              <div v-else-if="$appkit?.disabled" class="px-4 py-2 bg-gray-600 text-gray-300 rounded-lg text-sm">
                Wallet Configuration Error
              </div>
              <div v-else class="px-4 py-2 bg-gray-600 text-gray-300 rounded-lg text-sm">
                Wallet Unavailable
              </div>
            </ClientOnly>
          </div>
        </div>
      </div>
    </header>

    
    <div class="min-h-[calc(100vh-4rem)] flex items-center justify-center p-4 md:p-8 relative z-10">
      <div :class="[
        'w-full mx-auto transition-all duration-500',
        (showChart || showStaking) ? 'max-w-none px-4' : 'max-w-lg'
      ]">
        <div :class="[
          'flex gap-6 items-start',
          (showChart || showStaking) ? 'flex-col lg:flex-row' : 'justify-center'
        ]">
          
          <!-- Chart or Staking Panel (mutually exclusive) -->
          <div v-if="showChart || showStaking" class="w-full lg:w-3/5 xl:w-2/3 h-[80vh]">
            <CirxPriceChart v-if="showChart" @close="showChart = false" />
            <CirxStakingPanel v-else-if="showStaking" @close="showStaking = false" />
          </div>
          
          
          <div :class="[
            'transition-all duration-500',
            (showChart || showStaking) ? 'w-full lg:w-2/5 xl:w-1/3 lg:min-w-[400px]' : 'w-full max-w-lg'
          ]">
        
        <div class="relative">
          
          <div class="relative p-6 sm:p-8 rounded-2xl border border-cyan-500/30 shadow-2xl shadow-cyan-500/10 transition-all duration-300 gradient-border min-h-[600px]" style="background-color: rgba(0, 3, 6, 0.9);">
          
          <div class="flex mb-6 rounded-xl p-1 gap-1 overflow-hidden" style="background-color: #11161f;">
            <button
              @click="activeTab = 'liquid'"
              :class="[
                'flex-1 px-3 sm:px-4 py-3 text-sm font-medium font-michroma transition-all duration-300 rounded-lg flex items-center justify-center gap-1 sm:gap-2 min-w-0 basis-0',
                activeTab === 'liquid' 
                  ? 'text-circular-primary bg-circular-primary/20' 
                  : 'text-gray-400 hover:text-white hover:bg-gray-700/50'
              ]"
            >
              <div class="flex items-center gap-1 sm:gap-2">
                <!-- Liquid Swap Icon -->
                <img src="/buy_liquid.svg" alt="Liquid" class="w-3 h-2.5 flex-shrink-0" />
                <span class="text-xs sm:text-sm text-center leading-tight">
                  <span class="block md:inline">Buy</span>
                  <span class="block md:inline"> Liquid</span>
                </span>
              </div>
            </button>
            <button
              @click="activeTab = 'otc'"
              :class="[
                'flex-1 px-3 sm:px-4 py-3 text-sm font-medium font-michroma transition-all duration-300 rounded-lg flex items-center justify-center gap-1 sm:gap-2 min-w-0 basis-0',
                activeTab === 'otc' 
                  ? 'text-circular-purple bg-circular-purple/20' 
                  : 'text-gray-400 hover:text-white hover:bg-gray-700/50'
              ]"
            >
              <div class="flex items-center gap-1 sm:gap-2">
                <!-- OTC Contract Icon -->
                <img src="/buy_otc_purple.svg" alt="OTC" class="w-3 h-3.5 flex-shrink-0" />
                <span class="text-xs sm:text-sm text-center leading-tight">
                  <span class="block md:inline">Vested</span>
                  <span class="block md:inline"> OTC</span>
                </span>
              </div>
            </button>
          </div>

          
          <form @submit.prevent novalidate>
            
            <!-- CIRX OTC Connected Swap Fields -->
            <div class="mb-6 relative">
              <div class="swap-container" :data-tab="activeTab">
                <!-- Sell Field (Top) -->
                <div class="input-section input-section-top">
                  <div class="input-header">
                    <label class="text-sm font-medium text-white">Sell</label>
                    <span v-if="inputToken && inputBalance !== null" class="balance-display pr-3" @click="setMaxAmount" @dblclick="forceRefreshBalance">
                      Balance: {{ inputBalance }} {{ inputToken }}
                    </span>
                    <span v-else-if="inputToken" class="balance-display pr-3">
                      Balance: -
                    </span>
                    <span v-else class="balance-display pr-3">
                      Balance: -
                    </span>
                  </div>
                  
                  <div class="input-content">
                    <input
                      ref="amountInputRef"
                      :value="inputAmount"
                      @input="handleInputAmountChange($event.target.value)"
                      type="text"
                      inputmode="decimal"
                      pattern="[0-9]*\.?[0-9]*"
                      placeholder="0.0"
                      class="amount-input"
                      :disabled="loading"
                      @keypress="validateNumberInput"
                    />
                    
                    <div class="token-dropdown-container relative z-[100]" ref="tokenSelectorContainer">
                      <!-- Token Selector Button -->
                      <button
                        type="button"
                        @click="showTokenDropdown = !showTokenDropdown"
                        class="token-display-right flex items-center gap-2 rounded-full bg-gray-700/50 hover:bg-gray-700/70 transition-colors"
                        :disabled="loading"
                      >
                        <img 
                          v-if="inputToken"
                          :src="getTokenLogo(inputToken)" 
                          :alt="inputToken"
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
                        <span v-if="inputToken" class="font-semibold text-white" style="font-size: 0.8rem; letter-spacing: -0.01em;">
                          {{ getTokenSymbol(inputToken) }}
                        </span>
                        <span v-else class="font-semibold" style="color: #00e3a3; font-size: 0.8rem; letter-spacing: -0.01em;">
                          Select
                        </span>
                        <svg 
                          :class="['text-gray-400 transition-transform', showTokenDropdown && 'rotate-180']" 
                          style="width: 12px; height: 12px;"
                          fill="none" 
                          stroke="currentColor" 
                          viewBox="0 0 24 24"
                        >
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                      </button>
                      
                      <!-- Token Dropdown positioned relative to this container -->
                      <div 
                        v-if="showTokenDropdown"
                        class="token-dropdown-simple"
                      >
                        <template v-if="connectedWallet === 'phantom'">
                          <button
                            v-for="token in [{ value: 'SOL', label: 'SOL' }, { value: 'USDC_SOL', label: 'USDC' }]"
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
                            v-for="token in [{ value: 'ETH', label: 'ETH' }, { value: 'USDC', label: 'USDC' }, { value: 'USDT', label: 'USDT' }]"
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
                  </div>
                  <div class="usd-value">
                    <span v-if="inputAmount && parseFloat(inputAmount) > 0 && inputToken">
                      ~${{ ((parseFloat(inputAmount) || 0) * (livePrices[inputToken] || 0)).toFixed(2) }}
                    </span>
                    <span v-else-if="inputToken">~$0.00</span>
                    <span v-else>-</span>
                  </div>
                </div>

                <!-- Swap Arrow -->
                <div class="swap-arrow-container">
                  <button
                    type="button"
                    :class="[
                      'swap-arrow-button',
                      activeTab === 'liquid' ? 'swap-arrow-liquid' : 'swap-arrow-otc'
                    ]"
                    @click="reverseSwap"
                    :disabled="loading"
                  >
                    <svg v-if="isPriceRefreshing" class="animate-spin w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    <div v-else class="text-xl font-bold" style="letter-spacing: -0.1em;">
                      ⥯
                    </div>
                  </button>
                </div>

                <!-- Buy Field (Bottom) -->
                <div class="input-section input-section-bottom">
                  <div class="input-header">
                    <label class="text-sm font-medium text-white">Buy</label>
                    <span v-if="isFetchingRecipientBalance" class="balance-display pr-3">
                      Balance: Loading...
                    </span>
                    <span v-else-if="displayCirxBalance" class="balance-display pr-3">
                      Balance: {{ formatBalance(displayCirxBalance) }} CIRX
                    </span>
                    <span v-else class="balance-display pr-3">
                      Balance: -
                    </span>
                  </div>
                  <div class="input-content">
                    <input
                      :value="cirxAmount"
                      @input="handleCirxAmountChange($event.target.value)"
                      @keypress="validateNumberInput"
                      type="text"
                      inputmode="decimal"
                      pattern="[0-9]*\.?[0-9]*"
                      placeholder="0.0"
                      :disabled="quoteLoading || reverseQuoteLoading"
                      style="-webkit-appearance: none; -moz-appearance: textfield; appearance: none;"
                      :class="['amount-input', (quoteLoading || reverseQuoteLoading) && 'opacity-50']"
                    />
                    <!-- OTC Mode: Discount Tier Dropdown -->
                    <OtcDiscountDropdown
                      v-if="activeTab === 'otc' && discountTiers && discountTiers.length > 0"
                      :discount-tiers="discountTiers"
                      :selected-tier="selectedTier"
                      :current-amount="quote?.usdValue || 0"
                      @tier-changed="handleTierChange"
                    />
                    
                    <!-- Liquid Mode: Standard CIRX Display -->
                    <div 
                      v-else
                      class="token-display token-display-right flex items-center gap-2 rounded-full bg-gray-700/50 hover:bg-gray-700/70 transition-colors"
                      style="width: 110px; min-width: 110px; max-width: 110px; padding: 8px 12px;"
                      ref="cirxButton"
                    >
                      <img 
                        :src="getTokenLogo('CIRX')" 
                        alt="CIRX"
                        class="rounded-full"
                        style="width: 16px; height: 16px;"
                        @error="$event.target.src = 'https://cdn.prod.website-files.com/65e472c0cd2f1bebcd7fcf73/65e483ab69e2314b250ed7dc_imageedit_1_8961069084.png'"
                      />
                      <span class="font-semibold text-white" style="font-size: 0.8rem; letter-spacing: -0.01em;">CIRX</span>
                    </div>
                  </div>
                  <div class="usd-value">
                    <span v-if="cirxAmount && parseFloat(cirxAmount) > 0">
                      {{ getNewCirxBalance() }}
                    </span>
                    <span v-else>{{ getNewCirxBalance() }}</span>
                  </div>
                </div>
              </div>
              

              <!-- OTC Discount Tiers (show full range, highlight active) -->
              <div :class="['mt-3 space-y-2 transition-all duration-300', activeTab === 'otc' ? 'opacity-100 max-h-96' : 'opacity-0 max-h-0 overflow-hidden']">
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
                      <span class="text-gray-500 ml-1">{{ tier.vestingMonths || otcConfig.value?.vestingPeriod?.months || 6 }}mo</span>
                    </div>
                  </div>
                </div>
                <div v-if="currentUsd > 0 && (!selectedTier || currentUsd < lowestTierMin)" class="bg-gray-800/30 border border-gray-600/30 rounded-lg p-3 text-center">
                  <p class="text-xs text-gray-400">Below the minimum for the lowest tier. Minimum: ${{ formatAmount(lowestTierMin) }}</p>
                </div>
              </div>
            </div>

            
            <div v-if="quote" class="bg-transparent border border-cyan-500/20 rounded-xl p-4 mb-6 hover:border-cyan-500/40 transition-all duration-300" :class="isPriceRefreshing ? 'border-cyan-400/40' : ''">
              <div class="flex justify-between items-center mb-2">
                <span class="text-sm text-gray-400">Exchange Rate</span>
                <span class="text-sm font-medium text-white" :class="isPriceRefreshing || quoteLoading || reverseQuoteLoading ? 'opacity-60' : ''">1 {{ inputToken }} = {{ quote.rate }} CIRX</span>
              </div>
              <div class="flex justify-between items-center mb-2">
                <span class="text-xs text-gray-500" :class="isPriceRefreshing ? 'text-cyan-400' : ''">
                  {{ isPriceRefreshing ? 'Updating prices...' : `Next price update in ${priceCountdown}s` }}
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
                <span class="text-sm font-medium text-white">{{ otcConfig.value?.vestingPeriod?.months || 6 }} months ({{ otcConfig.value?.vestingPeriod?.type || 'linear' }})</span>
              </div>
            </div>

            
            <div class="mb-6">
              <div class="flex justify-between items-center mb-3">
                <div class="flex items-center gap-3">
                  <label class="text-sm font-medium text-white">Circular Chain Address</label>
                  <!-- Status Light -->
                  <div class="flex items-center gap-2">
                    <div 
                      :class="[
                        'w-3 h-3 rounded-full transition-all duration-200 cursor-help',
                        {
                          'bg-red-500': recipientAddressError,
                          'bg-green-500': (() => {
                            const greenCondition = recipientAddress && recipientAddressType === 'circular' && !recipientAddressError && addressValidationState === 'valid';
                            console.log('🟢 Green light condition check:', {
                              recipientAddress: recipientAddress,
                              recipientAddressType: recipientAddressType,
                              recipientAddressError: recipientAddressError,
                              addressValidationState: addressValidationState,
                              greenCondition: greenCondition
                            });
                            return greenCondition;
                          })(),
                          'bg-yellow-500 animate-flash': addressValidationState === 'validating',
                          'bg-yellow-500': recipientAddress && recipientAddress.length === 66 && recipientAddress.startsWith('0x') && addressValidationState === 'idle' && !recipientAddressError,
                          'bg-yellow-500': recipientAddress && (recipientAddress === '0' || (recipientAddress.startsWith('0x') && recipientAddress.length < 66)) && !recipientAddressError,
                          'bg-gray-500': !recipientAddress
                        }
                      ]"
                      :title="recipientAddressError || 'Validation status'"
                    ></div>
                  </div>
                </div>
              </div>
              <div class="relative">
                <input
                  ref="addressInputRef"
                  v-model="recipientAddress"
                  type="text"
                  :readonly="false"
                  :placeholder="dynamicPlaceholder"
                  @input="sanitizeAddressInput"
                  @keydown="handleAddressKeydown"
                  @blur="handleAddressBlur"
                  :class="[
                    'w-full pl-4 pr-12 py-3 text-sm bg-transparent border rounded-xl text-white placeholder-gray-400 transition-all duration-300',
                    activeTab === 'liquid' 
                      ? 'border-gray-700/70 hover:border-circular-primary focus:border-circular-primary focus:ring-2 focus:ring-circular-primary/30 focus:outline-none' 
                      : 'border-gray-700/70 hover:border-circular-purple focus:border-circular-purple focus:ring-2 focus:ring-circular-purple/30 focus:outline-none'
                  ]"
                  :disabled="loading"
                />
                <div class="absolute inset-y-0 right-0 flex items-center pr-4">
                  <button
                    v-if="recipientAddress"
                    @click="recipientAddress = ''; hasClickedEnterAddress = false"
                    class="text-gray-400 hover:text-white transition-colors"
                    title="Clear address"
                  >
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                      <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                    </svg>
                  </button>
                </div>
              </div>
              <div v-if="isConnected?.value && hasClickedEnterAddress && !recipientAddress" class="mt-2 flex items-center gap-2 text-sm text-yellow-400">
                <img 
                  v-if="isSaturnWalletDetected" 
                  src="https://avatars.githubusercontent.com/u/saturn-wallet?s=20" 
                  alt="Saturn Wallet" 
                  class="w-5 h-5 rounded"
                  @error="$event.target.style.display = 'none'"
                />
                <span v-if="!isSaturnWalletDetected">⚠️ Please specify a recipient address above to receive CIRX safely</span>
              </div>
              
              <!-- Error message for invalid addresses -->
              <div v-if="recipientAddressError" class="mt-2 text-sm text-red-400">
                {{ recipientAddressError }}
              </div>
              
              <!-- Success message for valid Circular addresses -->
              <div v-else-if="recipientAddress && recipientAddressType === 'circular'" class="mt-2 text-sm text-green-400">
                ✓ Valid Circular Chain address
              </div>
              
              <!-- Help text when no address is entered -->
              <div v-else-if="!recipientAddress" class="mt-2 text-xs text-gray-500">
              </div>
            </div>

            <!-- Unified CTA Button -->
            <CallToAction
              :wallet-connected="isConnected?.value"
              :recipient-address="recipientAddress"
              :recipient-address-error="recipientAddressError"
              :address-validation-state="addressValidationState"
              :input-amount="inputAmount"
              :input-balance="inputBalance"
              :eth-balance="awaitedEthBalance"
              :network-fee-eth="networkFee.eth"
              :input-token="inputToken"
              :active-tab="activeTab"
              :loading="loading || quoteLoading || reverseQuoteLoading || isButtonShowingDots"
              :loading-text="quoteLoading || reverseQuoteLoading ? (reverseQuoteLoading ? 'Calculating...' : 'Getting Quote...') : (loadingText || 'Processing...')"
              :can-purchase="canPurchase"
              :quote="quote"
              :debug="true"
              @connect-wallet="handleConnectWallet"
              @focus-address="handleFocusAddress"
              @clear-and-focus-address="handleClearAndFocusAddress"
              @focus-amount="handleFocusAmount"
              @get-cirx-wallet="handleGetCirxWallet"
              @click="handleSwapFromCTA"
            />
          </form>
        </div>
          
          <!-- Floating Action Pills -->
          <div class="mt-4 flex justify-start gap-3">
            <button
              v-if="!showChart"
              @click="showChart = true; showStaking = false"
              class="inline-flex items-center gap-2 px-3 py-2 text-white border border-gray-600/30 hover:border-gray-400/60 transition-all text-sm font-medium rounded-full shadow-lg hover:scale-105 transform gradient-border"
              style="background-color: #1B2E33;"
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
              v-if="!showStaking"
              @click="showStaking = true; showChart = false"
              class="inline-flex items-center gap-2 px-3 py-2 text-white border border-gray-600/30 hover:border-gray-400/60 transition-all text-sm font-medium rounded-full shadow-lg hover:scale-105 transform gradient-border"
              style="background-color: #1B2E33;"
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
        
        <!-- White Circular Logo centered at bottom -->
        <div class="mt-8 flex justify-center">
          <img 
            src="/images/logo/PNG/abstract-icon-white-png.png" 
            alt="Circular Protocol" 
            class="h-16 w-16 opacity-60 hover:opacity-80 transition-opacity cursor-pointer"
            @click="$router.push('/')"
          />
        </div>
      </div>
    </div>

    <!-- Connection Toast Notifications -->

    <!-- Wallet Download Modal -->
    <div 
      v-if="showWalletModal" 
      class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
      @click.self="showWalletModal = false"
    >
      <div class="bg-gray-900 border border-gray-700 rounded-2xl max-w-md w-full p-6 relative">
        <!-- Close Button -->
        <button 
          @click="showWalletModal = false"
          class="absolute top-4 right-4 text-gray-400 hover:text-white transition-colors"
        >
          <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>

        <!-- Modal Header -->
        <div class="mb-6">
          <h3 class="text-xl font-bold text-white mb-2">Get Circular Wallet</h3>
          <p class="text-gray-400 text-sm">Choose your preferred wallet to interact with the Circular ecosystem</p>
        </div>

        <!-- Wallet Options -->
        <div class="space-y-3">
          <!-- Nero Web Wallet -->
          <a
            href="https://www.circularlabs.io/nero"
            target="_blank"
            rel="noopener noreferrer"
            class="w-full flex items-center p-4 bg-gray-800 hover:bg-gray-700 border border-gray-600 rounded-xl transition-all duration-200 group"
          >
            <div class="w-12 h-12 bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg flex items-center justify-center mr-4">
              <svg width="24" height="24" fill="white" viewBox="0 0 24 24">
                <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
              </svg>
            </div>
            <div class="flex-1">
              <h4 class="text-white font-semibold mb-1">Nero Web Wallet</h4>
              <p class="text-gray-400 text-sm">Browser-based wallet for easy access</p>
            </div>
            <div class="text-gray-400 group-hover:text-white transition-colors">
              <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
              </svg>
            </div>
          </a>

          <!-- Saturn Extension Wallet -->
          <a
            href="https://www.saturnwallet.app/"
            target="_blank"
            rel="noopener noreferrer"
            class="w-full flex items-center p-4 bg-gray-800 hover:bg-gray-700 border border-gray-600 rounded-xl transition-all duration-200 group"
          >
            <div class="w-12 h-12 bg-gradient-to-r from-yellow-500 to-orange-600 rounded-lg flex items-center justify-center mr-4">
              <svg width="24" height="24" fill="white" viewBox="0 0 24 24">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
              </svg>
            </div>
            <div class="flex-1">
              <h4 class="text-white font-semibold mb-1">Saturn Extension</h4>
              <p class="text-gray-400 text-sm">Browser extension for enhanced security</p>
            </div>
            <div class="text-gray-400 group-hover:text-white transition-colors">
              <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
              </svg>
            </div>
          </a>
        </div>

        <!-- Footer -->
        <div class="mt-6 pt-4 border-t border-gray-700">
          <p class="text-xs text-gray-500 text-center">
            These wallets will allow you to interact with the Circular Protocol ecosystem
          </p>
        </div>
      </div>
    </div>

    <!-- State 6 Confirmation Modal -->
    <!-- Get Circular Wallet Modal -->
    <GetCircularWalletModal v-model="showConfirmationModal" />
  </div>
</template>

<script setup>
// Import Vue composables
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
// AppKit handled by web components - no direct imports needed
import { parseEther, parseUnits } from 'viem'
// Import components
// AppKit wallet integration now handles all wallet functionality
import OtcDiscountDropdown from '~/components/OtcDiscountDropdown.vue'
import CirxPriceChart from '~/components/CirxPriceChart.vue'
import CirxStakingPanel from '~/components/CirxStakingPanel.vue'
import CallToAction from '~/components/CallToAction.vue'
import GetCircularWalletModal from '~/components/GetCircularWalletModal.vue'
import { useCTAState } from '~/composables/core/useCallToActionState.js'
// Import unified price data composable
import { usePriceData } from '~/composables/usePriceData'
// AggregateMarket consolidated into unified price service
// Address validation functions can be added here if needed
// Import unified API client and CIRX utilities
import { useApiClient } from '~/composables/core/useApiClient.js'
import { useCirxUtils } from '~/composables/useCirxUtils.js'
// Import real-time transaction updates via IROH
import { useRealTimeTransactions } from '~/composables/useIrohNetwork'
// Import Circular address validation
import { useCircularAddressValidation } from '~/composables/utils/validators'
// Import safe toast utility
import { safeToast } from '~/composables/useToast'
// Extension detection disabled
// import { detectAllExtensions } from '~/utils/comprehensiveExtensionDetection.js'
// AppKit handles all wallet detection and connection

// Page metadata
definePageMeta({
  title: 'Circular Swap',
  layout: false  // Don't use default layout - this page has its own header
})

// Use AppKit hooks directly for state
import { useFormattedNumbers } from '~/composables/useFormattedNumbers.js'
// Import enhanced wallet composable with centralized balance management
import { useAppKitWallet } from '~/composables/useAppKitWallet.js'
// Import swap logic composable to replace duplicate implementations
import { useSwapLogic } from '~/composables/useSwapHandler.js'
// Import formatting utilities
import { useSwapFormatting } from '~/composables/features/useSwapFormatting.js'
// Import validation logic composable  
import { useSwapValidation } from '~/composables/features/useSwapValidation.js'
// Import unified swap transaction handler
import { useSwapTransaction } from '~/composables/useSwapTransaction.js'

// Initialize formatted numbers composable
const { isValidEthereumAddress, isValidSolanaAddress, getAddressType } = useFormattedNumbers()

// Use enhanced wallet composable with centralized balance management
const { 
  address, 
  isConnected, 
  chainId, 
  disconnect, 
  open,
 
  lastConnectedWalletIcon, 
  walletIcon,
  // Centralized balance management
  tokenBalances,
  formattedBalances,
  balanceLoading,
  refreshBalances
} = useAppKitWallet()

// Forward declare functions that will be defined later
let handleClickOutside, handleGlobalEnter

// Register all lifecycle hooks before any async operations
onUnmounted(() => {
  // Clean up event listeners
  if (typeof document !== 'undefined' && handleClickOutside && handleGlobalEnter) {
    document.removeEventListener('click', handleClickOutside)
    document.removeEventListener('keydown', handleGlobalEnter)
  }
})

// Balance is now handled by walletStore
// Remove custom ethBalanceData implementation

// Wallet state now handled directly by AppKit hooks

// Token contract addresses for balance fetching
const tokenAddresses = {
  'USDC': '0xA0b86a33E6Ba476C4db6B0EbB18B9E7D8e4a8563', // USDC on mainnet
  'USDT': '0xdAC17F958D2ee523a2206206994597C13D831ec7', // USDT on mainnet
}

// Balance fetching will be handled using provider when needed

// AppKit modal opening is handled by the open function from useAppKit() above

// Backend API integration
// API client and CIRX utilities
const apiClient = useApiClient()
const {
  calculateCirxQuote,
  getDepositAddress,
  createSwapTransaction,
  DEPOSIT_ADDRESSES,
  TOKEN_PRICES: backendTokenPrices
} = useCirxUtils()

// Unified swap transaction handler (replaces duplicate logic)
const swapTx = useSwapTransaction()

// Swap logic composable - replaces duplicate local implementations
const swapLogic = useSwapLogic()
const {
  calculateQuote: swapCalculateQuote,
  calculateReverseQuote: swapCalculateReverseQuote,
  calculateDiscount: swapCalculateDiscount,
  refreshPrices: swapRefreshPrices,
  getTokenPrice: swapGetTokenPrice,
  formatNumber: swapFormatNumber,
  tokenPrices: swapTokenPrices,
  fees: swapFees,
  discountTiers: swapDiscountTiers,
  qualifiesForOTC: swapQualifiesForOTC,
  validateSwap: swapValidateSwap,
  calculateMaxAmount: swapCalculateMaxAmount
} = swapLogic

// Formatting utilities composable
const {
  hexToBigInt,
  formatBalance: swapFormatBalance, // Original from useSwapFormatting
  formatAmount,
  formatTokenBalance,
  getExchangeRateDisplay,
  getNewCirxBalance
} = useSwapFormatting()

// Use consolidated formatBalance from useFormattingUtils
import { useFormattingUtils } from '~/composables/core/useFormattingUtils.js'
const { formatBalance, formatWithCommas } = useFormattingUtils()

// Real-time transaction updates via IROH
const {
  subscribeToTransaction,
  monitorTransaction,
  getTransaction,
  isConnected: irohConnected,
  connectionStatus: irohStatus
} = useRealTimeTransactions()

// Circular address validation
const { checkAddressExists } = useCircularAddressValidation()

// Network configuration for dynamic placeholder
const networkConfig = ref({
  network: 'testnet',
  chain_name: 'Circular SandBox',
  environment: 'development'
})
const dynamicPlaceholder = computed(() => {
  const network = networkConfig.value?.network || 'testnet'
  const chainName = networkConfig.value?.chain_name || 'Circular SandBox'
  
  // Capitalize network names: mainnet -> Mainnet, testnet -> Testnet, devnet -> Devnet
  const capitalizedNetwork = network.charAt(0).toUpperCase() + network.slice(1).toLowerCase()
  
  return `Enter a ${capitalizedNetwork} (${chainName}) Wallet Address`
})

// Fetch network configuration from backend
const fetchNetworkConfig = async () => {
  try {
    const config = useRuntimeConfig()
    const apiBaseUrl = config.public.apiBaseUrl || 'http://localhost:18423/api/v1'
    
    const response = await fetch(`${apiBaseUrl}/config/circular-network`)
    if (response.ok) {
      const data = await response.json()
      networkConfig.value = data
      console.log('🔗 Network config loaded for placeholder:', {
        network: data.network,
        chain: data.chain_name
      })
    } else {
      console.warn('Failed to fetch network config, using fallback placeholder')
    }
  } catch (error) {
    console.warn('Network config fetch error:', error.message)
    // networkConfig.value remains null, so fallback placeholder will be used
  }
}

// Toast callback for Circular chain notifications
const handleCircularToast = ({ type, title, message }) => {
  if (typeof window !== 'undefined' && window.$toast?.connection) {
    window.$toast.connection[type]?.(message, { title }) ?? window.$toast.connection.success(message, { title })
  }
}

// Using AppKit for wallet balances - static values for development
const isCircularChainAvailable = computed(() => false)
const isCircularChainConnected = computed(() => false)

// Check if button should be disabled (either showing "..." or processing)
const isButtonShowingDots = computed(() => {
  // Disable during loading states (transaction processing)
  if (loading.value || quoteLoading.value || reverseQuoteLoading.value) return true
  
  // Don't disable if not connected or no address - these are actionable states
  if (!isConnected?.value) return false
  if (isConnected?.value && (!recipientAddress.value || recipientAddress.value.trim() === '')) return false
  
  // Disable for the specific "..." conditions (address validation states)
  return (
    addressValidationState.value === 'validating' ||
    (recipientAddress.value && (recipientAddress.value === '0' || (recipientAddress.value.startsWith('0x') && recipientAddress.value.length < 66))) ||
    (recipientAddress.value && recipientAddress.value.length === 66 && recipientAddress.value.startsWith('0x') && addressValidationState.value === 'idle')
  )
})

// Format ETH balance using wallet store balance
const formattedEthBalance = computed(() => {
  if (!isConnected?.value) {
    return '0.000000000000000000'
  }
  
  // Use centralized balance from AppKit wallet composable
  return tokenBalances.value.ETH || '0.000000000000000000'
})

// Connection state management and watchers are now handled by useAppKitWallet composable

// Reactive state
const activeTab = ref('liquid')
const inputAmount = ref('')
// Mock token balances for testing (since wallet is disabled)
const mockTokenBalances = ref({
  ETH: '5.123456',   // Mock ETH balance
  USDC: '10000.50',  // Mock USDC balance  
  USDT: '7500.25'    // Mock USDT balance
})

// Token balance for selected input token - computed based on inputToken selection
const inputBalance = computed(() => {
  // Return null when not connected (no wallet connected)
  if (!isConnected?.value) {
    return null
  }
  
  // Real wallet balance logic using centralized balance management
  switch (inputToken.value) {
    case 'ETH':
      return tokenBalances.value.ETH || '0'
    case 'USDC':
      return tokenBalances.value.USDC || '0'
    case 'USDT':
      return tokenBalances.value.USDT || '0'
    default:
      return '0'
  }
})
const showWalletModal = ref(false)
const cirxAmount = ref('')
// Input token selection - default to ETH
const inputToken = ref('ETH')
// Address is always required - no toggle needed
const loading = ref(false)

// Initialize loading state
const loadingText = ref('')
const quote = ref(null)
const showChart = ref(false)
const showStaking = ref(false)

// Chart data preloading variables (will be initialized after page load)
let chartPreloadStarted = false
const chartDataLoading = ref(false)
const chartDataError = ref(null)
// Price feed composable - accessible at component level for lifecycle management
const { 
  currentPrice: cirxPrice, 
  isLoading: priceLoading, 
  error: priceError,
  startPriceUpdates,
  stopPriceUpdates
} = usePriceData()

// Chart preloading function - called after swap page loads
const startChartPreloading = async () => {
  if (chartPreloadStarted) return
  chartPreloadStarted = true
  
  console.log('🚀 Starting background chart data preload after swap page loaded...')
  
  try {
    // Chart data preloading now handled by unified price service in usePriceData composable
    // Start background price updates which will also warm up the chart data cache
    startPriceUpdates()
    
    // Additional TradingView chart preloading (if needed) - 1 second delay to ensure swap page is fully rendered
    setTimeout(() => {
      console.log('📊 Chart data cache warmed up via unified price service')
      chartDataLoading.value = false
    }, 1000)
    
  } catch (error) {
    console.warn('Chart preloading initialization failed:', error)
    chartDataError.value = error.message
  }
}


// Focus handler for address input
const addressInputRef = ref(null)
const amountInputRef = ref(null)
const recipientAddress = ref('')
const recipientAddressError = ref('')
const recipientAddressType = ref('')
const isFetchingRecipientBalance = ref(false)
const showTokenDropdown = ref(false)
// Track whether user has clicked "Enter Address" button
const hasClickedEnterAddress = ref(false)

// Backend connectivity state
const isBackendConnected = ref(true)
const backendHealthCheckInterval = ref(null)
// Track address validation state
const addressValidationState = ref('idle') // 'idle', 'validating', 'valid', 'invalid'

// Debug watcher for validation state changes
watch(addressValidationState, (newState, oldState) => {
  console.log('🔄 addressValidationState changed:', oldState, '->', newState)
})

// Initialize swap validation composable with validation state
const swapValidation = useSwapValidation({
  recipientAddressError,
  recipientAddressType,
  addressValidationState,
  hasClickedEnterAddress
})

// Safely destructure with fallbacks to prevent undefined errors
const { 
  validateNumberInput = (e) => true,
  validateRecipientAddress = async () => {},
  validateRecipientAddressSync = () => {},
  createCanPurchaseValidator = () => () => true,
  createAddressWatcher = () => {}
} = swapValidation || {}

// Initialize CTA state composable with handlers
const ctaState = useCTAState({
  isConnected,
  recipientAddress,
  recipientAddressError,
  inputAmount,
  addressValidationState,
  activeTab,
  quote
})

const {
  handleConnectWallet: ctaHandleConnectWallet,
  handleFocusAddress: ctaHandleFocusAddress,
  handleClearAndFocusAddress: ctaHandleClearAndFocusAddress,
  handleFocusAmount: ctaHandleFocusAmount,
  handleFocusAmountInput: ctaHandleFocusAmountInput,
  handleGetCirxWallet: ctaHandleGetCirxWallet,
  handleSwap: ctaHandleSwap,
  handleValidateAddress: ctaHandleValidateAddress
} = ctaState

// Create wrapper functions that handle emit functionality with MetaMask sync
const handleConnectWallet = async () => {
  console.log('🔄 handleConnectWallet called - checking for MetaMask sync first...')
  
  // First try to sync with MetaMask if it's connected but AppKit isn't
  if (window.ethereum && window.ethereum.selectedAddress && !isConnected?.value) {
    console.log('🔗 MetaMask connected but AppKit not synced - attempting sync...')
    try {
      // Call our improved sync function from the AppKit wallet composable
      const syncSuccessful = await syncWithMetaMask()
      
      // Wait for state to update
      await new Promise(resolve => setTimeout(resolve, 1000))
      
      // Check if sync worked
      if (syncSuccessful && isConnected?.value) {
        console.log('✅ MetaMask sync successful - wallet now connected!')
        return
      } else {
        console.log('⚠️ MetaMask sync didn\'t update AppKit state - proceeding with CTA handler')
      }
    } catch (error) {
      console.warn('❌ MetaMask sync failed:', error)
    }
  }
  
  // If no MetaMask sync needed or sync failed, use the CTA handler
  ctaHandleConnectWallet()
}

const handleFocusAddress = () => ctaHandleFocusAddress()
const handleClearAndFocusAddress = () => ctaHandleClearAndFocusAddress()
const handleFocusAmount = () => ctaHandleFocusAmount()
const handleValidateAddress = (address) => ctaHandleValidateAddress(validateRecipientAddress, address)

// Handle address blur with programmatic focus prevention
const handleAddressBlur = () => {
  // Check if this blur event was caused by programmatic focus change
  if (typeof window !== 'undefined' && window._preventAddressBlurValidation) {
    console.log('🚫 Skipping address validation on blur - programmatic focus change')
    return
  }
  
  // Normal blur validation
  console.log('✅ Running address validation on blur - user-initiated focus change')
  handleValidateAddress(recipientAddress.value)
}

// Handle getting CIRX wallet modal
const handleGetCirxWallet = () => {
  const showModal = () => { showConfirmationModal.value = true }
  ctaHandleGetCirxWallet(showModal)
}

// Handle swap transaction - delegate to existing swap logic
const handleSwapFromCTA = () => {
  // Pass the real handleSwap controller function to the CTA handler
  ctaHandleSwap(handleSwap)
}

// Modal state for State 6
const showConfirmationModal = ref(false)

// Price refresh state (30s countdown)
const livePrices = ref({ ETH: 2500, USDC: 1, USDT: 1, CIRX: 1 })
const isPriceRefreshing = ref(false)
const priceCountdown = ref(30)
let countdownTimer = null

// Gas price state
const gasPriceWeiHex = ref('0x0')
const isGasRefreshing = ref(false)

// Timer progress for SVG animation
const timerProgress = computed(() => {
  const circumference = 2 * Math.PI * 20 // radius = 20
  const progress = (30 - priceCountdown.value) / 30
  const offset = circumference * (1 - progress)
  console.log('🎯 Timer progress:', priceCountdown.value, 'offset:', offset)
  return offset
})

// hexToBigInt function moved to useSwapFormatting composable

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
  console.log('🕐 Starting price countdown from:', priceCountdown.value)
  countdownTimer = setInterval(async () => {
    if (priceCountdown.value > 0) {
      priceCountdown.value -= 1
      // console.log('⏰ Countdown:', priceCountdown.value, 'Progress offset:', 125.6 * ((30 - priceCountdown.value) / 30))
    } else {
      console.log('🔄 Refreshing prices and resetting countdown')
      await Promise.all([refreshPrices(), fetchGasPrice()])
    }
  }, 1000)
}

const refreshPrices = async () => {
  try {
    isPriceRefreshing.value = true
    const { getTokenPrices } = usePriceData()
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
          cirxAmount.value = formatWithCommas(cirxRaw.toString())
        }
      }
    }
  } catch (e) {
    console.warn('Price refresh failed, keeping previous prices', e)
  } finally {
    isPriceRefreshing.value = false
    // Restart the countdown timer
    startPriceCountdown()
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

// FORCE LOADING STATES TO FALSE TO UNBLOCK BUTTON
setInterval(() => {
  quoteLoading.value = false
  reverseQuoteLoading.value = false
}, 1000)

// Helper function to format token balance
// formatTokenBalance function moved to useSwapFormatting composable

// inputBalance logic moved to wallet store - removed orphaned local balance logic

// ETH balance for gas gating (0 when not connected)
const awaitedEthBalance = computed(() => {
  if (isConnected?.value) {
    return formattedEthBalance.value || '0.000000000000000000'
  }
  return '0.000000000000000000'
})

const displayCirxBalance = computed(() => {
  // Only show balance if we have a valid recipient address and fetched balance
  if (recipientAddress.value && !recipientAddressError.value) {
    return '0'
  }
  return null // No address or no balance fetched, show "Balance: -"
})

// fullPrecisionInputBalance removed - using wallet store's selectedTokenBalance directly

// Short address for display using official Wagmi address
const shortAddress = computed(() => {
  if (!address) return ''
  return `${address.slice(0, 6)}...${address.slice(-4)}`
})

// Connected wallet type - simplified since connector is not available
const connectedWallet = computed(() => {
  return isConnected ? 'ethereum' : null
})

// Wallet icon logic is now handled by the useAppKitWallet composable

// Check if dropdown should be positioned to the left to prevent overflow
const shouldPositionLeft = computed(() => {
  // Position dropdowns to prevent overflow outside form boundaries
  // Since token selectors are on the right side of input fields,
  // we need to position dropdowns leftward to stay within bounds
  return false // Let's try right-aligned first, adjust if needed
})

// Remove unused formatSliderAmount - not referenced in template

// Token prices (live via price service, with sane defaults)
// const tokenPrices = {
//   ETH: 2500,
//   USDC: 1,
//   USDT: 1
// }

// Use composable fee structure with local fallback
const fees = computed(() => swapFees.value || otcConfig.value.fees)

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

// Use composable discount tiers with local fallback
const discountTiers = computed(() => swapDiscountTiers.value || otcConfig.value.discountTiers)

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
// Use validation composable for canPurchase logic
const canPurchase = createCanPurchaseValidator({
  inputAmount,
  recipientAddress,
  loading,
  quoteLoading,
  reverseQuoteLoading,
  isConnected,
  inputBalance,
  inputToken,
  awaitedEthBalance,
  isBackendConnected,
  hasValidQuote: computed(() => !!quote.value),
  minPurchaseAmount: computed(() => null), // No minimum for now
  isOtcMode: computed(() => false) // Not in OTC mode for now
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

// Use composable implementation
const calculateDiscount = swapCalculateDiscount

// Calculate quote for purchase (forward: input token -> CIRX)
const calculateQuote = (amount, token, isOTC = false) => {
  if (!amount || parseFloat(amount) <= 0) return null
  
  // Use backend pricing logic
  try {
    const quoteResult = calculateCirxQuote(amount, token, isOTC)
    
    const cirxAmountFloat = parseFloat(quoteResult.cirxAmount)
    const inputAmountFloat = parseFloat(amount)
    const rate = cirxAmountFloat / inputAmountFloat
    
    return {
      cirxAmount: quoteResult.cirxAmount,
      usdValue: quoteResult.usdValue,
      rate: rate.toFixed(6),
      inverseRate: (1 / rate).toFixed(8),
      discount: parseFloat(quoteResult.discountPercentage),
      fee: isOTC ? 0.15 : 0.3, // Backend handles fees internally
      platformFee: quoteResult.platformFee,
      totalPaymentRequired: quoteResult.totalPaymentRequired
    }
  } catch (error) {
    console.error('Backend quote calculation failed, using composable fallback:', error)
    
    // Fallback to composable implementation
    const composableQuote = swapCalculateQuote(amount, token, isOTC)
    if (composableQuote) {
      return {
        cirxAmount: composableQuote.cirxAmountFormatted || composableQuote.cirxAmount,
        usdValue: composableQuote.inputUsdValue,
        rate: (composableQuote.cirxAmount / parseFloat(amount)).toFixed(6),
        inverseRate: (parseFloat(amount) / composableQuote.cirxAmount).toFixed(8),
        discount: composableQuote.discount || 0,
        fee: composableQuote.feeRate || (isOTC ? 0.15 : 0.3),
        platformFee: composableQuote.feeUsd || 0,
        totalPaymentRequired: amount // Simplified for fallback
      }
    }
    
    return null
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
// Use composable implementation with fallback to local logic for UI consistency
const calculateReverseQuote = (cirxAmt, token, isOTC = false) => {
  const composableQuote = swapCalculateReverseQuote(cirxAmt, token, isOTC)
  if (composableQuote) {
    // Build forward quote for UI consistency
    const forward = calculateQuote(composableQuote.inputAmount.toString(), token, isOTC)
    
    return {
      inputAmount: composableQuote.inputAmount,
      forwardQuote: forward
    }
  }
  
  return null
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


// Token utility functions
const getTokenLogo = (token) => {
  const logoMap = {
    'ETH': 'https://assets.coingecko.com/coins/images/279/small/ethereum.png',
    'USDC': 'https://assets.coingecko.com/coins/images/6319/small/USD_Coin_icon.png',
    'USDT': 'https://assets.coingecko.com/coins/images/325/small/Tether.png',
    'SOL': 'https://assets.coingecko.com/coins/images/4128/small/solana.png',
    'USDC_SOL': 'https://assets.coingecko.com/coins/images/6319/small/USD_Coin_icon.png',
    'CIRX': '/buy/cirx-icon.svg'
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

// Validation composable initialized after validation state declarations

// Wrapper for getExchangeRateDisplay composable function with reactive values
const getExchangeRateDisplayLocal = () => getExchangeRateDisplay(cirxAmount.value)

// Wrapper for getNewCirxBalance composable function with reactive values
const getNewCirxBalanceLocal = () => getNewCirxBalance({
  cirxAmountValue: cirxAmount.value,
  recipientAddress: recipientAddress.value,
  recipientAddressError: recipientAddressError.value,
  isCircularChainConnected: isCircularChainConnected.value,
  cirxBalance: cirxBalance?.value || 0
})


// Saturn wallet detection
const isSaturnWalletPresent = computed(() => {
  if (typeof window === 'undefined') return false
  
  // Check for Saturn wallet provider
  return !!(window.saturn || 
           (window.ethereum && window.ethereum.isSaturn) ||
           (window.ethereum && window.ethereum.providers && 
            window.ethereum.providers.some(p => p.isSaturn)))
})

// Enhanced Saturn wallet detection based on our comprehensive detection
const isSaturnWalletDetected = computed(() => {
  // Saturn wallet detection disabled
  return false
})

// Saturn wallet watch disabled
// watch([isSaturnWalletDetected, isConnected], () => {
//   if (isSaturnWalletDetected.value && isConnected) {
//     // Saturn wallet detected and connected - turn toggle off, clear address for safety
//     customAddressEnabled.value = false
//     // DON'T auto-fill address - different chain/account model could cause fund loss
//     recipientAddress.value = ''
//   }
// }, { immediate: true })

// Methods

// Helper function to format numbers with commas
// formatWithCommas function moved to useSwapFormatting composable

// Handle input amount changes with comma formatting
const handleInputAmountChange = (value) => {
  // Auto-select native token if none selected and user starts typing
  if (!inputToken.value && value && parseFloat(value) > 0) {
    autoSelectNativeToken()
  }
  
  // Set the formatted value
  inputAmount.value = formatWithCommas(value)
  
  lastEditedField.value = 'input'
}

// Handle CIRX amount changes with comma formatting
const handleCirxAmountChange = (value) => {
  // Auto-select native token if none selected and user starts typing in CIRX field
  if (!inputToken.value && value && parseFloat(value) > 0) {
    autoSelectNativeToken()
  }
  
  // Set the formatted value
  cirxAmount.value = formatWithCommas(value)
  
  lastEditedField.value = 'output'
}

const setMaxAmount = () => {
  console.log('🔧 setMaxAmount called for token:', inputToken.value)
  console.log('🔧 Current inputBalance:', inputBalance.value)
  
  // Can't set max if no wallet connected or no balance available
  if (!isConnected?.value || inputBalance.value === null) {
    console.log('🔧 No wallet connected or no balance available')
    return
  }
  
  // Get the current balance for the selected token
  const balance = parseFloat(inputBalance.value || '0')
  
  if (balance > 0) {
    // Reserve different amounts based on token type
    let maxAmount = 0
    
    if (inputToken.value === 'ETH') {
      // Reserve more ETH for gas fees (5% reserve)
      maxAmount = balance * 0.95
    } else {
      // For ERC-20 tokens (USDC/USDT), reserve less (1% for micro gas)
      maxAmount = balance * 0.99
    }
    
    console.log('🔧 Setting max amount:', {
      token: inputToken.value,
      balance,
      maxAmount,
      formatted: maxAmount.toFixed(6)
    })
    
    inputAmount.value = maxAmount.toFixed(6)
  } else {
    inputAmount.value = '1.0' // Fallback for demo
  }

  // Set edit state to input when using max amount
  lastEditedField.value = 'input'
}

const forceRefreshBalance = async () => {
  console.log('🔄 Force refreshing balance...')
  // With Wagmi, balance refreshes automatically
  console.log('✅ Balance refresh not needed with Wagmi - auto-refreshes')
}

const selectToken = (token) => {
  console.log('🔧 selectToken called with:', token)
  console.log('🔧 Current inputToken before change:', inputToken.value)
  
  // Update the selected token
  inputToken.value = token
  showTokenDropdown.value = false
  
  console.log('🔧 inputToken updated to:', inputToken.value)
  console.log('🔧 New balance for token:', inputBalance.value)
  
  // Test: Force reactivity update
  nextTick(() => {
    console.log('🔧 After nextTick - inputToken:', inputToken.value)
    console.log('🔧 DOM should now reflect new token')
  })
  
  // Reset input when token changes (optional - user can decide)
  // inputAmount.value = ''
  lastEditedField.value = 'input'
}

// Auto-select native token based on connected wallet
const autoSelectNativeToken = () => {
  if (connectedWallet.value === 'phantom') {
    // Phantom wallet - select SOL
    // Token selection functionality removed('SOL')
    console.log('🪙 Auto-selected SOL for Phantom wallet')
  } else {
    // Ethereum wallets (MetaMask, Coinbase, etc.) - select ETH
    // Token selection functionality removed('ETH')
    console.log('🪙 Auto-selected ETH for Ethereum wallet')
  }
}


// validateNumberInput function moved to useSwapValidation composable

const reverseSwap = () => {
  console.log('Reverse swap not supported yet')
}

// Format amount for display (e.g., "$1K", "$50K", "$1M")
// formatAmount function moved to useSwapFormatting composable


// Backend health check
const checkBackendHealth = async () => {
  let controller = null
  let timeoutId = null
  
  try {
    const config = useRuntimeConfig()
    const apiBaseUrl = config.public.apiBaseUrl || 'http://localhost:18423/api/v1'
    
    controller = new AbortController()
    timeoutId = setTimeout(() => {
      controller.abort()
    }, 8000) // Increased to 8 seconds for better reliability
    
    const response = await fetch(`${apiBaseUrl}/ping`, {
      signal: controller.signal,
      method: 'GET',
      headers: {
        'Content-Type': 'application/json'
      }
    })
    
    // Clear timeout on successful response
    if (timeoutId) {
      clearTimeout(timeoutId)
      timeoutId = null
    }
    
    if (response.ok) {
      const data = await response.json()
      // Use comprehensive transaction_ready check instead of basic status
      const wasConnected = isBackendConnected.value
      isBackendConnected.value = data.transaction_ready === true
      
      // Log health check details for debugging
      console.log('🏥 Backend health check:', {
        status: data.status,
        transaction_ready: data.transaction_ready,
        health_score: data.health_score,
        was_connected: wasConnected,
        now_connected: isBackendConnected.value
      })
      
      // Log any failed health checks for troubleshooting
      if (!data.transaction_ready && data.checks) {
        const failedChecks = Object.entries(data.checks)
          .filter(([key, check]) => check.status === 'error')
          .map(([key, check]) => `${key}: ${check.error || 'unknown error'}`)
        
        if (failedChecks.length > 0) {
          console.warn('⚠️ Backend not transaction-ready. Failed checks:', failedChecks)
        }
      }
    } else {
      console.error(`❌ Backend health endpoint returned ${response.status}`)
      isBackendConnected.value = false
    }
  } catch (error) {
    // Handle AbortError differently from other errors
    if (error.name === 'AbortError') {
      console.warn('⏱️ Backend health check timed out (8s) - backend may be slow')
    } else {
      console.error('Backend health check failed:', error)
    }
    isBackendConnected.value = false
  } finally {
    // Always clean up timeout
    if (timeoutId) {
      clearTimeout(timeoutId)
    }
  }
}

// CTA Button Event Handlers - Now using centralized handlers from useCallToActionState.js
// Old duplicate handlers removed - now handled by CTA composable

const handleSwap = async () => {
  try {
    console.log('🔥 HANDLESWAP CALLED IN SWAP.VUE!', {
      isConnected: isConnected,
      address: address,
      recipientAddress: recipientAddress.value,
      inputAmount: inputAmount.value,
    })
  
    // PRIORITY: Handle wallet connection states FIRST (before input validation)
    if (!isConnected?.value && (!recipientAddress.value || recipientAddress.value.trim() === '')) {
      console.log('🔥 State 1: Connect - Opening wallet modal')
      console.log('✅ Opening AppKit modal via composable')
      console.log('Wallet connection removed')
      return
    }
    
    if (!isConnected?.value && recipientAddress.value && recipientAddress.value.trim() !== '') {
      console.log('🔥 State 2: Connect Wallet - Opening wallet modal')
      open() // Open AppKit modal
      return
    }
    
    // If button shows "Enter Address", focus the address input field instead of swapping
    if (isConnected?.value && (!recipientAddress.value || recipientAddress.value.trim() === '')) {
      console.log('🔥 No address entered, focusing address input field')
      const addressInput = addressInputRef.value
      if (addressInput) {
        addressInput.focus()
        addressInput.select() // Also select any existing text
      }
      return // Don't proceed with swap
    }
    
    // If button shows "Enter an amount", focus the input field instead of swapping
    if (!inputAmount.value || parseFloat(inputAmount.value) <= 0) {
      console.log('🔥 No amount entered, focusing amount input field')
      const amountInput = amountInputRef.value
      if (amountInput) {
        amountInput.focus()
        amountInput.select() // Also select any existing text
      }
      return // Don't proceed with swap
    }
    
    console.log('🔥 Proceeding with swap logic...', {
      recipientAddressError: recipientAddressError.value,
      inputAmount: inputAmount.value,
      loading: loading.value,
      quoteLoading: quoteLoading.value,
      reverseQuoteLoading: reverseQuoteLoading.value
    })
    
    // State 3: "Enter Address" - Wallet connected but no address input
    if (isConnected?.value && (!recipientAddress.value || recipientAddress.value.trim() === '')) {
      // Set flag to indicate user has clicked "Enter Address"
      hasClickedEnterAddress.value = true
      // Focus the CIRX address input field specifically
      const addressInput = document.querySelector('input[placeholder*="Circular Protocol"]')
      if (addressInput) {
        addressInput.focus()
        addressInput.scrollIntoView({ behavior: 'smooth', block: 'center' })
      }
      return
    }
    
    // State 7: "..." - Address is being validated (yellow flashing)
    if (addressValidationState.value === 'validating') {
      // Don't do anything, just show the "..." text and wait for validation to complete
      return
    }

    // State 6: "Modal" - Connected + amount + address populated but not confirmed (not green)
    if (isConnected?.value && inputAmount.value && parseFloat(inputAmount.value) > 0 && recipientAddress.value && recipientAddress.value.trim() !== '') {
      // Check if address is not confirmed (not green light state)
      const isAddressConfirmed = recipientAddressType.value === 'circular' && 
                                !recipientAddressError.value && 
                                addressValidationState.value === 'valid' && 
                                recipientAddress.value && !recipientAddressError.value
      
      if (!isAddressConfirmed) {
        // Show modal
        showConfirmationModal.value = true
        return
      }
    }

    // State 5: "Get CIRX Wallet" - Invalid address, clear input for Ethereum/EVM addresses
    if (recipientAddress.value && recipientAddressError.value) {
      // Check if it's an Ethereum address error - clear the input instead of showing modal
      if (recipientAddressError.value.includes('Ethereum addresses are not supported')) {
        recipientAddress.value = ''
        hasClickedEnterAddress.value = false
        // Focus the address input after clearing
        nextTick(() => {
          const addressInput = addressInputRef.value
          if (addressInput) {
            addressInput.focus()
          }
        })
        return
      }
      // For other errors, show the modal
      showConfirmationModal.value = true
      return
    }
    
    // Force debug the canPurchase conditions
    const hasAmount = inputAmount.value && parseFloat(inputAmount.value) > 0
    const notLoading = !loading.value && !quoteLoading.value && !reverseQuoteLoading.value
    const addressValid = recipientAddress.value && 
      (addressValidationState.value === 'valid' || !recipientAddressError.value)
    const connected = isConnected?.value || false
    const hasValidRecipient = connected || (recipientAddress.value && addressValid)
    const inputAmountNum = parseFloat(inputAmount.value) || 0
    const balanceNum = parseFloat(inputBalance.value) || 0
    const gasReserve = inputToken.value === 'ETH' ? 0.01 : 0
    const availableBalance = Math.max(0, balanceNum - gasReserve)
    const hasSufficientBalance = !connected || (inputAmountNum <= availableBalance)
    const ethBal = parseFloat(awaitedEthBalance.value) || 0
    const feeEth = parseFloat(networkFee.value?.eth || '0') || 0
    const tokenBal = parseFloat(inputBalance.value) || 0
    let hasSufficientForFees = true
    if (isConnected) {
      if (inputToken.value === 'ETH') {
        hasSufficientForFees = ethBal >= (inputAmountNum + (feeEth || 0))
      } else {
        hasSufficientForFees = tokenBal >= inputAmountNum && ethBal >= (feeEth || 0)
      }
    }
    
    console.log('🔥 MANUAL canPurchase DEBUG:', {
      hasAmount,
      notLoading, 
      hasValidRecipient,
      hasSufficientBalance,
      hasSufficientForFees,
      canPurchaseValue: canPurchase.value,
      inputAmountNum,
      balanceNum,
      ethBal,
      feeEth,
      gasReserve,
      availableBalance
    })
    
    console.log('🔥 CHECKING canPurchase:', canPurchase.value)
    if (!canPurchase.value) {
      console.log('🔥 BLOCKED BY canPurchase = false - BYPASSING FOR TESTING')
      // Temporarily bypass canPurchase for testing
      console.log('🔥 BYPASSING canPurchase check - proceeding with swap')
    }
    console.log('🔥 canPurchase PASSED - CONTINUING TO SWAP')
    
    loading.value = true
    
    // CRITICAL PRE-FLIGHT CHECK: Ensure backend is transaction-ready before any blockchain action
    loadingText.value = 'Verifying backend transaction readiness...'
    
    // Force a health check before proceeding with any blockchain transaction
    console.log('🛡️ CRITICAL PRE-FLIGHT: Checking backend transaction readiness...')
    await checkBackendHealth()
    
    if (!isBackendConnected.value) {
      throw new Error('Backend is not ready to process transactions. Please wait and try again.')
    }
    
    console.log('✅ CRITICAL PRE-FLIGHT: Backend is transaction-ready, proceeding with blockchain transaction')
    
    const tokenToUse = inputToken.value || 'ETH' // Default to ETH if not selected
    const depositAddress = getDepositAddress(tokenToUse, 'ethereum')
    const isOTC = activeTab.value === 'otc'
    
    // Step 1: Prepare transaction parameters
    loadingText.value = 'Preparing blockchain transaction...'
    
    // Get the quote to determine exact payment needed (including platform fee)
    const backendQuote = calculateCirxQuote(inputAmount.value, tokenToUse, isOTC)
    const totalPaymentNeeded = backendQuote.totalPaymentRequired
    
    // Log transaction details instead of showing confirm dialog
    console.log('🔥 TRANSACTION DETAILS:', {
      sending: `${totalPaymentNeeded} ${tokenToUse}`,
      receiving: `${cirxAmount.value} CIRX`,
      depositAddress,
      recipient: recipientAddress.value
    })
    
    // Step 2: Use unified swap transaction handler
    console.log('🔥 Using unified swap transaction handler')
    
    const result = await swapTx.executeSwap({
      tokenSymbol: tokenToUse,
      amount: totalPaymentNeeded,
      recipientAddress: recipientAddress.value,
      cirxAmount: cirxAmount.value,
      isOTC: isOTC,
      callbacks: {
        onStatusUpdate: (status) => {
          // Update loading text based on status
          if (status === 'checking_health') {
            loadingText.value = 'Verifying backend transaction readiness...'
          } else if (status === 'executing_blockchain') {
            loadingText.value = `Sending ${totalPaymentNeeded} ${tokenToUse}...`
          } else if (status === 'tracking_status') {
            loadingText.value = 'Tracking transaction status...'
          }
        },
        onComplete: () => {
          // Reset form on successful completion
          inputAmount.value = ''
          cirxAmount.value = ''
          quote.value = null
        }
      }
    })
    
    if (result.success) {
      // Show success message with option to track status
      const message = `Transaction sent successfully!\n\nTransaction Hash: ${result.txHash.slice(0, 10)}...\nSwap ID: ${result.swapId}\n\nYour ${cirxAmount.value} CIRX ${isOTC ? 'will be vested over 6 months and' : 'will'} be sent to ${recipientAddress.value} once the transaction is confirmed.\n\nClick OK to view the status page, or Cancel to continue trading.`
      
      if (confirm(message)) {
        // Navigate to status page
        await swapTx.navigateToStatus(result.swapId)
      }
    }
    
  } catch (error) {
    console.error('Swap failed:', error)
    
    // Provide more specific error messages
    let errorMessage = 'Transaction failed: '
    if (error.message.includes('rejected')) {
      errorMessage += 'Transaction was rejected by user'
    } else if (error.message.includes('insufficient funds')) {
      errorMessage += 'Insufficient balance to complete transaction'
    } else if (error.message.includes('contract address')) {
      errorMessage += 'Token contract not configured. Please contact support.'
    } else {
      errorMessage += error.message
    }
    
    alert(errorMessage)
  } finally {
    loading.value = false
    loadingText.value = ''
  }
}

// Handle OTC tier selection
const handleTierChange = (tier) => {
  console.log('🔧 INDEX.VUE handleTierChange called with tier:', tier)
  console.log('🔧 Current inputAmount before change:', inputAmount.value)
  
  selectedTier.value = tier

  if (activeTab.value !== 'otc') {
    console.log('🔧 Not in OTC tab, skipping calculation')
    return
  }

  // When user picks a tier, set the input amount to the minimum USD required for that tier
  // Convert tier.minAmount USD into selected input token units
  const tokenPriceUsd = livePrices.value[inputToken.value] || 0  // Fixed: added .value
  console.log('🔧 TOKEN PRICE DEBUG:', {
    inputToken: inputToken.value,
    livePrices: livePrices.value,
    tokenPriceUsd,
    tierMinAmount: tier?.minAmount
  })
  if (tokenPriceUsd > 0 && tier?.minAmount) {
    const feeRate = fees.value.otc
    const feeMultiplier = 1 - feeRate / 100
    console.log('🔧 Fee calculation:', { feeRate, feeMultiplier })
    
    if (feeMultiplier > 0) {
      // We want amountAfterFee * tokenPriceUsd >= tier.minAmount
      // amountAfterFee = inputAmount * feeMultiplier => inputAmount = minUsd / (tokenPriceUsd * feeMultiplier)
      const requiredInput = tier.minAmount / (tokenPriceUsd * feeMultiplier)
      const formattedInput = requiredInput.toFixed(6)
      
      console.log('🔧 Calculation result:', {
        tierMinAmount: tier.minAmount,
        requiredInput,
        formattedInput,
        oldInputAmount: inputAmount.value
      })
      
      inputAmount.value = formattedInput
      lastEditedField.value = 'input'
      
      console.log('🔧 inputAmount updated to:', inputAmount.value)
    } else {
      console.warn('🔧 Invalid fee multiplier:', feeMultiplier)
    }
  } else {
    console.warn('🔧 Cannot calculate: tokenPriceUsd =', tokenPriceUsd, 'tier.minAmount =', tier?.minAmount)
  }

  // Recalculate quote with new tier
  if (inputAmount.value && parseFloat(inputAmount.value) > 0) {
    // Use async quote calculation for accurate pricing
    calculateQuoteAsync(inputAmount.value, inputToken.value, true).then(newQuote => {
      if (newQuote) {
        quote.value = newQuote
        const cirxRaw = parseFloat(newQuote.cirxAmount.replace(/,/g, ''))
        cirxAmount.value = isFinite(cirxRaw) ? formatWithCommas(cirxRaw.toString()) : formatWithCommas(newQuote.cirxAmount)
      }
    }).catch(error => {
      console.error('Tier change quote calculation failed:', error)
    })
  }
}

// Debounced quote calculation for better UX
let quoteTimeout = null

// Watch for wallet connection changes - removed problematic positioning
// Token selector now uses CSS-only positioning for consistency

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
        const tokenPriceUsd = livePrices.value[inputToken] || 0
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
        cirxAmount.value = isFinite(cirxRaw) ? formatWithCommas(cirxRaw.toString()) : formatWithCommas(newQuote.cirxAmount)
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
  }, 10000)
})

// Address validation is now handled by @blur event on the input field

const alignTokenSelector = () => {
  const cirxButton = document.querySelector('.token-display-right')
  const tokenSelector = document.querySelector('.token-dropdown-container')

  console.log('cirxButton', cirxButton)
  console.log('tokenSelector', tokenSelector)

  if (cirxButton && tokenSelector) {
    const cirxButtonRect = cirxButton.getBoundingClientRect()
    console.log('cirxButtonRect', cirxButtonRect)
    tokenSelector.style.position = 'absolute'
    tokenSelector.style.left = `${cirxButtonRect.left}px`
    tokenSelector.style.top = `${cirxButtonRect.top}px`
  }
}


// Handle keydown to prevent invalid characters from being typed (same logic as RecipientAddressInput)
const handleAddressKeydown = (event) => {
  const currentValue = event.target.value
  const key = event.key
  
  // Allow control keys
  if (['Backspace', 'Delete', 'Tab', 'Escape', 'Enter', 'Home', 'End', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(key) || 
      event.ctrlKey || event.metaKey) {
    return
  }
  
  // If field is empty and user types something other than '0', prevent it
  if (currentValue === '' && key !== '0') {
    event.preventDefault()
    return
  }
  
  // If field has '0' and user types something other than 'x', prevent it
  if (currentValue === '0' && key !== 'x') {
    event.preventDefault()
    return
  }
  
  // If field starts with '0x', only allow hex characters (0-9, a-f, A-F)
  if (currentValue.startsWith('0x')) {
    if (!/[0-9a-fA-F]/.test(key)) {
      event.preventDefault()
      return
    }
  }
  
  // If field has something that doesn't start with '0x', prevent further input
  if (currentValue && !currentValue.startsWith('0x') && currentValue !== '0') {
    event.preventDefault()
    return
  }
}

// Sanitize address input to remove spaces and trigger validation during typing
const sanitizeAddressInput = (event) => {
  const input = event.target
  const value = input.value
  
  // Remove all spaces from the input
  const sanitized = value.replace(/\s/g, '')
  
  // If the value changed, update it
  if (sanitized !== value) {
    recipientAddress.value = sanitized
    // Set cursor position to the end
    nextTick(() => {
      input.setSelectionRange(sanitized.length, sanitized.length)
    })
  }
  
  // Trigger validation during typing for potential Circular addresses
  // Check if the address looks like it could be a complete Circular address (66 chars starting with 0x)
  if (sanitized && sanitized.startsWith('0x') && sanitized.length === 66) {
    console.log('🔍 Address looks complete during typing - triggering validation:', sanitized)
    // Ensure the recipientAddress is updated before validation
    recipientAddress.value = sanitized
    // Trigger async validation immediately when address appears complete
    nextTick(() => {
      // Use recipientAddress.value instead of passing sanitized
      handleValidateAddress(recipientAddress.value)
    })
  }
}

// Validation functions moved to useSwapValidation composable

// Handle Circular chain events
const handleCircularChainHelp = () => {
  // Show help information in toast
  if (typeof window !== 'undefined' && window.$toast?.connection) {
    window.$toast.connection.success('Visit docs.circular.protocol for setup instructions or contact support', { 
      title: 'Circular Chain Help' 
    })
  }
}

const handleChainAdded = () => {
  // Show success message in toast
  if (typeof window !== 'undefined' && window.$toast?.connection) {
    window.$toast.connection.success('Circular chain has been added to your wallet', {
      title: 'Chain Added Successfully'
    })
  }
}

// Close dropdown and slider when clicking outside
onMounted(async () => {
  // Start backend health check immediately
  await checkBackendHealth()
  
  // Set up periodic health check every 10 seconds
  backendHealthCheckInterval.value = setInterval(() => {
    checkBackendHealth()
  }, 10000)
  
  // Fetch OTC configuration on component mount
  await fetchOtcConfig()
  
  // Fetch network configuration for dynamic placeholder
  await fetchNetworkConfig()
  
  // Start chart data preloading after swap page is fully loaded
  await startChartPreloading()
  
  // Start price updates manually (composable may not have component context)
  startPriceUpdates()
  
  const handleClickOutside = (event) => {
    if (showTokenDropdown.value && !event.target.closest('.token-dropdown-container')) {
      showTokenDropdown.value = false
    }
  }
  
  document.addEventListener('click', handleClickOutside)
  
  // Global Enter key handler to trigger swap action
  const handleGlobalEnter = (event) => {
    if (event.key === 'Enter' && !event.ctrlKey && !event.metaKey && !event.altKey) {
      // Skip if user is typing in a textarea
      if (event.target.tagName === 'TEXTAREA') return
      
      // Handle Enter in form inputs - trigger swap directly
      if (event.target.tagName === 'INPUT') {
        const inputType = event.target.type
        
        // For text and number inputs in the form, prevent default and trigger swap
        if (inputType === 'text' || inputType === 'number') {
          event.preventDefault()
          
          // Only trigger if not loading and form is ready
          if (!loading.value) {
            console.log('🎯 Enter pressed in input field, triggering swap...')
            handleSwap().catch(error => {
              console.error('❌ Error triggered by Enter key in input:', error)
            })
          } else {
            console.log('⏸️ Skipping Enter action - loading:', loading.value)
          }
          return
        }
        
        // Skip other input types
        return
      }
      
      // For clicks anywhere else on the page, trigger swap
      event.preventDefault()
      
      // Only trigger if not loading and not connecting wallet
      if (!loading.value) {
        handleSwap().catch(error => {
          console.error('❌ Error triggered by Enter key:', error)
        })
      }
    }
  }
  
  document.addEventListener('keydown', handleGlobalEnter)
  
  // Lifecycle hooks already registered earlier - no need to register again

  // Async initialization after lifecycle hooks are registered
  await Promise.all([refreshPrices(), fetchGasPrice()])
  startPriceCountdown()

  // alignTokenSelector() // Removed - using CSS-only positioning

  // Extension detection disabled
  // setTimeout(() => {
  //   console.log('🔍 Running extension detection from swap page...')
  //   detectAllExtensions()
  //   
  //   // Debug Saturn wallet detection for UI
  //     'window.saturn': !!window.saturn,
  //     'window.extension': !!window.extension,
  //     'window.ethereum?.isSaturn': !!(window.ethereum?.isSaturn),
  //     'providers check': !!(window.ethereum?.providers?.some?.(p => p.isSaturn)),
  //     'DOM elements': !!document.querySelector('[data-saturn], [class*="saturn"], [id*="saturn"]'),
  //     'isSaturnWalletDetected': isSaturnWalletDetected.value
  //   })
  // }, 3000)
})

onUnmounted(() => {
  if (countdownTimer) clearInterval(countdownTimer)
  if (backendHealthCheckInterval.value) clearInterval(backendHealthCheckInterval.value)
  // Stop price updates when component unmounts
  stopPriceUpdates()
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

/* Gradient border effect */
.gradient-border {
  position: relative;
  border: 1px solid rgba(55, 65, 81, 0.5);
  border-radius: 1rem;
  transition: all 0.3s ease;
}

.gradient-border:hover {
  border: 1px solid #00ff88;
  animation: border-color-cycle 24s ease infinite;
}

@keyframes border-color-cycle {
  0% { border-color: #00ff88; }
  25% { border-color: #00d9ff; }
  50% { border-color: #8b5cf6; }
  75% { border-color: #a855f7; }
  100% { border-color: #00ff88; }
}

/* Styles for GetCircularWallet modal moved to component */

/* CIRX OTC Connected Swap Fields */
.swap-container {
  position: relative;
  background: #000306;
  border-radius: 16px;
  padding: 4px;
  transition: all 0.3s ease;
  overflow: visible;
}


/* Remove container-level focus - we want individual field focus */

/* Individual input sections */
.input-section {
  position: relative;
  background: rgba(21, 30, 40, 0.3);
  border-radius: 12px;
  border: 1px solid transparent;
  transition: all 0.3s ease;
  overflow: visible;
}

.input-section-top {
  border-radius: 12px 12px 0 0;
  margin-bottom: 0;
  /* Consistent padding for alignment */
  padding: 20px 16px;
  background: #0D141B;
  backdrop-filter: none;
  border: 1px solid #0D141B;
  border-bottom: none;
}

.input-section-bottom {
  border-radius: 0 0 12px 12px;
  margin-top: 0;
  /* Consistent padding for alignment */
  padding: 20px 16px;
  background: #050A0F;
  backdrop-filter: none;
  border: 1px solid #050A0F;
  border-top: none;
}

.input-section:hover {
  background: #151E28;
}

/* Simple focus states - just background color change */
.input-section:focus-within {
  background: #151E28;
}

/* Opposite styling when the other field is selected */
.swap-container:has(.input-section-top:focus-within) .input-section-bottom {
  background: #0D141B !important;
  border-color: #0D141B !important;
}

.swap-container:has(.input-section-bottom:focus-within) .input-section-top {
  background: #050A0F !important;
  border-color: #050A0F !important;
}

.input-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}

.balance-display {
  font-size: 0.875rem;
  color: #9CA3AF;
  cursor: pointer;
  transition: color 0.3s ease;
}

.balance-display:hover {
  color: #ffffff;
}

.input-content {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 8px;
  overflow: visible; /* Allow dropdowns to show */
  position: relative; /* Create positioning context */
}

.amount-input {
  flex: 1;
  background: transparent;
  border: none;
  outline: none;
  font-size: 1.5rem;
  font-weight: 600;
  color: #ffffff;
  padding: 0;
}

.amount-input::placeholder {
  color: #6B7280;
}

.amount-input:focus {
  outline: none;
  box-shadow: none;
}

/* Token selector styling now applied via inline classes */

/* Token display styling now applied via inline classes */

.token-display-left {
  position: absolute;
  left: 0;
  top: 50%;
  transform: translateY(-50%);
}

.token-display-right {
  position: absolute;
  right: 0;
  top: 50%;
  transform: translateY(-50%);
}

.token-display-otc {
  position: absolute;
  right: 0;
  top: 50%;
  transform: translateY(-50%);
}

.token-icon {
  width: 20px;
  height: 20px;
  border-radius: 50%;
}

.token-symbol {
  font-weight: 600;
  color: #ffffff;
  font-size: 0.875rem;
}

.dropdown-arrow {
  color: #9CA3AF;
  transition: transform 0.2s ease;
}

.token-dropdown {
  position: absolute !important;
  top: calc(100% + 8px) !important;
  right: 0 !important;
  left: auto !important;
  background: #1F2937 !important;
  border: 1px solid rgba(55, 65, 81, 0.7) !important;
  border-radius: 12px !important;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4) !important;
  z-index: 50 !important;
  width: 120px !important;
  overflow: hidden !important;
  /* Nuclear option: fixed positioning relative to parent */
  transform: none !important;
  margin: 0 !important;
  padding: 0 !important;
}

/* Token dropdown container - provides positioning context */
.token-dropdown-container {
  position: absolute;
  right: 0;
  top: 50%;
  transform: translateY(-50%);
  /* Ensure container provides proper bounds for dropdown */
  min-width: 120px;
  /* Force this to be the positioning context */
  z-index: 100;
  isolation: isolate;
  /* Additional containment for good measure */
  contain: layout style;
}

/* Removed token-selector-container - now using absolute positioning */

.token-dropdown-simple {
  position: absolute;
  top: calc(100% + 20px);
  right: 0;
  background: #1F2937;
  border: 1px solid rgba(55, 65, 81, 0.7);
  border-radius: 12px;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
  z-index: 100;
  width: 110px;
  overflow: hidden;
}


.dropdown-left {
  right: auto !important;
  left: auto !important;
  /* Position dropdown to align with the right edge of the token selector */
  transform: translateX(-20px) !important;
}

.token-option {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  padding: 12px 16px;
  background: transparent;
  border: none;
  color: #ffffff;
  cursor: pointer;
  transition: background-color 0.2s ease;
}

.token-option:hover {
  background: rgba(55, 65, 81, 0.5);
}

.usd-value {
  font-size: 0.75rem;
  color: #6B7280;
  text-align: left;
  margin-top: 4px;
  min-height: 1rem; /* Reserve space to prevent layout shift */
  display: flex;
  align-items: center;
}

/* Swap Arrow */
.swap-arrow-container {
  position: absolute;
  left: 50%;
  top: 50%;
  transform: translate(-50%, -50%);
  z-index: 10;
}

.swap-arrow-button {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  border: 4px solid #000306;
  background: #000306;
  cursor: pointer;
}

.swap-arrow-button:hover {
  opacity: 0.8;
}

.swap-arrow-button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.swap-arrow-liquid {
  color: #01DA9D;
  background: #000306 !important;
}

.swap-arrow-otc {
  color: #9333ea;
  background: #000306 !important;
}

/* Hide number input spinner arrows */
input[type="number"]::-webkit-outer-spin-button,
input[type="number"]::-webkit-inner-spin-button {
  -webkit-appearance: none !important;
  appearance: none !important;
  margin: 0 !important;
  display: none !important;
}

/* Firefox */
input[type="number"] {
  -moz-appearance: textfield !important;
  appearance: textfield !important;
}

/* Blinking animation for yellow status circle */
@keyframes blink {
  0%, 50% { 
    opacity: 1; 
  }
  51%, 100% { 
    opacity: 0.3; 
  }
}

.animate-flash {
  animation: flash 1s ease-in-out infinite;
}

@keyframes flash {
  0%, 100% { 
    opacity: 0;
    transform: scale(0.8);
  }
  50% { 
    opacity: 1;
    transform: scale(1);
  }
}

/* Pulse animation for backend error icon */
@keyframes pulse {
  0% {
    opacity: 1;
  }
  50% {
    opacity: 0.5;
  }
  100% {
    opacity: 1;
  }
}

</style>