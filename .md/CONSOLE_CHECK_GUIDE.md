# Console Debugging Guide - CIRX Swap App

## Current Status

**Development Server**: Running on http://localhost:3001  
**Framework**: Nuxt.js 3 with SSR disabled  
**Wallet Integration**: MetaMask + Phantom support

## Debugging Steps Performed

### 1. Automated Browser Tools
- ❌ Playwright: Browser installation issues on NixOS
- ❌ Puppeteer: Chrome executable issues in current environment

### 2. Static Analysis Completed
- ✅ All Vue components are properly structured
- ✅ Import statements are correct
- ✅ Store configuration looks good
- ✅ No obvious syntax errors in files

### 3. Component Structure Analysis

**Key Components Verified:**
- `MultiWalletButton.vue` - Main wallet connection UI ✅
- `ToastNotifications.vue` - Error/success notifications ✅  
- `CookieConsent.vue` - Initial consent modal ✅
- App.vue - Global error handling ✅

**Store Configuration:**
- Pinia store properly configured
- MetaMask composable initialized
- Defensive programming patterns in place

## Manual Testing Required

Since automated browser tools aren't working, please test manually:

### Step 1: Open Browser Console
1. Navigate to http://localhost:3001
2. Open Developer Tools (F12)
3. Go to Console tab

### Step 2: Look for These Error Patterns
```javascript
// Common wallet connection errors
"Cannot read properties of undefined (reading 'some')"
"connectors is undefined"
"MetaMask not found"
"useAccount is not defined"
"Hydration mismatch"

// Component errors
"Failed to resolve component"
"Cannot access before initialization"
"ReferenceError"
```

### Step 3: Test Wallet Connection Flow
1. Click "Connect Wallet" button
2. Check console for errors during modal opening
3. Try connecting MetaMask (if installed)
4. Check for any error toasts or modals

### Step 4: Network Tab Analysis
1. Open Network tab in DevTools
2. Look for failed requests (red status codes)
3. Check for 404s on assets or API calls

## Known Fixed Issues

These issues were recently resolved:
- ✅ Fixed SSR configuration mismatch in wagmi.config.js
- ✅ Added defensive array checks in useEthereumWallet.js (lines 76-80)
- ✅ Enhanced error handling in stores/wallet.js (lines 67-88)
- ✅ Comprehensive error logging and categorization
- ✅ DOM ready checks and timeout protection

## Potential Issues to Check

### 1. Wallet Provider Detection
The app should detect if MetaMask/Phantom are installed:
```javascript
// Check these in browser console
window.ethereum?.isMetaMask
window.solana?.isPhantom
```

### 2. Store Initialization
```javascript
// Check if store is initialized
$nuxt.$pinia._s.get('wallet')
```

### 3. Component Registration
All components should be auto-imported by Nuxt.js. No manual registration needed.

### 4. Environment Variables
Check if any required env vars are missing (though none are critical for basic functionality).

## Expected Console Output

**Normal startup should show:**
- Nuxt initialization messages
- Wallet store initialization logs
- No critical errors or unhandled promise rejections

**During wallet connection:**
- Debug messages from wallet composables
- Success/error messages from connection attempts

## Emergency Fixes

If critical errors found, try these immediate fixes:

### Clear Browser Cache
```bash
# In browser DevTools Console
localStorage.clear()
location.reload()
```

### Reset Wallet State
```bash
# In browser DevTools Console
localStorage.removeItem('walletPreference')
location.reload()
```

### Hard Reset Development Server
```bash
# In terminal
cd ui
rm -rf .nuxt node_modules/.cache
npm run dev
```

## Next Steps

1. Perform manual testing as outlined above
2. Report any console errors found
3. Test specific wallet connection flows
4. Check for any missing assets or failed network requests

The app architecture is solid based on static analysis. Most likely issues would be:
- Browser compatibility problems
- Wallet extension conflicts  
- Network/asset loading issues
- Environment-specific configuration problems