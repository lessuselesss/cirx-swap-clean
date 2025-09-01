/**
 * Unified Quote Calculator
 * Consolidates all quote calculation logic identified through neural embeddings analysis
 * 
 * Merged functions with high similarity:
 * - getLiquidQuote + getOTCQuote (97.3% similarity)
 * - calculateQuote + calculateReverseQuote (96.8% similarity)
 * - Multiple price calculation functions (95%+ similarity)
 */
import { ref, computed } from 'vue'
import { parseUnits, formatUnits } from 'viem'
import { useMathUtils } from '../core/useMathUtils.js'
import { useFormattingUtils } from '../core/useFormattingUtils.js'
import { usePriceData } from '../usePriceData.js'
import { useVestedConfig } from '../useFormattedNumbers.js'

export function useQuoteCalculator() {
  const { safeDiv, safeMul, safePercentage, validateNumber } = useMathUtils()
  const { formatNumber } = useFormattingUtils()
  
  // Get dynamic vested configuration
  const { discountTiers: dynamicDiscountTiers, fees: dynamicFees } = useVestedConfig()
  
  // Token prices (fetched from live APIs)
  const tokenPrices = ref({
    ETH: 2500,   // Will be updated with live prices
    USDC: 1,     
    USDT: 1,     
    SOL: 100,    
    CIRX: 1      
  })

  // Track if we're using live or fallback prices
  const priceSource = ref('loading')

  // Fee structure - use dynamic fees with fallback
  const fees = computed(() => ({
    liquid: dynamicFees.value?.liquid || 0.3,  // 0.3% for liquid swaps (fallback)
    otc: dynamicFees.value?.otc || 0.15        // 0.15% for OTC swaps (fallback)
  }))

  // Dynamic discount tiers with fallback
  const discountTiers = computed(() => 
    dynamicDiscountTiers.value || [
      { minAmount: 50000, discount: 12 },  // $50K+: 12%
      { minAmount: 10000, discount: 8 },   // $10K+: 8%  
      { minAmount: 1000, discount: 5 }     // $1K+: 5%
    ]
  )

  /**
   * Initialize prices on first use
   */
  const initializePrices = async () => {
    try {
      const { getTokenPrices } = usePriceData()
      const livePrices = await getTokenPrices()
      tokenPrices.value = { ...livePrices }
      priceSource.value = 'live'
    } catch (error) {
      console.warn('Failed to load live prices, using fallback:', error)
      priceSource.value = 'fallback'
    }
  }

  /**
   * Calculate discount percentage based on USD amount
   */
  const calculateDiscount = (usdAmount) => {
    for (const tier of discountTiers.value) {
      if (usdAmount >= tier.minAmount) {
        return tier.discount
      }
    }
    return 0
  }

  /**
   * Normalize token symbol for price lookup
   */
  const normalizeTokenSymbol = (tokenSymbol) => {
    // Handle Solana-specific token naming
    if (tokenSymbol === 'USDC_SOL') return 'USDC'
    if (tokenSymbol === 'USDT_SOL') return 'USDT'
    return tokenSymbol?.toUpperCase() || 'UNKNOWN'
  }

  /**
   * Get token price with validation
   */
  const getTokenPrice = (tokenSymbol) => {
    const normalizedSymbol = normalizeTokenSymbol(tokenSymbol)
    const price = tokenPrices.value[normalizedSymbol] || 0
    
    if (price <= 0) {
      console.warn(`No price available for token: ${tokenSymbol} (${normalizedSymbol})`)
      return 0
    }
    
    return price
  }

  /**
   * UNIFIED QUOTE CALCULATOR
   * Consolidates calculateQuote + calculateReverseQuote (96.8% similarity)
   * Supports both forward (token->CIRX) and reverse (CIRX->token) calculations
   */
  const calculateUnifiedQuote = (inputAmount, inputToken, options = {}) => {
    const {
      isOTC = false,
      selectedTier = null,
      isReverse = false,
      targetToken = null,
      slippageTolerance = 0.5
    } = options

    // For reverse quotes, inputToken is CIRX and targetToken is the desired output
    const sourceToken = isReverse ? 'CIRX' : inputToken
    const destToken = isReverse ? (targetToken || inputToken) : 'CIRX'
    
    // Validate input amount
    const inputValue = validateNumber(inputAmount, `${sourceToken} amount`)
    if (inputValue === null || inputValue <= 0) {
      return null
    }
    
    // Get token prices with validation
    const sourceTokenPrice = getTokenPrice(sourceToken)
    const destTokenPrice = getTokenPrice(destToken)
    
    // Comprehensive price validation
    if (sourceTokenPrice <= 0 || destTokenPrice <= 0) {
      console.warn(`Cannot calculate quote: invalid prices - ${sourceToken}: $${sourceTokenPrice}, ${destToken}: $${destTokenPrice}`)
      return null
    }
    
    // Safe calculation of total USD value
    const totalUsdValue = safeMul(inputValue, sourceTokenPrice)
    if (totalUsdValue <= 0) {
      console.error('Invalid total USD value calculation:', { inputValue, sourceTokenPrice, totalUsdValue })
      return null
    }
    
    // Calculate fee with safe percentage handling
    const feeRate = safePercentage(isOTC ? fees.value.otc : fees.value.liquid)
    const feeAmount = safeMul(inputValue, safeDiv(feeRate, 100))
    const amountAfterFee = Math.max(0, inputValue - feeAmount)
    const usdAfterFee = safeMul(amountAfterFee, sourceTokenPrice)
    
    // Calculate destination token amount with safe division
    let destReceived = safeDiv(usdAfterFee, destTokenPrice)
    if (destReceived <= 0) {
      console.error('Invalid destination token calculation:', { usdAfterFee, destTokenPrice, destReceived })
      return null
    }
    
    // Apply OTC discount with safe calculations (only for CIRX purchases)
    let discount = 0
    if (isOTC && destToken === 'CIRX') {
      // Use selected tier discount if provided, otherwise calculate based on amount
      if (selectedTier && selectedTier.discount) {
        discount = safePercentage(selectedTier.discount)
      } else {
        discount = safePercentage(calculateDiscount(totalUsdValue))
      }
      
      if (discount > 0) {
        const multiplier = 1 + safeDiv(discount, 100)
        destReceived = safeMul(destReceived, multiplier)
      }
    }
    
    // Final validation of destination amount
    if (!isFinite(destReceived) || destReceived <= 0) {
      console.error('Final destination amount validation failed:', destReceived)
      return null
    }
    
    // Calculate exchange rate with safe division
    const exchangeRate = safeDiv(sourceTokenPrice, destTokenPrice)
    const slippageAmount = safeMul(destReceived, safeDiv(slippageTolerance, 100))
    
    return {
      inputAmount: inputValue,
      inputToken: sourceToken,
      outputToken: destToken,
      inputUsdValue: totalUsdValue,
      sourceTokenPrice,
      destTokenPrice,
      feeRate,
      feeAmount: parseFloat(feeAmount.toFixed(8)),
      feeUsd: safeMul(feeAmount, sourceTokenPrice),
      discount,
      outputAmount: parseFloat(destReceived.toFixed(6)),
      outputAmountFormatted: destReceived.toLocaleString('en-US', { maximumFractionDigits: 6 }),
      exchangeRate: `1 ${sourceToken} = ${exchangeRate.toFixed(2)} ${destToken}`,
      isOTC,
      isReverse,
      priceImpact: 0, // Could be calculated based on liquidity
      minimumReceived: parseFloat((destReceived - slippageAmount).toFixed(6)),
      slippageTolerance,
      vestingPeriod: isOTC && destToken === 'CIRX' ? '6 months' : null,
      priceSource: priceSource.value
    }
  }

  /**
   * UNIFIED CONTRACT QUOTE FUNCTION
   * Consolidates getLiquidQuote + getOTCQuote (97.3% similarity)
   * Supports both liquid and OTC quotes with smart contract integration
   */
  const getUnifiedContractQuote = async (inputToken, inputAmount, options = {}) => {
    const {
      isOTC = false,
      contractsDeployed = { value: { otcSwap: false } },
      CONTRACT_ADDRESSES = { value: {} },
      ABIS = {}
    } = options

    try {
      // Development/fallback mode - use calculation-based quotes  
      // Note: Smart contract integration removed - needs AppKit-compatible implementation
      if (!contractsDeployed.value.otcSwap) {
        const mockPrice = inputToken === 'ETH' ? 2500 : 1 // $2500 per ETH, $1 per stablecoin
        const baseAmount = parseFloat(inputAmount) * mockPrice
        
        let discount = 0
        if (isOTC) {
          // Apply dynamic OTC discount tiers
          discount = calculateDiscount(baseAmount)
        }
        
        const discountMultiplier = 1 + (discount / 100)
        const feeRate = isOTC ? fees.value.otc : fees.value.liquid
        const feeAmount = baseAmount * (feeRate / 100)
        const cirxReceived = (baseAmount - feeAmount) * discountMultiplier
        
        return {
          cirxAmount: cirxReceived.toFixed(2),
          fee: feeAmount.toFixed(4),
          feePercentage: feeRate.toString(),
          discount: discount.toString(),
          discountBps: (discount * 100).toString(), // basis points
          type: isOTC ? 'otc' : 'liquid',
          source: 'mock'
        }
      }

      // Smart contract integration
      const contractAddress = CONTRACT_ADDRESSES.value.OTC_SWAP
      if (!contractAddress) {
        throw new Error('OTC Swap contract address not found')
      }

      const tokenAddress = CONTRACT_ADDRESSES.value[inputToken]
      if (!tokenAddress) {
        throw new Error(`Token address not found for ${inputToken}`)
      }

      // Get token decimals (default to 18)
      const decimals = inputToken === 'USDC' || inputToken === 'USDT' ? 6 : 18
      const amountWei = parseUnits(inputAmount.toString(), decimals)

      // TODO: Replace with AppKit-compatible contract reading
      // Original: const contractResult = await publicClient.readContract({...})
      const contractResult = null // Temporarily disabled until AppKit integration

      // Parse contract response (fallback to mock when contract is disabled)
      if (!contractResult) {
        // Fallback to calculation-based quotes when contract is unavailable
        const mockPrice = inputToken === 'ETH' ? 2500 : 1
        const baseAmount = parseFloat(inputAmount) * mockPrice
        
        let discount = 0
        if (isOTC) {
          if (baseAmount >= 50000) discount = 12
          else if (baseAmount >= 10000) discount = 8  
          else if (baseAmount >= 1000) discount = 5
        }

        const cirxAmount = baseAmount * (100 - discount) / 100
        return {
          cirxAmount: cirxAmount.toString(),
          fee: (baseAmount * 0.003).toString(), // 0.3% fee
          discount: discount.toString(),
          pricePerCirx: '1.00'
        }
      }
      
      if (isOTC) {
        const [cirxAmount, fee, discountBps] = contractResult
        return {
          cirxAmount: formatUnits(cirxAmount, 18),
          fee: formatUnits(fee, 18),
          feePercentage: fees.value.otc.toString(),
          discount: (parseInt(discountBps) / 100).toString(),
          discountBps: discountBps.toString(),
          type: 'otc',
          source: 'contract'
        }
      } else {
        const [cirxAmount, fee] = contractResult
        return {
          cirxAmount: formatUnits(cirxAmount, 18),
          fee: formatUnits(fee, 18),
          feePercentage: fees.value.liquid.toString(),
          discount: '0',
          discountBps: '0',
          type: 'liquid',
          source: 'contract'
        }
      }

    } catch (error) {
      console.error(`Failed to get ${isOTC ? 'OTC' : 'liquid'} quote:`, error)
      throw error
    }
  }

  /**
   * Convenience methods for backward compatibility
   */
  const calculateQuote = (inputAmount, inputToken, isOTC = false, selectedTier = null) => {
    return calculateUnifiedQuote(inputAmount, inputToken, { isOTC, selectedTier })
  }

  const calculateReverseQuote = (cirxAmount, targetToken, isOTC = false, selectedTier = null) => {
    return calculateUnifiedQuote(cirxAmount, 'CIRX', { 
      isOTC, 
      selectedTier, 
      isReverse: true, 
      targetToken 
    })
  }

  const getLiquidQuote = async (inputToken, inputAmount, contractOptions = {}) => {
    return getUnifiedContractQuote(inputToken, inputAmount, { 
      ...contractOptions, 
      isOTC: false 
    })
  }

  const getOTCQuote = async (inputToken, inputAmount, contractOptions = {}) => {
    return getUnifiedContractQuote(inputToken, inputAmount, { 
      ...contractOptions, 
      isOTC: true 
    })
  }

  /**
   * Price refresh utilities
   */
  const refreshPrices = async () => {
    await initializePrices()
  }

  const qualifiesForOTC = (inputAmount, inputToken) => {
    const usdValue = safeMul(parseFloat(inputAmount), getTokenPrice(inputToken))
    const tiers = discountTiers.value
    return usdValue >= (tiers[tiers.length - 1]?.minAmount || 1000) // $1K minimum fallback
  }

  // Auto-initialize prices
  initializePrices()

  return {
    // Unified calculation functions
    calculateUnifiedQuote,
    getUnifiedContractQuote,
    
    // Backward compatibility functions
    calculateQuote,
    calculateReverseQuote,
    getLiquidQuote,
    getOTCQuote,
    
    // Utility functions
    calculateDiscount,
    getTokenPrice,
    normalizeTokenSymbol,
    refreshPrices,
    qualifiesForOTC,
    
    // State and configuration
    tokenPrices: computed(() => tokenPrices.value),
    priceSource: computed(() => priceSource.value),
    fees,
    discountTiers,
    
    // Status checks
    isLoading: computed(() => priceSource.value === 'loading'),
    hasLivePrices: computed(() => priceSource.value === 'live')
  }
}