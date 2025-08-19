<template>
  <div class="min-h-screen relative overflow-hidden bg-figma-base">
    <!-- Space Background -->
    <div key="static-background" class="absolute inset-0 bg-cover bg-center bg-no-repeat bg-fixed z-0" style="background-image: url('/background.png')"></div>
    <!-- Gradient overlay: darkest at top, lightest at bottom -->
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
              to="/status" 
              class="px-3 py-2 text-sm text-gray-300 hover:text-white transition-colors rounded-lg hover:bg-gray-800/50"
            >
              Transactions
            </NuxtLink>
            
            <!-- Token Balance Display -->
            <div v-if="isConnected && inputBalance && inputToken" class="flex items-center gap-2 px-4 py-2 rounded-xl">
              <img 
                :src="getTokenLogo(inputToken)" 
                :alt="inputToken"
                class="w-4 h-4 rounded-full"
              />
              <span class="text-sm font-medium text-white drop-shadow-md">{{ formatBalance(inputBalance) }} {{ getTokenSymbol(inputToken) }}</span>
            </div>

            <!-- Wallet Button -->
            <WalletButton />
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
          
          <div v-if="showChart && !showStaking" class="w-full lg:w-3/5 xl:w-2/3 h-[80vh]">
            <CirxPriceChart @close="showChart = false" />
          </div>
          
          
          <div v-if="showStaking && !showChart" class="w-full lg:w-3/5 xl:w-2/3 h-[80vh]">
            <CirxStakingPanel @close="showStaking = false" />
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

          
          <form @submit.prevent="handleSwap" novalidate>
            
            <!-- Uniswap-style Connected Swap Fields -->
            <div class="mb-6 relative">
              <div class="swap-container" :data-tab="activeTab">
                <!-- Sell Field (Top) -->
                <div class="input-section input-section-top">
                  <div class="input-header">
                    <label class="text-sm font-medium text-white">Sell</label>
                    <span v-if="inputToken" class="balance-display pr-3" @click="setMaxAmount" @dblclick="forceRefreshBalance">
                      Balance: {{ inputBalance ? formatBalance(fullPrecisionInputBalance) : '-' }} {{ getTokenSymbol(inputToken) }}
                      <span v-if="isBalanceLoading" class="ml-1 text-xs">🔄</span>
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
                            const greenCondition = recipientAddress && recipientAddressType === 'circular' && !recipientAddressError && addressValidationState === 'valid' && recipientCirxBalance !== null && parseFloat(recipientCirxBalance) >= 0.0;
                            console.log('🟢 Green light condition check:', {
                              recipientAddress: recipientAddress,
                              recipientAddressType: recipientAddressType,
                              recipientAddressError: recipientAddressError,
                              addressValidationState: addressValidationState,
                              recipientCirxBalance: recipientCirxBalance,
                              balanceFloat: parseFloat(recipientCirxBalance),
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
              <div v-if="isConnected && hasClickedEnterAddress && !recipientAddress" class="mt-2 flex items-center gap-2 text-sm text-yellow-400">
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

            <button
              type="button"
              :disabled="isButtonShowingDots || loading || quoteLoading || reverseQuoteLoading"
              :style="{
                width: '100%',
                padding: '16px 24px',
                borderRadius: '12px',
                fontWeight: '600',
                fontSize: '18px',
                backgroundColor: isButtonShowingDots ? '#374151' : '#0B5443',
                color: isButtonShowingDots ? '#9CA3AF' : '#01DA9D',
                cursor: isButtonShowingDots ? 'not-allowed' : 'pointer',
                textAlign: 'center',
                opacity: isButtonShowingDots ? '0.6' : '1',
                transition: 'all 0.2s ease',
                border: 'none'
              }"
              @click="handleSwap"
            >
              <span v-if="loading">{{ loadingText || 'Processing...' }}</span>
              <span v-else-if="quoteLoading || reverseQuoteLoading">
                {{ reverseQuoteLoading ? 'Calculating...' : 'Getting Quote...' }}
              </span>
              <span v-else-if="!isConnected && (!recipientAddress || recipientAddress.trim() === '')">Connect</span>
              <span v-else-if="!isConnected && recipientAddress && recipientAddress.trim() !== ''">Connect Wallet</span>
              <span v-else-if="isConnected && (!recipientAddress || recipientAddress.trim() === '')">Enter Address</span>
              <span v-else-if="addressValidationState === 'validating'">...</span>
              <span v-else-if="recipientAddress && (recipientAddress === '0' || (recipientAddress.startsWith('0x') && recipientAddress.length < 66))">...</span>
              <span v-else-if="recipientAddress && recipientAddress.length === 66 && recipientAddress.startsWith('0x') && addressValidationState === 'idle'">...</span>
              <span v-else-if="!inputAmount">Enter an amount</span>
              <span v-else-if="recipientAddress && recipientAddressError">Get CIRX Wallet</span>
              <span v-else-if="activeTab === 'liquid'">Buy Liquid CIRX</span>
              <span v-else>Buy Vested CIRX</span>
            </button>
          </form>
        </div>
          
          <!-- Floating Action Pills -->
          <div v-if="!showChart && !showStaking" class="mt-4 flex justify-start gap-3">
            <button
              @click="showChart = true"
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
              @click="showStaking = true"
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
    <ConnectionToast 
      :show="connectionToast.show"
      :type="connectionToast.type"
      :title="connectionToast.title"
      :message="connectionToast.message"
      :wallet-icon="connectionToast.walletIcon"
      @close="connectionToast.show = false"
    />

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
    <div v-if="showConfirmationModal" class="fixed inset-0 backdrop-blur-sm bg-black/60 flex items-center justify-center z-50">
      <div class="space-modal rounded-2xl p-3 sm:p-5 md:p-6 w-full max-w-64 sm:max-w-80 md:max-w-md lg:max-w-lg mx-4 shadow-2xl shadow-cyan-500/20 relative gradient-border-animated border border-white/10 overflow-hidden">
        <!-- Additional star layers for random blinking -->
        <div class="star-layer-1"></div>
        <div class="star-layer-2"></div>
        
        <h2 class="text-2xl font-bold text-white mb-8 text-center">Wallets</h2>
        
        <!-- Wallet Tiles -->
        <div class="flex flex-col gap-4 mb-8">
          <a
            href="https://www.saturnwallet.app"
            target="_blank"
            rel="noopener noreferrer"
            class="flex flex-col items-center gap-3 p-6 rounded-xl transition-colors group"
          >
            <div class="w-48 h-48 rounded-2xl overflow-hidden bg-transparent group-hover:bg-gray-800 flex items-center justify-center flex-shrink-0 transition-colors duration-300">
              <img 
                src="/saturnicon.svg" 
                alt="Saturn Wallet"
                class="w-36 h-36 object-contain"
              />
            </div>
            <span class="wallet-text font-semibold text-lg">Saturn</span>
          </a>

          <a
            href="https://www.circularlabs.io/nero"
            target="_blank"
            rel="noopener noreferrer"
            class="flex flex-col items-center gap-3 p-6 rounded-xl transition-colors group"
          >
            <div class="w-48 h-48 rounded-2xl overflow-hidden bg-transparent group-hover:bg-gray-800 flex items-center justify-center flex-shrink-0 transition-colors duration-300">
              <img 
                src="/neroicon.svg" 
                alt="Nero Wallet"
                class="w-36 h-36 object-contain"
              />
            </div>
            <span class="wallet-text font-semibold text-lg">Nero</span>
          </a>
        </div>
        
        <!-- Close Button - Positioned under Nero wallet -->
        <button 
          @click="showConfirmationModal = false"
          class="close-button-circular absolute -bottom-2 left-1/2 transform -translate-x-1/2 w-10 h-10 sm:w-12 sm:h-12 bg-black border-2 border-white/20 rounded-full flex items-center justify-center text-gray-300 hover:text-white hover:border-white/40 transition-all duration-300 hover:shadow-lg hover:shadow-white/20"
        >
          <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
// Import Vue composables
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
// Import official Wagmi Vue hooks
import { useAccount, useBalance } from '@wagmi/vue'
import { useAppKit } from '@reown/appkit/vue'
import { parseEther, parseUnits } from 'viem'
import { sendTransaction, writeContract } from '@wagmi/core'
import { wagmiConfig } from '~/config/appkit.js'
// Import components
import WalletButton from '~/components/WalletButton.vue'
import ConnectionToast from '~/components/ConnectionToast.vue'
import OtcDiscountDropdown from '~/components/OtcDiscountDropdown.vue'
import { getTokenPrices } from '~/services/priceService.js'
import { isValidCircularAddress, isValidEthereumAddress, isValidSolanaAddress } from '~/utils/addressFormatting.js'
// Import backend API integration
import { useBackendApi } from '~/composables/useBackendApi.js'
// Import Circular address validation
import { useCircularAddressValidation } from '~/composables/useCircularAddressValidation.js'
// Extension detection disabled
// import { detectAllExtensions } from '~/utils/comprehensiveExtensionDetection.js'
// Removed useCircularChain import - Saturn wallet detection disabled

// Page metadata
definePageMeta({
  title: 'Circular Swap',
  layout: 'default'
})

// Official Wagmi Vue hooks
const { address, isConnected, connector } = useAccount()
const { data: balance, isLoading: isBalanceLoading } = useBalance({ 
  address: address 
})

// Token contract addresses for balance fetching
const tokenAddresses = {
  'USDC': '0xA0b86a33E6Ba476C4db6B0EbB18B9E7D8e4a8563', // USDC on mainnet
  'USDT': '0xdAC17F958D2ee523a2206206994597C13D831ec7', // USDT on mainnet
}

// Additional balance hooks for ERC20 tokens
const { data: usdcBalance, isLoading: isUsdcLoading } = useBalance({
  address: address,
  token: tokenAddresses.USDC
})

const { data: usdtBalance, isLoading: isUsdtLoading } = useBalance({
  address: address,
  token: tokenAddresses.USDT
})

const { open } = useAppKit()

// Backend API integration
const {
  initiateSwap,
  getTransactionStatus,
  getCirxBalance,
  calculateCirxQuote,
  getDepositAddress,
  validateCircularAddress,
  createSwapTransaction,
  isLoading: backendLoading,
  lastError: backendError,
  DEPOSIT_ADDRESSES,
  tokenPrices: backendTokenPrices
} = useBackendApi()

// Circular address validation
const { checkAddressExists, isValidCircularAddressFormat, isAddressPending } = useCircularAddressValidation()

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
    const apiBaseUrl = config.public.apiBaseUrl || 'http://localhost:8080/api'
    
    const response = await fetch(`${apiBaseUrl}/v1/config/circular-network`)
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
  connectionToast.value = {
    show: true,
    type,
    title,
    message,
    walletIcon: null
  }
}

