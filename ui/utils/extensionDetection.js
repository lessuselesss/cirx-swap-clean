/**
 * Advanced browser extension detection utilities
 */

// Common extension IDs for Chrome Web Store extensions
export const EXTENSION_IDS = {
  metamask: 'nkbihfbeogaeaoehlefnkodbefgpgknn',
  ublock: 'cjpalhdlnbpafiamejdnhcphjbkeiagm',
  adblock: 'gighmmpiobklfepjocnamgkkbiglidom',
  lastpass: 'hdokiejnpimakedhajhdlcegeplioahd',
  onePassword: 'aeblfdkhhhdcdjpifhhbdiojplfjncoa',
  bitwarden: 'nngceckbapebfimnlniiiahkandclblb',
  honey: 'bmnlcjabgnpnenekpadlanbbkooimhnj',
  grammarly: 'kbfnbcaeplbcioakkpcpgfkobkghlhen',
  ghostery: 'mlomiejdfkolichcflejclcbmpeaniij'
}

// Extension detection patterns
export const EXTENSION_PATTERNS = {
  // Wallet Extensions
  metamask: {
    name: 'MetaMask',
    type: 'wallet',
    detect: () => !!(window.ethereum?.isMetaMask),
    windowProps: ['ethereum'],
    domSelectors: ['[data-metamask]'],
    resourceTest: 'chrome-extension://nkbihfbeogaeaoehlefnkodbefgpgknn/manifest.json'
  },
  
  phantom: {
    name: 'Phantom',
    type: 'wallet',
    detect: () => !!(window.phantom?.solana || window.solana?.isPhantom),
    windowProps: ['phantom', 'solana'],
    domSelectors: ['[data-phantom]']
  },
  
  coinbase: {
    name: 'Coinbase Wallet',
    type: 'wallet',
    detect: () => !!(window.ethereum?.isCoinbaseWallet || window.coinbaseWalletExtension),
    windowProps: ['coinbaseWalletExtension']
  },
  
  // Privacy & Security
  ublock: {
    name: 'uBlock Origin',
    type: 'privacy',
    detect: () => detectUBlock(),
    resourceTest: `chrome-extension://${EXTENSION_IDS.ublock}/manifest.json`
  },
  
  adblock: {
    name: 'AdBlock',
    type: 'privacy',
    detect: () => detectAdBlock(),
    resourceTest: `chrome-extension://${EXTENSION_IDS.adblock}/manifest.json`
  },
  
  // Password Managers
  lastpass: {
    name: 'LastPass',
    type: 'password',
    detect: () => !!(document.querySelector('[data-lastpass-icon-root]') || window.lpData),
    domSelectors: ['[data-lastpass-icon-root]', '#lp-pom-root'],
    resourceTest: `chrome-extension://${EXTENSION_IDS.lastpass}/manifest.json`
  },
  
  onePassword: {
    name: '1Password',
    type: 'password',
    detect: () => !!(document.querySelector('[data-1p-ignore]') || window.OnePassword),
    domSelectors: ['[data-1p-ignore]', '[data-onepassword-extension]'],
    resourceTest: `chrome-extension://${EXTENSION_IDS.onePassword}/manifest.json`
  },
  
  bitwarden: {
    name: 'Bitwarden',
    type: 'password',
    detect: () => !!(document.querySelector('[data-bw-ignore]') || window.BitwardenExtension),
    domSelectors: ['[data-bw-ignore]', '[data-bitwarden-notification]'],
    resourceTest: `chrome-extension://${EXTENSION_IDS.bitwarden}/manifest.json`
  },
  
  // Shopping & Productivity
  honey: {
    name: 'Honey',
    type: 'shopping',
    detect: () => !!(window.honey || document.querySelector('#honey-extension-root')),
    windowProps: ['honey'],
    domSelectors: ['#honey-extension-root'],
    resourceTest: `chrome-extension://${EXTENSION_IDS.honey}/manifest.json`
  },
  
  grammarly: {
    name: 'Grammarly',
    type: 'productivity',
    detect: () => !!(document.querySelector('[data-grammarly-extension]') || window.grammarly),
    domSelectors: ['[data-grammarly-extension]', 'grammarly-extension'],
    resourceTest: `chrome-extension://${EXTENSION_IDS.grammarly}/manifest.json`
  },
  
  // Developer Tools
  react: {
    name: 'React Developer Tools',
    type: 'developer',
    detect: () => !!(window.__REACT_DEVTOOLS_GLOBAL_HOOK__)
  },
  
  vue: {
    name: 'Vue.js Developer Tools',
    type: 'developer',
    detect: () => !!(window.__VUE_DEVTOOLS_GLOBAL_HOOK__)
  }
}

