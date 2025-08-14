# Manual Testing Guide - Wallet Connection Debug

## Quick Test Commands

**Run these in your browser console at http://localhost:3001:**

### 1. Environment Check
```javascript
// Check if we're in the right environment
console.log('URL:', window.location.href)
console.log('User Agent:', navigator.userAgent)
console.log('Window Ethereum:', !!window.ethereum)
console.log('Window Solana:', !!window.solana)
```

### 2. Wallet Provider Detection
```javascript
// MetaMask detection
console.log('MetaMask installed:', !!(window.ethereum && window.ethereum.isMetaMask))
console.log('Ethereum providers:', window.ethereum?.providers?.map(p => p.constructor.name) || 'None')

// Phantom detection  
console.log('Phantom installed:', !!(window.solana && window.solana.isPhantom))
```

### 3. Vue/Nuxt App State
```javascript
// Check if Vue app is mounted
console.log('Vue app:', !!document.querySelector('#__nuxt'))
console.log('Nuxt instance:', !!window.$nuxt)

// Check if Pinia store exists
console.log('Pinia stores:', window.$nuxt?.$pinia?._s?.size || 'Not found')
```

### 4. Component Registration Check
```javascript
// Check if components are registered
const appInstance = document.querySelector('#__nuxt').__vueParentComponent
console.log('App components:', Object.keys(appInstance?.appContext?.components || {}))
```

### 5. Wallet Store Debug
```javascript
// If store is accessible
if (window.$nuxt?.$pinia) {
  const stores = Array.from(window.$nuxt.$pinia._s.keys())
  console.log('Available stores:', stores)
  
  // Check wallet store specifically
  const walletStore = window.$nuxt.$pinia._s.get('wallet')
  if (walletStore) {
    console.log('Wallet store state:', {
      isConnected: walletStore.isConnected,
      isInitialized: walletStore.isInitialized,
      activeWallet: walletStore.activeWallet,
      currentError: walletStore.currentError
    })
  }
}
```

### 6. Event Listener Test
```javascript
// Test wallet connection manually
if (window.ethereum) {
  window.ethereum.request({ method: 'eth_accounts' })
    .then(accounts => console.log('MetaMask accounts:', accounts))
    .catch(error => console.error('MetaMask error:', error))
}

if (window.solana) {
  window.solana.connect({ onlyIfTrusted: true })
    .then(response => console.log('Phantom response:', response))
    .catch(error => console.error('Phantom error:', error))
}
```

## Test Scenarios

### Scenario 1: Page Load
1. Open http://localhost:3001
2. Check console immediately for errors
3. Wait for cookie consent modal to appear
4. Accept consent and proceed to swap page

### Scenario 2: Connect Wallet Button
1. Click "Connect Wallet" button
2. Check console for click handler errors
3. Verify modal opens
4. Check for wallet detection errors

### Scenario 3: Wallet Selection
1. Click on MetaMask option (if installed)
2. Check console for connection attempts  
3. Look for any promise rejections
4. Verify success/error handling

### Scenario 4: Network Issues
1. Open Network tab in DevTools
2. Reload page and check for failed requests
3. Look for 404s on assets
4. Check for CORS errors

## Common Error Patterns to Look For

```javascript
// JavaScript errors
"Cannot read properties of undefined"
"ReferenceError: X is not defined" 
"TypeError: X is not a function"

// Vue/Nuxt errors
"Failed to resolve component"
"Hydration mismatch"
"Cannot access before initialization"

// Wallet errors
"User rejected the request"
"MetaMask not found"
"No Ethereum provider found"

// Network errors
"Failed to fetch"
"CORS error"
"404 Not Found"
```

## Success Indicators

**Page Load Success:**
- Cookie consent modal appears
- No console errors
- All assets load successfully

**Wallet Detection Success:**  
- MetaMask shown as "Available" (if installed)
- Phantom shown as "Available" (if installed)
- Console shows wallet provider detection

**Connection Success:**
- Modal closes after selection
- Wallet address appears in header
- No error toasts displayed

## Reporting Issues

If you find errors, please capture:
1. Full error message from console
2. Stack trace (click arrow to expand)
3. Steps to reproduce
4. Browser type and version
5. Wallet extensions installed

## Quick Fixes to Try

**If components not loading:**
```bash
cd ui && rm -rf .nuxt && npm run dev
```

**If wallet detection fails:**
```javascript
// In console - reload after clearing
localStorage.clear()
sessionStorage.clear()  
location.reload()
```

**If store errors:**
```javascript
// Check for store initialization issues
window.$nuxt.$pinia._s.clear()
location.reload()
```