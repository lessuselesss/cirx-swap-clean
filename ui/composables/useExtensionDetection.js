// Extension detection composable
import { ref, onMounted } from 'vue'

export const useExtensionDetection = () => {
  const detectedExtensions = ref([])
  const isDetecting = ref(false)

  // Common extension detection patterns
  const extensionPatterns = {
    // Wallet Extensions
    metamask: {
      name: 'MetaMask',
      detect: () => !!(window.ethereum?.isMetaMask),
      icon: 'https://raw.githubusercontent.com/MetaMask/brand-resources/master/SVG/metamask-fox.svg'
    },
    coinbaseWallet: {
      name: 'Coinbase Wallet',
      detect: () => !!(window.ethereum?.isCoinbaseWallet || window.coinbaseWalletExtension),
      icon: 'https://avatars.githubusercontent.com/u/18060234?s=280&v=4'
    },
    phantom: {
      name: 'Phantom',
      detect: () => !!(window.phantom?.solana || window.solana?.isPhantom),
      icon: 'https://avatars.githubusercontent.com/u/78782331?s=280&v=4'
    },
    rabby: {
      name: 'Rabby Wallet',
      detect: () => !!(window.ethereum?.isRabby),
      icon: 'https://rabby.io/assets/images/logo-128.png'
    },
    trust: {
      name: 'Trust Wallet',
      detect: () => !!(window.ethereum?.isTrust),
      icon: 'https://trustwallet.com/assets/images/media/assets/trust_platform.png'
    },
    
    // Browser Extensions
    adblock: {
      name: 'AdBlock',
      detect: () => {
        // Create test element that AdBlock would hide
        const testAd = document.createElement('div')
        testAd.innerHTML = '&nbsp;'
        testAd.className = 'adsbox'
        testAd.style.position = 'absolute'
        testAd.style.left = '-10000px'
        document.body.appendChild(testAd)
        const isBlocked = testAd.offsetHeight === 0
        document.body.removeChild(testAd)
        return isBlocked
      },
      icon: '🚫'
    },
    uBlockOrigin: {
      name: 'uBlock Origin',
      detect: () => !!(window.uBlockOrigin || document.querySelector('script[src*="ublock"]')),
      icon: '🛡️'
    },
    
    // Developer Tools
    reactDevTools: {
      name: 'React Developer Tools',
      detect: () => !!(window.__REACT_DEVTOOLS_GLOBAL_HOOK__),
      icon: '⚛️'
    },
    vueDevTools: {
      name: 'Vue.js Developer Tools',
      detect: () => !!(window.__VUE_DEVTOOLS_GLOBAL_HOOK__),
      icon: '💚'
    },
    
    // Privacy/Security
    duckduckgo: {
      name: 'DuckDuckGo Privacy Essentials',
      detect: () => !!(window.DDG || document.querySelector('[data-ddg]')),
      icon: '🦆'
    },
    ghostery: {
      name: 'Ghostery',
      detect: () => !!(window.ghostery || window.__ghostery),
      icon: '👻'
    },
    
    // Password Managers
    onePassword: {
      name: '1Password',
      detect: () => !!(document.querySelector('[data-1p-ignore]') || window.OnePassword),
      icon: '🔐'
    },
    lastpass: {
      name: 'LastPass',
      detect: () => !!(document.querySelector('[data-lastpass-icon-root]') || window.lpData),
      icon: '🔑'
    },
    bitwarden: {
      name: 'Bitwarden',
      detect: () => !!(document.querySelector('[data-bw-ignore]') || window.BitwardenExtension),
      icon: '🛡️'
    }
  }

  // Advanced detection using resource loading
  const detectByResourceLoading = async (extensionId, resourcePath) => {
    try {
      const response = await fetch(`chrome-extension://${extensionId}/${resourcePath}`)
      return response.ok
    } catch {
      return false
    }
  }

  // Detect extensions by checking for injected scripts/styles
  const detectByInjection = (selector) => {
    return !!document.querySelector(selector)
  }

  // Main detection function
  const detectExtensions = async () => {
    isDetecting.value = true
    const detected = []

    for (const [key, extension] of Object.entries(extensionPatterns)) {
      try {
        if (await extension.detect()) {
          detected.push({
            id: key,
            name: extension.name,
            icon: extension.icon,
            detected: true
          })
        }
      } catch (error) {
        console.warn(`Failed to detect ${extension.name}:`, error)
      }
    }

    detectedExtensions.value = detected
    isDetecting.value = false
    return detected
  }

  // Check for specific wallet extensions
  const hasWalletExtensions = () => {
    return detectedExtensions.value.some(ext => 
      ['metamask', 'coinbaseWallet', 'phantom', 'rabby', 'trust'].includes(ext.id)
    )
  }

  // Check for ad blockers
  const hasAdBlockers = () => {
    return detectedExtensions.value.some(ext => 
      ['adblock', 'uBlockOrigin'].includes(ext.id)
    )
  }

  // Get wallet extensions only
  const getWalletExtensions = () => {
    return detectedExtensions.value.filter(ext => 
      ['metamask', 'coinbaseWallet', 'phantom', 'rabby', 'trust'].includes(ext.id)
    )
  }

  // Auto-detect on mount
  onMounted(() => {
    // Wait for extensions to load
    setTimeout(detectExtensions, 1000)
  })

  return {
    detectedExtensions,
    isDetecting,
    detectExtensions,
    hasWalletExtensions,
    hasAdBlockers,
    getWalletExtensions
  }
}