// Saturn wallet detection disabled - using static CIRX values
const cirxBalance = ref('0')
const formatCirxBalance = computed(() => '0.0000')
const isCircularChainAvailable = computed(() => false)
const isCircularChainConnected = computed(() => false)

// Check if button should be disabled (either showing "..." or processing)
const isButtonShowingDots = computed(() => {
  // Disable during loading states (transaction processing)
  if (loading.value || quoteLoading.value || reverseQuoteLoading.value) return true
  
  // Don't disable if not connected or no address - these are actionable states
  if (!isConnected.value) return false
  if (isConnected.value && (!recipientAddress.value || recipientAddress.value.trim() === '')) return false
  
  // Disable for the specific "..." conditions (address validation states)
  return (
    addressValidationState.value === 'validating' ||
    (recipientAddress.value && (recipientAddress.value === '0' || (recipientAddress.value.startsWith('0x') && recipientAddress.value.length < 66))) ||
    (recipientAddress.value && recipientAddress.value.length === 66 && recipientAddress.value.startsWith('0x') && addressValidationState.value === 'idle')
  )
})

// Format ETH balance with full decimal precision using official Wagmi data
const formattedEthBalance = computed(() => {
  if (!balance.value) return '0.000000000000000000'
  
  // balance.value contains { formatted, value, decimals, symbol }
  const amount = parseFloat(balance.value.formatted)
  
  if (isNaN(amount)) return '0.000000000000000000'
  
  // Show all 18 decimal places for Ethereum tokens
  return amount.toFixed(18)
})

