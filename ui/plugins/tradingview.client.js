/**
 * TradingView Charting Library Plugin for Nuxt
 * Loads the full TradingView Charting Library from CDN
 */
export default defineNuxtPlugin(async () => {
  // Only load on client side
  if (typeof window === 'undefined') return

  // Check if TradingView is already loaded
  if (window.TradingView && window.Datafeeds) {
    console.log('✅ TradingView Charting Library already loaded')
    return
  }

  try {
    console.log('🔄 Loading TradingView Charting Library...')
    
    // Load the main charting library
    await loadScript('https://charting-library.tradingview-widget.com/charting_library/charting_library.standalone.js')
    
    // Load the UDF compatible datafeed
    await loadScript('https://charting-library.tradingview-widget.com/datafeeds/udf/dist/bundle.js')
    
    console.log('✅ TradingView Charting Library loaded successfully')
    
  } catch (error) {
    console.error('❌ Failed to load TradingView Charting Library:', error)
    throw error
  }
})

/**
 * Helper function to load scripts dynamically
 */
function loadScript(src) {
  return new Promise((resolve, reject) => {
    // Check if script is already loaded
    if (document.querySelector(`script[src="${src}"]`)) {
      resolve()
      return
    }

    const script = document.createElement('script')
    script.type = 'text/javascript'
    script.src = src
    script.async = true
    
    script.onload = () => {
      console.log(`✅ Loaded: ${src}`)
      resolve()
    }
    
    script.onerror = (error) => {
      console.error(`❌ Failed to load: ${src}`, error)
      reject(new Error(`Failed to load script: ${src}`))
    }
    
    document.head.appendChild(script)
  })
}