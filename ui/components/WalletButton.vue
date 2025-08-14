<template>
  <!-- Simple Wallet Connection Button -->
  <button
    @click="handleClick"
    :class="[
      'flex items-center gap-2 px-4 py-2 rounded-xl font-medium transition-all duration-300 border relative gradient-border',
      isConnected 
        ? 'border-gray-600/30 hover:border-gray-400/60 text-white shadow-lg' 
        : 'border-transparent shadow-lg hover:shadow-xl backdrop-blur-sm',
      isConnecting ? 'cursor-wait opacity-75' : 'cursor-pointer'
    ]"
    :style="!isConnected ? 'background-color: transparent; color: #01DA9D;' : 'background-color: #1B2E33;'"
    :disabled="isConnecting"
  >
    <!-- Connection Status Indicator -->
    <div v-if="isConnected" class="absolute -top-1 -right-1 w-3 h-3 bg-green-500 rounded-full border-2 border-gray-900 animate-pulse"></div>
    
    <!-- Loading Spinner -->
    <div v-if="isConnecting" class="animate-spin w-4 h-4 border-2 border-current border-t-transparent rounded-full"></div>
    
    <!-- Wallet Icon -->
    <div v-else-if="isConnected" class="w-5 h-5">
      <img v-if="walletIcon" :src="walletIcon" :alt="walletName" class="w-full h-full rounded-sm" @error="$event.target.style.display='none'" />
      <svg v-else viewBox="0 0 24 24" fill="white" class="w-full h-full">
        <path d="M21 18v1c0 1.1-.9 2-2 2H5c-1.1 0-2-.9-2-2V5c0-1.1.9-2 2-2h14c1.1 0 2 .9 2 2v1h-9c-1.1 0-2 .9-2 2v8c0 1.1.9 2 2 2h9zm-9-2h10V8H12v8zm4-2.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/>
      </svg>
    </div>
    
    <!-- Default Connect Icon -->
    <div v-else class="w-5 h-5">
      <svg viewBox="0 0 24 24" fill="white" class="w-full h-full">
        <path d="M21 18v1c0 1.1-.9 2-2 2H5c-1.1 0-2-.9-2-2V5c0-1.1.9-2 2-2h14c1.1 0 2 .9 2 2v1h-9c-1.1 0-2 .9-2 2v8c0 1.1.9 2 2 2h9zm-9-2h10V8H12v8zm4-2.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/>
      </svg>
    </div>

    <!-- Button Text -->
    <span class="text-sm font-semibold">
      {{ buttonText }}
    </span>
  </button>
</template>

<script setup>
import { useAppKit, useAppKitAccount, useAppKitNetwork } from '@reown/appkit/vue'
import { useAccount, useBalance, useDisconnect } from '@wagmi/vue'
import { computed, ref, watch, onMounted, onUnmounted } from 'vue'

// Use the same AppKit instance as the main app  
const appKit = useAppKit()
console.log('🔍 Available AppKit methods:', Object.keys(appKit))
const appKitAccount = useAppKitAccount()
const appKitNetwork = useAppKitNetwork()
const { address, isConnected, isConnecting, connector } = useAccount()
const { disconnect } = useDisconnect()
const { data: balance, isLoading: isBalanceLoading, error: balanceError, refetch: refetchBalance } = useBalance({ 
  address,
  query: {
    enabled: !!address.value,
    retry: 3,
    staleTime: 10_000, // Reduced to 10 seconds for more frequent updates
    refetchInterval: 30_000 // Auto-refresh every 30 seconds
  }
})

// Debug logging to see current state and detect sync issues
watch([address, isConnected, balance, appKitAccount, balanceError], ([addr, connected, bal, appKitAcc, balErr]) => {
  const debugInfo = {
    wagmiAddress: addr,
    wagmiConnected: connected,
    wagmiBalance: bal?.formatted,
    balanceError: balErr,
    appKitConnected: appKitAcc?.isConnected,
    appKitAddress: appKitAcc?.address,
    connectorName: connector.value?.name,
    isBalanceLoading: isBalanceLoading.value
  }
  
  console.log('🔍 WalletButton Debug:', debugInfo)
  
  // Check for sync issues and warn
  if (connected && addr && !appKitAcc?.isConnected) {
    console.warn('⚠️ SYNC ISSUE: Wagmi connected but AppKit disconnected')
    console.warn('This will cause the wallet button to behave incorrectly')
    console.warn('AppKit account state:', appKitAcc)
  }
  
  // Store debug info globally for inspection
  if (typeof window !== 'undefined') {
    window.__walletButtonDebug = debugInfo
  }
}, { immediate: true })

// Computed properties
const walletName = computed(() => {
  if (!connector.value) return 'Unknown'
  const name = connector.value.name
  return name === 'MetaMask' ? 'MetaMask' : 
         name === 'Coinbase Wallet' ? 'Coinbase' :
         name === 'WalletConnect' ? 'WalletConnect' : name
})

const walletIcon = computed(() => {
  // Try to get icon from AppKit account data first
  if (appKitAccount.value?.connector?.icon) {
    return appKitAccount.value.connector.icon
  }
  
  // Try to get icon from Wagmi connector
  if (connector.value?.icon) {
    return connector.value.icon
  }
  
  // Fallback to common wallet icons from CDN
  const name = walletName.value.toLowerCase()
  const iconMap = {
    'metamask': 'https://raw.githubusercontent.com/MetaMask/brand-resources/master/SVG/metamask-fox.svg',
    'coinbase': 'https://avatars.githubusercontent.com/u/18060234?s=280&v=4',
    'walletconnect': 'https://avatars.githubusercontent.com/u/37784886?s=280&v=4',
    'phantom': 'https://avatars.githubusercontent.com/u/78782331?s=280&v=4'
  }
  
  return iconMap[name] || null
})