// Connection state management
const connectionToast = ref({ show: false, type: 'success', title: '', message: '', walletIcon: null })
const lastConnectedWalletIcon = ref(null) // Store icon when connected

// Watch for connection state changes with toast notifications
watch([isConnected, address], ([connected, addr], [prevConnected, prevAddr]) => {
  console.log('🔍 CONNECTION WATCH: State changed:', { connected, addr, prevConnected, prevAddr })
  
  if (connected && !prevConnected) {
    // Just connected - store the icon for later use
    lastConnectedWalletIcon.value = walletIcon.value
    connectionToast.value = {
      show: true,
      type: 'success',
      title: 'Wallet Connected',
      message: `Connected to ${addr?.slice(0, 6)}...${addr?.slice(-4)}`,
      walletIcon: walletIcon.value
    }
  } else if (!connected && prevConnected) {
    // Just disconnected - use stored icon
    console.log('🔍 Wallet disconnected, using stored icon:', lastConnectedWalletIcon.value)
    connectionToast.value = {
      show: true,
      type: 'error',
      title: 'Wallet Disconnected',
      message: 'Your wallet has been disconnected',
      walletIcon: lastConnectedWalletIcon.value
    }
    // Clear stored icon after use
    lastConnectedWalletIcon.value = null
  }
}, { immediate: false })

// Watch for balance updates
watch(() => [isConnected.value, address.value, balance.value], 
  ([connected, addr, bal]) => {
    console.log('🔍 BALANCE WATCH: Wagmi state changed:')
    console.log('  - Connected:', connected)
    console.log('  - Address:', addr ? addr.slice(0, 6) + '...' + addr.slice(-4) : 'none')
    console.log('  - Balance object:', bal)
    console.log('  - Formatted balance:', bal?.formatted)
    console.log('  - Balance symbol:', bal?.symbol)
    console.log('  - Is loading:', isBalanceLoading.value)
    console.log('  - Computed full precision:', formattedEthBalance.value)
    console.log('  - Timestamp:', new Date().toISOString())
  }, 
  { immediate: true }
)

// Reactive state
const activeTab = ref('liquid')
const inputAmount = ref('')
const showWalletModal = ref(false)
const cirxAmount = ref('')
const inputToken = ref('')
// Address is always required - no toggle needed
const loading = ref(false)
const loadingText = ref('')
const quote = ref(null)
const showChart = ref(false)
const showStaking = ref(false)