// Advanced detection functions
function detectUBlock() {
  // Test for uBlock Origin by checking blocked requests
  const testElement = document.createElement('div')
  testElement.className = 'ads ad adsbox doubleclick ad-placement carbon-ads'
  testElement.style.cssText = 'position: absolute !important; left: -10000px !important; width: 1px !important; height: 1px !important;'
  document.body.appendChild(testElement)
  
  const isBlocked = testElement.offsetHeight === 0 || testElement.offsetWidth === 0
  document.body.removeChild(testElement)
  
  return isBlocked
}

function detectAdBlock() {
  // Create a fake ad element that AdBlock would hide
  const adTest = document.createElement('div')
  adTest.innerHTML = '&nbsp;'
  adTest.className = 'adsbox'
  adTest.style.cssText = 'position: absolute; left: -10000px; width: 1px; height: 1px;'
  document.body.appendChild(adTest)
  
  const blocked = adTest.offsetHeight === 0
  document.body.removeChild(adTest)
  
  return blocked
}

// Test if extension resource is accessible
export async function testExtensionResource(url, timeout = 1000) {
  try {
    const controller = new AbortController()
    const timeoutId = setTimeout(() => controller.abort(), timeout)
    
    const response = await fetch(url, {
      method: 'HEAD',
      signal: controller.signal
    })
    
    clearTimeout(timeoutId)
    return response.ok
  } catch (error) {
    return false
  }
}

// Detect extensions by checking DOM for injected elements
export function detectByDOM(selectors = []) {
  return selectors.some(selector => {
    try {
      return document.querySelector(selector) !== null
    } catch {
      return false
    }
  })
}

// Detect extensions by window properties
export function detectByWindow(props = []) {
  return props.some(prop => {
    try {
      return window[prop] !== undefined
    } catch {
      return false
    }
  })
}

// Main detection function
export async function detectAllExtensions() {
  const detected = []
  const startTime = Date.now()
  
  for (const [id, pattern] of Object.entries(EXTENSION_PATTERNS)) {
    try {
      let isDetected = false
      const methods = []
      
      // Method 1: Custom detection function
      if (pattern.detect) {
        const customResult = await pattern.detect()
        if (customResult) {
          isDetected = true
          methods.push('custom')
        }
      }
      
      // Method 2: DOM selectors
      if (pattern.domSelectors && !isDetected) {
        const domResult = detectByDOM(pattern.domSelectors)
        if (domResult) {
          isDetected = true
          methods.push('dom')
        }
      }
      
      // Method 3: Window properties
      if (pattern.windowProps && !isDetected) {
        const windowResult = detectByWindow(pattern.windowProps)
        if (windowResult) {
          isDetected = true
          methods.push('window')
        }
      }
      
      // Method 4: Resource loading (slower, so do last)
      if (pattern.resourceTest && !isDetected) {
        const resourceResult = await testExtensionResource(pattern.resourceTest)
        if (resourceResult) {
          isDetected = true
          methods.push('resource')
        }
      }
      
      if (isDetected) {
        detected.push({
          id,
          name: pattern.name,
          type: pattern.type || 'unknown',
          detected: true,
          methods,
          pattern
        })
      }
    } catch (error) {
      console.warn(`Failed to detect ${pattern.name}:`, error)
    }
  }
  
  const endTime = Date.now()
  console.log(`Extension detection completed in ${endTime - startTime}ms. Found ${detected.length} extensions.`)
  
  return detected
}

// Get extensions by type
export function getExtensionsByType(extensions, type) {
  return extensions.filter(ext => ext.type === type)
}

// Quick wallet detection (most common use case)
export function detectWallets() {
  const wallets = []
  
  // MetaMask
  if (window.ethereum?.isMetaMask) {
    wallets.push({
      name: 'MetaMask',
      id: 'metamask',
      icon: 'https://raw.githubusercontent.com/MetaMask/brand-resources/master/SVG/metamask-fox.svg'
    })
  }
  
  // Phantom
  if (window.phantom?.solana || window.solana?.isPhantom) {
    wallets.push({
      name: 'Phantom',
      id: 'phantom',
      icon: 'https://avatars.githubusercontent.com/u/78782331?s=280&v=4'
    })
  }
  
  // Coinbase
  if (window.ethereum?.isCoinbaseWallet || window.coinbaseWalletExtension) {
    wallets.push({
      name: 'Coinbase Wallet',
      id: 'coinbase',
      icon: 'https://avatars.githubusercontent.com/u/18060234?s=280&v=4'
    })
  }
  
  // Rabby
  if (window.ethereum?.isRabby) {
    wallets.push({
      name: 'Rabby',
      id: 'rabby',
      icon: 'https://rabby.io/assets/images/logo-128.png'
    })
  }
  
  return wallets
}

// Export utility for use in Vue composables
export default {
  detectAllExtensions,
  detectWallets,
  getExtensionsByType,
  testExtensionResource,
  EXTENSION_PATTERNS,
  EXTENSION_IDS
}