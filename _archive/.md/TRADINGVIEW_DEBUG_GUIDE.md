# TradingView Chart Debug Guide

## Quick Debug Checklist

Run these steps in your browser's developer console to identify the issue:

### 1. Check Library Loading
```javascript
console.log('TradingView loaded:', !!window.TradingView)
console.log('Datafeeds loaded:', !!window.Datafeeds)
```

### 2. Check Container Element
```javascript
const containerId = document.querySelector('[id^="tv_chart_container_"]')?.id
console.log('Container ID:', containerId)
console.log('Container element:', document.getElementById(containerId))
```

### 3. Check Console Logs
Filter console messages for these patterns:
- `[CIRX Datafeed]` - Datafeed operations
- `TradingView` - Library loading/errors
- `Chart initialization` - Widget creation

### 4. Network Tab Verification
Check these requests succeed (200 status):
- `https://charting-library.tradingview-widget.com/charting_library/charting_library.standalone.js`
- `https://charting-library.tradingview-widget.com/datafeeds/udf/dist/bundle.js`

## Systematic Debugging Steps

### Step 1: Library Loading Issues

**Expected Console Messages:**
```
🔄 Loading TradingView Charting Library...
✅ Loaded: https://charting-library.tradingview-widget.com/charting_library/charting_library.standalone.js
✅ Loaded: https://charting-library.tradingview-widget.com/datafeeds/udf/dist/bundle.js
✅ TradingView Charting Library loaded successfully
```

**If Missing:**
1. Check network connectivity to TradingView CDN
2. Look for CORS or CSP (Content Security Policy) errors
3. Verify no ad blockers are interfering

### Step 2: Container Element Issues

**Run in Console:**
```javascript
// Find the chart container
const containers = document.querySelectorAll('[id^="tv_chart_container_"]')
console.log('Found containers:', containers.length)
containers.forEach((el, i) => {
  console.log(`Container ${i}:`, {
    id: el.id,
    visible: el.offsetWidth > 0 && el.offsetHeight > 0,
    dimensions: { width: el.offsetWidth, height: el.offsetHeight },
    computed: window.getComputedStyle(el).display
  })
})
```

### Step 3: Datafeed Debugging

**Expected Console Flow:**
```
[CIRX Datafeed]: onReady called
🚀 Initializing TradingView chart with options: {...}
[CIRX Datafeed]: resolveSymbol called CIRX/USD
[CIRX Datafeed]: getBars called {...}
✅ TradingView chart ready
```

**If getBars is not called:**
```javascript
// Check if symbol is recognized
const { createCIRXDatafeed } = useTradingViewDatafeed()
const datafeed = createCIRXDatafeed()
datafeed.resolveSymbol('CIRX/USD', 
  (symbolInfo) => console.log('✅ Symbol resolved:', symbolInfo),
  (error) => console.log('❌ Symbol error:', error)
)
```

### Step 4: Mock Data Verification

**Check Generated Data:**
```javascript
// Add this temporarily to useTradingViewDatafeed.js in getBars method
console.log('Generated bars sample:', bars.slice(0, 3))
console.log('Bars count:', bars.length)
console.log('Date range:', {
  first: new Date(bars[0]?.time * 1000),
  last: new Date(bars[bars.length - 1]?.time * 1000)
})
```

### Step 5: Widget Options Verification

**Check the logged options in console:**
```javascript
// Look for this log message:
// 🚀 Initializing TradingView chart with options: {...}
```

**Common Issues:**
- `container` ID doesn't match actual DOM element
- `datafeed` is undefined or not implementing required methods
- Invalid `symbol` or `interval` values

## Common Issues & Solutions

### Issue 1: "Chart Loading Failed" Error

**Symptoms:** Error message displayed in chart area
**Causes:**
1. TradingView library failed to load
2. Container element not found
3. Datafeed errors

**Solution:**
```javascript
// Check what error is being thrown
const chartComponent = document.querySelector('.tradingview-chart-wrapper')
const vueInstance = chartComponent.__vueParentComponent
console.log('Chart error:', vueInstance?.data?.error)
```

### Issue 2: Blank Chart Area

**Symptoms:** Loading spinner never goes away, no error shown
**Causes:**
1. `getBars` returning empty data
2. `onChartReady` never called
3. Widget initialization hanging

**Solution:**
Check if `onChartReady` callback is reached:
```javascript
// Look for this in console:
// ✅ TradingView chart ready
```

### Issue 3: Chart Container Not Found

**Symptoms:** "Chart container not available" in console
**Causes:**
1. Vue component not properly mounted
2. Container ref not established

**Solution:**
```javascript
// Force chart re-initialization
const chart = document.querySelector('.tradingview-chart-wrapper')
if (chart && chart.__vueParentComponent) {
  chart.__vueParentComponent.exposed.refresh()
}
```

## Testing Mock Data

**Verify datafeed returns valid bars:**
```javascript
import { createCIRXDatafeed } from '~/composables/useTradingViewDatafeed'

const datafeed = createCIRXDatafeed()
const mockSymbol = { name: 'CIRX/USD' }
const mockParams = {
  from: Math.floor(Date.now() / 1000) - (30 * 24 * 60 * 60), // 30 days ago
  to: Math.floor(Date.now() / 1000),
  countBack: 100
}

datafeed.getBars(
  mockSymbol,
  '1D',
  mockParams,
  (bars, meta) => {
    console.log('✅ Mock bars generated:', bars.length)
    console.log('Sample bars:', bars.slice(0, 3))
    console.log('Meta:', meta)
  },
  (error) => console.log('❌ getBars error:', error)
)
```

## Advanced Debugging

### Memory and Performance
```javascript
// Check for memory leaks
console.log('Active subscriptions:', window.tradingViewSubscriptions?.size || 0)
console.log('Active intervals:', Object.keys(window.tradingViewIntervals || {}).length)
```

### Widget State Inspection
```javascript
// If chart widget exists
const containers = document.querySelectorAll('[id^="tv_chart_container_"]')
containers.forEach(container => {
  const iframe = container.querySelector('iframe')
  if (iframe) {
    console.log('Chart iframe found:', {
      src: iframe.src,
      loaded: iframe.contentDocument !== null
    })
  }
})
```

## Expected Working Flow

1. ✅ Page loads, TradingView plugin runs
2. ✅ Scripts load from CDN
3. ✅ Chart component mounts
4. ✅ Container element created with unique ID
5. ✅ Datafeed created and `onReady` called
6. ✅ Widget initialized with proper options
7. ✅ `resolveSymbol` called for CIRX/USD
8. ✅ `getBars` called with date range
9. ✅ Mock data generated and returned
10. ✅ `onChartReady` callback fired
11. ✅ Chart renders with candlestick data

## Quick Fix Commands

**Force library reload:**
```javascript
delete window.TradingView
delete window.Datafeeds
location.reload()
```

**Manually trigger chart initialization:**
```javascript
const chartEl = document.querySelector('.tradingview-chart-wrapper')
chartEl?.__vueParentComponent?.exposed?.refresh()
```

**Clear all subscriptions:**
```javascript
window.tradingViewSubscriptions?.clear()
Object.values(window.tradingViewIntervals || {}).forEach(clearInterval)
window.tradingViewIntervals = {}
```