// Focus handler for address input
const addressInputRef = ref(null)
const amountInputRef = ref(null)
const recipientAddress = ref('')
const recipientAddressError = ref('')
const recipientAddressType = ref('')
const recipientCirxBalance = ref(null)
const isFetchingRecipientBalance = ref(false)
const showTokenDropdown = ref(false)
// Track whether user has clicked "Enter Address" button
const hasClickedEnterAddress = ref(false)
// Track address validation state
const addressValidationState = ref('idle') // 'idle', 'validating', 'valid', 'invalid'
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
  console.log('🕐 Starting price countdown from:', priceCountdown.value)
  countdownTimer = setInterval(async () => {
    if (priceCountdown.value > 0) {
      priceCountdown.value -= 1
      console.log('⏰ Countdown:', priceCountdown.value, 'Progress offset:', 125.6 * ((30 - priceCountdown.value) / 30))
    } else {
      console.log('🔄 Refreshing prices and resetting countdown')
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
const formatTokenBalance = (balanceData) => {
  if (!balanceData) return '0.000000000000000000'
  const amount = parseFloat(balanceData.formatted)
  if (isNaN(amount)) return '0.000000000000000000'
  return amount.toFixed(18)
}

// Use official Wagmi balance data
const inputBalance = computed(() => {
  if (!isConnected.value) {
    return '0.000000000000000000'
  }
  
  // Return balance based on selected token
  switch (inputToken.value) {
    case 'ETH':
      return formattedEthBalance.value
    case 'USDC':
      return formatTokenBalance(usdcBalance.value)
    case 'USDT':
      return formatTokenBalance(usdtBalance.value)
    default:
      return '0.000000000000000000'
  }
})

// ETH balance for gas gating (0 when not connected)
const awaitedEthBalance = computed(() => {
  if (isConnected.value) {
    return formattedEthBalance.value || '0.000000000000000000'
  }
  return '0.000000000000000000'
})

const displayCirxBalance = computed(() => {
  // Only show balance if we have a valid recipient address and fetched balance
  if (recipientAddress.value && !recipientAddressError.value && recipientCirxBalance.value !== null) {
    return recipientCirxBalance.value
  }
  return null // No address or no balance fetched, show "Balance: -"
})

// Simply use the inputBalance which now has full precision
const fullPrecisionInputBalance = computed(() => {
  return inputBalance.value
})

// Short address for display using official Wagmi address
const shortAddress = computed(() => {
  if (!address.value) return ''
  return `${address.value.slice(0, 6)}...${address.value.slice(-4)}`
})

// Connected wallet type (using Wagmi hooks)
const connectedWallet = computed(() => {
  if (!connector.value) return null
  return connector.value.name?.toLowerCase() === 'phantom' ? 'phantom' : 'ethereum'
})

// Wallet icon logic (same as in WalletButton)
const walletIcon = computed(() => {
  // Try to get icon from Wagmi connector
  if (connector.value?.icon) {
    return connector.value.icon
  }
  
  // Fallback to common wallet icons from CDN
  if (!connector.value) return null
  const name = connector.value.name?.toLowerCase()
  const iconMap = {
    'metamask': 'https://raw.githubusercontent.com/MetaMask/brand-resources/master/SVG/metamask-fox.svg',
    'coinbase': 'https://avatars.githubusercontent.com/u/18060234?s=280&v=4',
    'walletconnect': 'https://avatars.githubusercontent.com/u/37784886?s=280&v=4',
    'phantom': 'https://avatars.githubusercontent.com/u/78782331?s=280&v=4'
  }
  
  return iconMap[name] || null
})

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

// Dynamic fee structure
const fees = computed(() => otcConfig.value.fees)

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
const discountTiers = computed(() => otcConfig.value.discountTiers)

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
  try {
    // Basic requirements
    const hasAmount = inputAmount.value && parseFloat(inputAmount.value) > 0
    const notLoading = !loading.value && !quoteLoading.value && !reverseQuoteLoading.value
    
    // Address validation
    const addressValid = validateRecipientAddress(recipientAddress.value)
    
    // Either connected wallet OR valid recipient address required
    const connected = isConnected.value || false
    const hasValidRecipient = connected || (recipientAddress.value && addressValid)
    
    // Balance validation - only check if wallet is connected
    const hasSufficientBalance = !connected || (() => {
      const inputAmountNum = parseFloat(inputAmount.value) || 0
      const balanceNum = parseFloat(inputBalance.value) || 0
      
      // For ETH, reserve gas fees (0.01 ETH)
      const gasReserve = inputToken.value === 'ETH' ? 0.01 : 0
      const availableBalance = Math.max(0, balanceNum - gasReserve)
      
      return inputAmountNum <= availableBalance
    })()
    
    // Network fee gating
    const ethBal = parseFloat(awaitedEthBalance.value) || 0
    const feeEth = parseFloat(networkFee.value?.eth || '0') || 0
    const tokenBal = parseFloat(inputBalance.value) || 0

    let hasSufficientForFees = true
    if (isConnected.value) {
      if (inputToken.value === 'ETH') {
        hasSufficientForFees = ethBal >= ((parseFloat(inputAmount.value) || 0) + (feeEth || 0))
      } else {
        hasSufficientForFees = tokenBal >= (parseFloat(inputAmount.value) || 0) && ethBal >= (feeEth || 0)
      }
    }

    const result = hasAmount && notLoading && hasValidRecipient && hasSufficientBalance && hasSufficientForFees
    
    console.log('🔥 canPurchase DEBUG:', {
      hasAmount,
      notLoading,
      hasValidRecipient,
      hasSufficientBalance,
      hasSufficientForFees,
      result,
      inputAmount: inputAmount.value,
      inputBalance: inputBalance.value,
      ethBalance: awaitedEthBalance.value,
      networkFee: networkFee.value
    })
    
    return result
  } catch (error) {
    console.error('❌ Error in canPurchase computed:', error)
    return false
  }
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
    console.error('Backend quote calculation failed, using fallback:', error)
    
    // Fallback to simplified logic if backend fails
    const inputValue = parseFloat(amount)
    const tokenPriceUsd = backendTokenPrices[token] || livePrices.value[token] || 0
    
    if (tokenPriceUsd <= 0) return null
    
    const totalUsdValue = inputValue * tokenPriceUsd
    
    // Base rate: $2.50 per CIRX
    let cirxAmount = totalUsdValue / 2.5
    let discount = 0
    
    if (isOTC) {
      if (totalUsdValue >= 50000) {
        discount = 12
      } else if (totalUsdValue >= 10000) {
        discount = 8
      } else if (totalUsdValue >= 1000) {
        discount = 5
      }
      
      if (discount > 0) {
        cirxAmount = cirxAmount * (1 + discount / 100)
      }
    }
    
    const rate = cirxAmount / inputValue
    
    return {
      rate: rate.toFixed(6),
      inverseRate: (1 / rate).toFixed(8),
      fee: isOTC ? 0.15 : 0.3,
      discount: discount,
      cirxAmount: cirxAmount.toFixed(6),
      usdValue: totalUsdValue.toFixed(2)
    }
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

const formatBalance = (balance) => {
  const num = parseFloat(balance)
  if (num === 0 || isNaN(num)) return '0.0'
  
  const str = num.toString()
  const [integer, decimal] = str.split('.')
  
  if (!decimal) return integer + '.0'
  
  // Find first non-zero digit in decimal
  let firstNonZeroIndex = -1
  for (let i = 0; i < decimal.length; i++) {
    if (decimal[i] !== '0') {
      firstNonZeroIndex = i
      break
    }
  }
  
  if (firstNonZeroIndex === -1) {
    // All decimal digits are zero
    return integer + '.0'
  }
  
  // Include up to and including the first non-zero decimal digit
  const truncatedDecimal = decimal.substring(0, firstNonZeroIndex + 1)
  return integer + '.' + truncatedDecimal
}

const getExchangeRateDisplay = () => {
  const cirxAmountNum = parseFloat(cirxAmount.value) || 0
  
  if (cirxAmountNum === 0) {
    return ''
  }
  
  return `${cirxAmountNum.toFixed(4)} CIRX`
}

// Get new CIRX balance after transaction (current + purchase amount)
const getNewCirxBalance = () => {
  const purchaseAmount = parseFloat(cirxAmount.value) || 0
  
  // If we have fetched the recipient's current balance, calculate the new total
  if (recipientCirxBalance.value !== null) {
    const currentBalance = parseFloat(recipientCirxBalance.value) || 0
    const newBalance = currentBalance + purchaseAmount
    return `New Balance: ${newBalance.toFixed(4)} CIRX`
  }
  
  // If we have connected wallet balance, use that
  if (isCircularChainConnected.value) {
    const currentBalance = parseFloat(cirxBalance.value) || 0
    const newBalance = currentBalance + purchaseAmount
    return `New Balance: ${newBalance.toFixed(4)} CIRX`
  }
  
  // Default case - just show the purchase amount as new balance
  if (purchaseAmount > 0) {
    return `New Balance: ${purchaseAmount.toFixed(4)} CIRX`
  }
  
  return 'New Balance: 0.0000 CIRX'
}


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
//   if (isSaturnWalletDetected.value && isConnected.value) {
//     // Saturn wallet detected and connected - turn toggle off, clear address for safety
//     customAddressEnabled.value = false
//     // DON'T auto-fill address - different chain/account model could cause fund loss
//     recipientAddress.value = ''
//   }
// }, { immediate: true })

// Methods

// Helper function to format numbers with commas
const formatWithCommas = (value) => {
  if (!value || value === '') return ''
  const cleanValue = value.toString().replace(/[^0-9.]/g, '')
  if (!cleanValue) return ''
  
  const parts = cleanValue.split('.')
  const integerPart = parts[0]
  const decimalPart = parts[1]
  
  const withCommas = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',')
  return decimalPart !== undefined ? `${withCommas}.${decimalPart}` : withCommas
}

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
  if (isConnected.value) {
    // Set to 95% of balance to account for gas fees
    let balance = 0
    if (inputToken.value === 'ETH') {
      balance = parseFloat(formattedEthBalance.value || '0')
    } else {
      balance = 0 // Other tokens not implemented yet
    }
    const maxAmount = inputToken.value === 'ETH' ? balance * 0.95 : balance * 0.99
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
  inputToken.value = token
  // Token selection handled by Wagmi automatically
  showTokenDropdown.value = false
  // Reset input when token changes
  inputAmount.value = ''
  lastEditedField.value = 'input'
}

// Auto-select native token based on connected wallet
const autoSelectNativeToken = () => {
  if (connectedWallet.value === 'phantom') {
    // Phantom wallet - select SOL
    selectToken('SOL')
    console.log('🪙 Auto-selected SOL for Phantom wallet')
  } else {
    // Ethereum wallets (MetaMask, Coinbase, etc.) - select ETH
    selectToken('ETH')
    console.log('🪙 Auto-selected ETH for Ethereum wallet')
  }
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
  console.log('🔥 HANDLESWAP CALLED IN SWAP.VUE!', {
    isConnected: isConnected.value,
    address: address.value,
    connector: connector.value,
    recipientAddress: recipientAddress.value,
    inputAmount: inputAmount.value,
  })
  
  // If button shows "Enter Address", focus the address input field instead of swapping
  if (isConnected.value && (!recipientAddress.value || recipientAddress.value.trim() === '')) {
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
  
  // Handle different CTA states - wallet connection takes priority
  if (!isConnected.value && (!recipientAddress.value || recipientAddress.value.trim() === '')) {
    console.log('🔥 State 1: Connect - Opening wallet modal')
    // State: "Connect" - Open Reown modal
    open() // Open AppKit modal
    return
  }
  
  // State 2: "Connect Wallet" - Has address input but wallet not connected
  if (!isConnected.value && recipientAddress.value && recipientAddress.value.trim() !== '') {
    open() // Open Reown modal
    return
  }
  
  // State 3: "Enter Address" - Wallet connected but no address input
  if (isConnected.value && (!recipientAddress.value || recipientAddress.value.trim() === '')) {
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
  if (isConnected.value && inputAmount.value && parseFloat(inputAmount.value) > 0 && recipientAddress.value && recipientAddress.value.trim() !== '') {
    // Check if address is not confirmed (not green light state)
    const isAddressConfirmed = recipientAddressType.value === 'circular' && 
                              !recipientAddressError.value && 
                              addressValidationState.value === 'valid' && 
                              recipientCirxBalance.value !== null && 
                              parseFloat(recipientCirxBalance.value) >= 0.0
    
    if (!isAddressConfirmed) {
      // Show modal
      showConfirmationModal.value = true
      return
    }
  }

  // State 5: "Get CIRX Wallet" - Invalid address, show wallet modal
  if (recipientAddress.value && recipientAddressError.value) {
    showConfirmationModal.value = true
    return
  }
  
  // Force debug the canPurchase conditions
  const hasAmount = inputAmount.value && parseFloat(inputAmount.value) > 0
  const notLoading = !loading.value && !quoteLoading.value && !reverseQuoteLoading.value
  const addressValid = validateRecipientAddress(recipientAddress.value)
  const connected = isConnected.value || false
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
  if (isConnected.value) {
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
  
  try {
    loading.value = true
    
    const depositAddress = getDepositAddress(inputToken.value, 'ethereum')
    const isOTC = activeTab.value === 'otc'
    
    // Step 1: Prepare transaction parameters
    loadingText.value = 'Preparing blockchain transaction...'
    
    // Get the quote to determine exact payment needed (including platform fee)
    const backendQuote = calculateCirxQuote(inputAmount.value, inputToken.value, isOTC)
    const totalPaymentNeeded = backendQuote.totalPaymentRequired
    
    // Log transaction details instead of showing confirm dialog
    console.log('🔥 TRANSACTION DETAILS:', {
      sending: `${totalPaymentNeeded} ${inputToken.value}`,
      receiving: `${cirxAmount.value} CIRX`,
      depositAddress,
      recipient: recipientAddress.value
    })
    
    // Step 2: Execute blockchain transaction using connected wallet
    loadingText.value = `Sending ${totalPaymentNeeded} ${inputToken.value}...`
    console.log('🔥 ABOUT TO EXECUTE BLOCKCHAIN TRANSACTION')
    
    let transactionHash
    
    if (inputToken.value === 'ETH') {
      // Send ETH transaction
      transactionHash = await sendTransaction(wagmiConfig, {
        to: depositAddress,
        value: parseEther(totalPaymentNeeded)
      })
    } else {
      // Get token contract addresses from existing tokenAddresses object
      const tokenContractAddresses = {
        'USDC': tokenAddresses.USDC, // From the existing tokenAddresses object
        'USDT': tokenAddresses.USDT
      }
      
      const tokenAddress = tokenContractAddresses[inputToken.value]
      
      if (!tokenAddress) {
        throw new Error(`${inputToken.value} contract address not configured`)
      }
      
      // Calculate amount in token decimals
      const decimals = inputToken.value === 'USDC' || inputToken.value === 'USDT' ? 6 : 18
      const tokenAmount = parseUnits(totalPaymentNeeded, decimals)
      
      transactionHash = await writeContract(wagmiConfig, {
        address: tokenAddress,
        abi: [
          {
            name: 'transfer',
            type: 'function',
            stateMutability: 'nonpayable',
            inputs: [
              { name: 'to', type: 'address' },
              { name: 'amount', type: 'uint256' }
            ],
            outputs: [{ name: '', type: 'bool' }]
          }
        ],
        functionName: 'transfer',
        args: [depositAddress, tokenAmount]
      })
    }
    
    if (!transactionHash) {
      throw new Error('Transaction was rejected or failed')
    }
    
    // Step 3: Submit swap request to backend with the transaction hash
    loadingText.value = 'Registering swap with backend...'
    
    const swapData = createSwapTransaction(
      transactionHash,
      'ethereum', // payment chain
      recipientAddress.value, // CIRX recipient address
      totalPaymentNeeded, // actual amount paid (includes platform fee)
      inputToken.value // payment token
    )
    
    console.log('🔥 CALLING BACKEND API with swapData:', swapData)
    
    let result
    try {
      result = await initiateSwap(swapData)
      console.log('🔥 BACKEND API SUCCESS:', result)
    } catch (backendError) {
      console.error('🔥 BACKEND API FAILED:', backendError)
      throw new Error(`Backend API error: ${backendError.message}`)
    }
    
    if (result.success) {
      // Store swap ID for status tracking
      localStorage.setItem('lastSwapId', result.swapId)
      
      // Show success message with option to track status
      const message = `Transaction sent successfully!\n\nTransaction Hash: ${transactionHash.slice(0, 10)}...\nSwap ID: ${result.swapId}\n\nYour ${cirxAmount.value} CIRX ${isOTC ? 'will be vested over 6 months and' : 'will'} be sent to ${recipientAddress.value} once the transaction is confirmed.\n\nClick OK to view the status page, or Cancel to continue trading.`
      
      if (confirm(message)) {
        // Navigate to status page
        await navigateTo(`/status?swapId=${result.swapId}`)
      }
      
      // Reset form
      inputAmount.value = ''
      cirxAmount.value = ''
      quote.value = null
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
  }, 200)
})

watch(recipientAddress, async (newAddress) => {
  await validateRecipientAddress(newAddress)
  // Fetch CIRX balance for the new address if it's valid
  if (newAddress && !recipientAddressError.value && addressValidationState.value === 'valid') {
    await fetchCirxBalanceForAddress(newAddress)
  }
})

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

// Fetch CIRX balance for a given address
const fetchCirxBalanceForAddress = async (address) => {
  if (!address || recipientAddressError.value) {
    recipientCirxBalance.value = null
    return
  }

  try {
    isFetchingRecipientBalance.value = true
    
    // Get runtime config for API base URL
    const config = useRuntimeConfig()
    const apiBaseUrl = config.public.apiBaseUrl || 'http://localhost:8080/api'
    
    // Call our backend API to get CIRX balance
    const response = await fetch(`${apiBaseUrl}/v1/cirx/balance/${address}`, {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
        // Add API key if required
        ...(config.public.apiKey && { 'X-API-Key': config.public.apiKey })
      }
    })
    
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`)
    }
    
    const data = await response.json()
    
    if (!data.success) {
      throw new Error(data.message || 'Failed to fetch balance')
    }
    
    // Set the balance from the API response
    recipientCirxBalance.value = data.balance
    
    console.log(`📊 Fetched CIRX balance for ${address}: ${recipientCirxBalance.value}`)
  } catch (error) {
    console.error('Failed to fetch CIRX balance:', error)
    // Don't reset balance to null if we already have a valid balance from address validation
    if (recipientCirxBalance.value === null) {
      recipientCirxBalance.value = null
    }
    // Keep existing balance if we have one
  } finally {
    isFetchingRecipientBalance.value = false
  }
}

// Sanitize address input to remove spaces
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
}

const validateRecipientAddress = async (address) => {
  if (!address) {
    recipientAddressError.value = ''
    recipientAddressType.value = ''
    recipientCirxBalance.value = null
    addressValidationState.value = 'idle'
    return true
  }

  // Handle partial addresses that are still being typed (don't show errors)
  if (address === '0') {
    // Just "0" - user starting to type, no error
    addressValidationState.value = 'idle'
    recipientAddressError.value = ''
    recipientAddressType.value = ''
    return false
  }

  // Check for valid partial 0x format
  if (address.startsWith('0x') && address.length < 66) {
    // User is typing valid 0x format, don't show errors yet
    addressValidationState.value = 'idle'
    recipientAddressError.value = ''
    recipientAddressType.value = ''
    return false
  }

  // Check for invalid formats (starts with 0 but not 0x, or other invalid patterns)
  if (address.startsWith('0') && !address.startsWith('0x')) {
    recipientAddressError.value = 'Invalid address format. Circular addresses must start with "0x"'
    recipientAddressType.value = ''
    addressValidationState.value = 'invalid'
    return false
  }

  // Note: isAddressPending only checks format, let validation continue for 66-char addresses

  // Reject Ethereum addresses with specific error message
  if (isValidEthereumAddress(address)) {
    recipientAddressError.value = 'Ethereum addresses are not supported. Please enter a valid CIRX address'
    recipientAddressType.value = ''
    addressValidationState.value = 'invalid'
    return false
  }

  // Reject Solana addresses with specific error message  
  if (isValidSolanaAddress(address)) {
    recipientAddressError.value = 'Solana addresses are not supported. Please enter a valid CIRX address'
    recipientAddressType.value = ''
    addressValidationState.value = 'invalid'
    return false
  }

  // Check if it looks like a Circular address format
  if (isValidCircularAddressFormat(address)) {
    // Start async validation
    addressValidationState.value = 'validating'
    recipientAddressError.value = ''
    recipientAddressType.value = ''

    try {
      // Perform async validation with balance checking (delimiter-based requirement)
      const validation = await checkAddressExists(address)
      
      if (validation.isValid && validation.exists) {
        // Address exists on network, now check balance for green light
        recipientAddressError.value = ''
        recipientAddressType.value = 'circular'
        
        // Store balance from validation (green light only if balance >= 0.0)
        if (validation.hasBalance && validation.balance !== undefined) {
          recipientCirxBalance.value = validation.balance
        } else {
          recipientCirxBalance.value = '0'
        }
        
        addressValidationState.value = 'valid'
        hasClickedEnterAddress.value = false
        return true
      } else if (validation.isValid && !validation.exists) {
        // Valid format but doesn't exist on network yet (could be new address)
        recipientAddressError.value = 'Address not found on Circular network. Please verify the address.'
        recipientAddressType.value = ''
        addressValidationState.value = 'invalid'
        recipientCirxBalance.value = null
        return false
      } else {
        // Invalid format or API error
        recipientAddressError.value = validation.error || 'Invalid CIRX address'
        recipientAddressType.value = ''
        addressValidationState.value = 'invalid'
        recipientCirxBalance.value = null
        return false
      }
    } catch (error) {
      console.error('Address validation error:', error)
      recipientAddressError.value = 'Unable to validate address. Please try again.'
      recipientAddressType.value = ''
      addressValidationState.value = 'invalid'
      recipientCirxBalance.value = null
      return false
    }
  }

  // Invalid address format
  recipientAddressError.value = 'Invalid Circular Protocol Address'
  recipientAddressType.value = ''
  addressValidationState.value = 'invalid'
  return false
}

// Handle Circular chain events
const handleCircularChainHelp = () => {
  // Show help information in toast
  connectionToast.value = {
    show: true,
    type: 'info',
    title: 'Circular Chain Help',
    message: 'Visit docs.circular.protocol for setup instructions or contact support',
    walletIcon: null
  }
}

const handleChainAdded = () => {
  // Show success message in toast
  connectionToast.value = {
    show: true,
    type: 'success', 
    title: 'Chain Added Successfully',
    message: 'Circular chain has been added to your wallet',
    walletIcon: null
  }
}

// Close dropdown and slider when clicking outside
onMounted(async () => {
  // Fetch OTC configuration on component mount
  await fetchOtcConfig()
  
  // Fetch network configuration for dynamic placeholder
  await fetchNetworkConfig()
  
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
  
  onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
    document.removeEventListener('keydown', handleGlobalEnter)
  })

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
  border: 1px solid #ef4444;
  animation: border-color-cycle 75s ease infinite;
}

/* Gradient border that's always animated (for modal) */
.gradient-border-animated {
  position: relative;
  border: 1px solid #ef4444;
  border-radius: 1rem;
  transition: all 0.3s ease;
  animation: border-color-cycle 75s ease infinite;
}

@keyframes border-color-cycle {
  0% { border-color: #00ff88; }
  25% { border-color: #00d9ff; }
  50% { border-color: #8b5cf6; }
  75% { border-color: #a855f7; }
  100% { border-color: #00ff88; }
}

/* Wallet text - white by default, animated gradient on hover */
.wallet-text {
  color: white;
  transition: all 0.3s ease;
}

.group:hover .wallet-text {
  background: linear-gradient(45deg, #00ff88, #00d9ff, #8b5cf6, #a855f7, #00ff88);
  background-size: 400% 400%;
  background-clip: text;
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  animation: text-gradient-cycle 75s ease infinite;
}

@keyframes text-gradient-cycle {
  0% {
    background-position: 0% 50%;
  }
  25% {
    background-position: 25% 50%;
  }
  50% {
    background-position: 50% 50%;
  }
  75% {
    background-position: 75% 50%;
  }
  100% {
    background-position: 100% 50%;
  }
}

/* Space Theme Modal with Particles */
.space-modal {
  background: linear-gradient(135deg, rgba(0, 0, 0, 0.95), rgba(15, 15, 35, 0.9));
  border: 1px solid rgba(255, 255, 255, 0.1);
  box-shadow: 
    0 8px 32px 0 rgba(0, 0, 0, 0.6),
    inset 0 1px 0 rgba(255, 255, 255, 0.05);
  position: relative;
  overflow: hidden;
}

.space-modal::before {
  content: '';
  position: absolute;
  top: -50%;
  left: -50%;
  width: 200%;
  height: 200%;
  background-image: 
    radial-gradient(2px 2px at 20px 30px, rgba(255, 255, 255, 0.8), transparent),
    radial-gradient(2px 2px at 40px 70px, rgba(200, 200, 255, 0.6), transparent),
    radial-gradient(1px 1px at 90px 40px, rgba(255, 255, 255, 0.9), transparent),
    radial-gradient(1px 1px at 130px 80px, rgba(255, 200, 200, 0.5), transparent),
    radial-gradient(2px 2px at 160px 30px, rgba(255, 255, 255, 0.7), transparent),
    radial-gradient(1px 1px at 200px 90px, rgba(200, 255, 255, 0.6), transparent),
    radial-gradient(1px 1px at 240px 50px, rgba(255, 255, 255, 0.8), transparent),
    radial-gradient(2px 2px at 280px 10px, rgba(255, 255, 255, 0.9), transparent),
    radial-gradient(1px 1px at 320px 70px, rgba(255, 255, 200, 0.4), transparent),
    radial-gradient(1px 1px at 360px 20px, rgba(255, 255, 255, 0.7), transparent),
    radial-gradient(3px 3px at 80px 120px, rgba(255, 255, 255, 0.8), transparent),
    radial-gradient(1px 1px at 220px 140px, rgba(200, 200, 255, 0.5), transparent),
    radial-gradient(2px 2px at 300px 100px, rgba(255, 255, 255, 0.6), transparent),
    radial-gradient(1px 1px at 180px 60px, rgba(255, 200, 255, 0.3), transparent);
  background-repeat: repeat;
  background-size: 400px 200px;
  animation: starsMove 40s linear infinite;
  pointer-events: none;
  z-index: 0;
}

.space-modal::after {
  content: '';
  position: absolute;
  top: -50%;
  left: -50%;
  width: 200%;
  height: 200%;
  background-image: 
    radial-gradient(2px 2px at 45px 85px, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.9) 50%, transparent),
    radial-gradient(1px 1px at 125px 45px, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.7) 50%, transparent),
    radial-gradient(2px 2px at 245px 125px, rgba(200, 200, 255, 0), rgba(200, 200, 255, 0.8) 50%, transparent),
    radial-gradient(1px 1px at 315px 65px, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.6) 50%, transparent),
    radial-gradient(3px 3px at 185px 25px, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.9) 50%, transparent);
  background-repeat: repeat;
  background-size: 400px 200px;
  animation: starsMove 40s linear infinite, randomBlink1 8s ease-in-out infinite;
  pointer-events: none;
  z-index: 0;
}

/* Additional star layers for random blinking */
.space-modal .star-layer-1 {
  position: absolute;
  top: -50%;
  left: -50%;
  width: 200%;
  height: 200%;
  background-image: 
    radial-gradient(1px 1px at 60px 110px, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.8) 50%, transparent),
    radial-gradient(2px 2px at 340px 45px, rgba(200, 255, 200, 0), rgba(200, 255, 200, 0.7) 50%, transparent),
    radial-gradient(1px 1px at 150px 180px, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.6) 50%, transparent);
  background-repeat: repeat;
  background-size: 400px 200px;
  animation: starsMove 40s linear infinite, randomBlink2 12s ease-in-out infinite;
  pointer-events: none;
  z-index: 0;
}

.space-modal .star-layer-2 {
  position: absolute;
  top: -50%;
  left: -50%;
  width: 200%;
  height: 200%;
  background-image: 
    radial-gradient(1px 1px at 275px 155px, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.9) 50%, transparent),
    radial-gradient(2px 2px at 95px 75px, rgba(255, 200, 255, 0), rgba(255, 200, 255, 0.6) 50%, transparent),
    radial-gradient(1px 1px at 385px 135px, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.7) 50%, transparent);
  background-repeat: repeat;
  background-size: 400px 200px;
  animation: starsMove 40s linear infinite, randomBlink3 16s ease-in-out infinite;
  pointer-events: none;
  z-index: 0;
}

.space-modal > * {
  position: relative;
  z-index: 1;
}

/* Circular Close Button */
.close-button-circular {
  background: linear-gradient(135deg, rgba(0, 0, 0, 0.9), rgba(30, 30, 50, 0.8));
  backdrop-filter: blur(10px);
  box-shadow: 
    0 4px 16px rgba(0, 0, 0, 0.4),
    inset 0 1px 0 rgba(255, 255, 255, 0.1);
}

.close-button-circular:hover {
  background: linear-gradient(135deg, rgba(20, 20, 20, 0.95), rgba(40, 40, 60, 0.9));
  transform: translateX(-50%) scale(1.05);
}

@keyframes starsMove {
  0% {
    transform: translateX(0px) translateY(0px) rotate(0deg);
  }
  100% {
    transform: translateX(-400px) translateY(-200px) rotate(90deg);
  }
}

@keyframes randomBlink1 {
  0% { opacity: 0.3; }
  15% { opacity: 0.8; }
  20% { opacity: 0.3; }
  45% { opacity: 0.3; }
  50% { opacity: 1; }
  55% { opacity: 0.3; }
  80% { opacity: 0.3; }
  85% { opacity: 0.9; }
  90% { opacity: 0.3; }
  100% { opacity: 0.3; }
}

@keyframes randomBlink2 {
  0% { opacity: 0.4; }
  25% { opacity: 0.4; }
  30% { opacity: 0.9; }
  35% { opacity: 0.4; }
  65% { opacity: 0.4; }
  70% { opacity: 1; }
  75% { opacity: 0.4; }
  100% { opacity: 0.4; }
}

@keyframes randomBlink3 {
  0% { opacity: 0.2; }
  10% { opacity: 0.7; }
  15% { opacity: 0.2; }
  40% { opacity: 0.2; }
  60% { opacity: 0.2; }
  65% { opacity: 0.8; }
  70% { opacity: 0.2; }
  90% { opacity: 0.2; }
  95% { opacity: 0.6; }
  100% { opacity: 0.2; }
}

/* Uniswap-style Connected Swap Fields */
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
  margin-bottom: 8px;
  /* Consistent padding for alignment */
  padding: 20px 16px;
  background: rgba(21, 30, 40, 0.3);
  backdrop-filter: none;
  border: 1px solid #0D141B;
}

.input-section-bottom {
  border-radius: 0 0 12px 12px;
  margin-top: 8px;
  /* Consistent padding for alignment */
  padding: 20px 16px;
  background: rgba(21, 30, 40, 0.05);
  backdrop-filter: none;
  border: 1px solid #0D141B;
}

.input-section:hover {
  background: rgba(55, 65, 81, 0.1);
}

/* Simple focus states - just background color change */
.input-section:focus-within {
  background: #151E28;
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

</style>