const buttonText = computed(() => {
  if (isConnecting.value) return 'Connecting...'
  if (isConnected.value) {
    const addr = address.value
    return addr ? `${addr.slice(0, 6)}...${addr.slice(-4)}` : 'Connected'
  }
  return 'Connect'
})

// Methods
const handleClick = async () => {
  try {
    const currentState = {
      wagmiConnected: isConnected.value,
      wagmiAddress: address.value,
      wagmiBalance: balance.value?.formatted,
      appKitConnected: appKitAccount.value?.isConnected,
      appKitAddress: appKitAccount.value?.address
    }
    
    console.log('🔍 Opening AppKit modal, current state:', currentState)
    
    // Primary check: Use Wagmi state as source of truth since it's more reliable
    const isReallyConnected = isConnected.value && address.value
    
    if (isReallyConnected) {
      console.log('🔗 Wallet is connected via Wagmi, opening AppKit modal')
      
      // Check if AppKit is also aware of the connection
      if (appKitAccount.value?.isConnected) {
        console.log('✅ AppKit is synced - should show account modal')
      } else {
        console.warn('⚠️ AppKit not synced - may show connect modal instead of account modal')
        console.warn('Clicking will still work, but user will need to click "Connect" first')
      }
      
      // Force AppKit to show the account view instead of connect view
      // The issue is AppKit doesn't know about the Wagmi connection
      console.log('🔗 Opening AppKit - forcing account view for connected wallet')
      
      // Simply open the modal - AppKit should handle the state automatically
      appKit.open()
    } else {
      console.log('🔌 Opening connection modal')
      checkWalletAvailability()
      if (appKit.open) {
        appKit.open()
      } else {
        console.error('AppKit not available for connecting')
      }
    }
  } catch (error) {
    console.error('Failed to handle wallet button click:', error)
    if (typeof window !== 'undefined' && window.$toast) {
      showWalletUnavailableToast('Wallet')
    }
  }
}

// Check if common wallets are available
const checkWalletAvailability = () => {
  if (typeof window === 'undefined') return
  
  // Check MetaMask
  if (!window.ethereum || !window.ethereum.isMetaMask) {
    // Set up a listener to catch when user tries to connect to MetaMask specifically
    setTimeout(() => {
      // This catches the case where user clicked MetaMask in the modal but it's not available
      if (!isConnected.value && !isConnecting.value) {
        const urlParams = new URLSearchParams(window.location.search)
        if (urlParams.get('connector') === 'metamask' || document.querySelector('[data-testid="metamask"]')) {
          showWalletUnavailableToast('MetaMask')
        }
      }
    }, 2000)
  }
}

// Connection error handler reference for cleanup
let connectionErrorHandler = null

// Add event listeners for wallet connection errors and balance refresh
onMounted(() => {
  if (typeof window !== 'undefined') {
    // Listen for AppKit events or Wagmi connection errors
    connectionErrorHandler = (event) => {
      console.log('Connection error event:', event)
      if (event.detail && event.detail.error) {
        const error = event.detail.error.message || event.detail.error
        if (error.includes('chain') || error.includes('invalid') || error.includes('unavailable')) {
          showWalletUnavailableToast('MetaMask')
        }
      }
    }
    
    // Listen for forced balance refresh events
    const forceBalanceRefreshHandler = (event) => {
      console.log('🔄 Force balance refresh requested:', event.detail)
      if (refetchBalance && isConnected.value) {
        refetchBalance()
        console.log('✅ Balance refetch triggered')
      }
    }
    
    window.addEventListener('appkit:error', connectionErrorHandler)
    window.addEventListener('wagmi:error', connectionErrorHandler)
    window.addEventListener('forceBalanceRefresh', forceBalanceRefreshHandler)
    
    // Also listen for the specific chain error by intercepting console.error
    const originalConsoleError = console.error
    console.error = (...args) => {
      if (args[0] && args[0].includes && args[0].includes('invalid chain')) {
        showWalletUnavailableToast('MetaMask')
      }
      originalConsoleError.apply(console, args)
    }
    
    // Store the force refresh handler for cleanup
    window.__forceBalanceRefreshHandler = forceBalanceRefreshHandler
  }
})

onUnmounted(() => {
  if (typeof window !== 'undefined') {
    if (connectionErrorHandler) {
      window.removeEventListener('appkit:error', connectionErrorHandler)
      window.removeEventListener('wagmi:error', connectionErrorHandler)
    }
    if (window.__forceBalanceRefreshHandler) {
      window.removeEventListener('forceBalanceRefresh', window.__forceBalanceRefreshHandler)
    }
  }
})

// Show wallet unavailable toast with logo
const showWalletUnavailableToast = (walletName) => {
  if (typeof window !== 'undefined' && window.$toast) {
    const walletIcons = {
      'MetaMask': 'https://raw.githubusercontent.com/MetaMask/brand-resources/master/SVG/metamask-fox.svg',
      'Coinbase': 'https://avatars.githubusercontent.com/u/18060234?s=280&v=4',
      'WalletConnect': 'https://avatars.githubusercontent.com/u/37784886?s=280&v=4',
      'Phantom': 'https://avatars.githubusercontent.com/u/78782331?s=280&v=4'
    }
    
    window.$toast.error('Wallet unavailable', {
      title: `${walletName} unavailable`,
      customIcon: walletIcons[walletName] || null,
      actions: [
        {
          label: 'Install ' + walletName,
          handler: () => {
            if (walletName === 'MetaMask') {
              window.open('https://metamask.io/download/', '_blank')
            }
          }
        }
      ]
    })
  }
}

// isConnecting is now properly managed by Wagmi hooks
</script>