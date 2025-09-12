// Price management and refresh composable
import { ref } from 'vue'
import { usePriceData } from './usePriceData'
import { useFormattingUtils } from './core/useFormattingUtils.js'

export function iusePriceManager() {
  const { formatWithCommas } = useFormattingUtils()
  const livePrices = ref({ ETH: 2500, USDC: 1, USDT: 1, CIRX: 1 })
  const isPriceRefreshing = ref(false)
  
  const refreshPrices = async (inputAmount, inputToken, activeTab, lastEditedField, quote, cirxAmount, calculateQuoteAsync) => {
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
    }
  }
  
  const startChartPreloading = async () => {
    let chartPreloadStarted = false
    if (chartPreloadStarted) return
    chartPreloadStarted = true
    
    console.log('🚀 Starting background chart data preload after swap page loaded...')
    
    try {
      // Chart data preloading now handled by unified price service in usePriceData composable
      // Start background price updates which will also warm up the chart data cache
      const { startPriceUpdates } = usePriceData()
      startPriceUpdates()
      
      // Additional TradingView chart preloading (if needed) - 1 second delay to ensure swap page is fully rendered
      setTimeout(() => {
        console.log('📊 Chart data cache warmed up via unified price service')
      }, 1000)
      
    } catch (error) {
      console.warn('Chart preloading initialization failed:', error)
    }
  }
  
  return {
    livePrices,
    isPriceRefreshing,
    refreshPrices,
    startChartPreloading
  }
}