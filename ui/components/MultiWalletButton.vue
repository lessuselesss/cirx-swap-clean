<template>
  <div class="relative">
    <!-- Generic Connect Wallet Button -->
    <button
      v-if="!isConnected"
      @click="openModal"
      class="px-4 py-2 bg-circular-primary text-gray-900 rounded-lg font-medium hover:bg-circular-primary-hover transition-colors flex items-center gap-2"
      :disabled="isConnecting"
    >
      <!-- Connect Wallet Icon -->
      <svg width="20" height="20" fill="currentColor" viewBox="0 0 640 640">
        <path d="M482.4 221.9C517.7 213.6 544 181.9 544 144C544 99.8 508.2 64 464 64C420.6 64 385.3 98.5 384 141.5L200.2 215.1C185.7 200.8 165.9 192 144 192C99.8 192 64 227.8 64 272C64 316.2 99.8 352 144 352C156.2 352 167.8 349.3 178.1 344.4L323.7 471.8C321.3 479.4 320 487.6 320 496C320 540.2 355.8 576 400 576C444.2 576 480 540.2 480 496C480 468.3 466 443.9 444.6 429.6L482.4 221.9zM220.3 296.2C222.5 289.3 223.8 282 224 274.5L407.8 201C411.4 204.5 415.2 207.7 419.4 210.5L381.6 418.1C376.1 419.4 370.8 421.2 365.8 423.6L220.3 296.2z"/>
      </svg>
      
      <span v-if="isConnecting">Connecting...</span>
      <span v-else>Connect Wallet</span>
    </button>

    <!-- Connected State -->
    <div v-else class="flex items-center gap-3">
      <!-- Network Warning -->
      <div v-if="!isOnSupportedChain" class="flex items-center gap-2">
        <button
          @click="switchToMainnet"
          class="px-3 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium text-sm transition-colors"
        >
          Switch Network
        </button>
      </div>
      
      <!-- Account Info -->
      <div class="bg-gray-800 border border-gray-600 rounded-lg px-3 py-2">
        <div class="flex items-center gap-2">
          <!-- Wallet Icon -->
          <div class="w-4 h-4 flex items-center justify-center text-white">
            <!-- MetaMask Fox Icon -->
            <img v-if="connectedWallet === 'metamask'" src="/icons/wallets/metamask-fox.svg" alt="MetaMask" width="16" height="16" class="text-orange-500" />
            <!-- Phantom Ghost Icon -->
            <img v-else-if="connectedWallet === 'phantom'" src="/icons/wallets/phantom-icon.svg" alt="Phantom" width="16" height="16" class="text-purple-500" />
            <!-- WalletConnect Icon -->
            <img v-else-if="connectedWallet === 'walletconnect'" src="/icons/wallets/walletconnect.svg" alt="WalletConnect" width="16" height="16" class="text-blue-500" />
            <div v-else class="w-2 h-2 bg-green-400 rounded-full"></div>
          </div>
          <span class="text-sm font-medium text-white">{{ shortAddress }}</span>
        </div>
        <div class="text-xs text-gray-400">
          {{ headerBalance }} {{ selectedTokenForHeader }}
        </div>
      </div>
      
      <!-- Disconnect Button -->
      <button
        @click="handleDisconnect"
        class="px-3 py-2 text-gray-400 hover:text-white transition-colors"
        title="Disconnect wallet"
      >
        <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
          <path d="M16 17v-3H9v-4h7V7l5 5-5 5M14 2a2 2 0 012 2v2h-2V4H5v16h9v-2h2v2a2 2 0 01-2 2H5a2 2 0 01-2-2V4a2 2 0 012-2h9z"/>
        </svg>
      </button>
    </div>

    <!-- Wallet Selection Modal -->
    <div
      v-if="walletStore.isWalletModalOpen"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 min-h-screen"
      @click="closeModal"
    >
      <div
        class="bg-gray-900 border border-gray-700 rounded-xl p-6 w-full max-w-md mx-4 my-8"
        @click.stop
      >
        <!-- Modal Header -->
        <div class="flex items-center justify-between mb-6">
          <h3 class="text-xl font-semibold text-white">Connect Wallet</h3>
          <button
            @click="closeModal"
            class="text-gray-400 hover:text-white transition-colors"
          >
            <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
              <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
            </svg>
          </button>
        </div>

        <!-- Status / Controls -->
        <div v-if="isConnecting" class="mb-4 flex items-center justify-between text-sm">
          <span class="text-gray-400">Connecting...</span>
          <div class="flex gap-2">
            <button class="px-2 py-1 text-xs rounded bg-gray-700 hover:bg-gray-600 text-white" @click="walletStore.cancelConnect()">Cancel</button>
            <button class="px-2 py-1 text-xs rounded bg-gray-700 hover:bg-gray-600 text-white" @click="walletStore.hardReset()">Reset</button>
          </div>
        </div>

        <!-- Wallet Options (MetaMask & Phantom) -->
        <div class="space-y-3">
          <!-- MetaMask Option -->
          <div 
            class="flex items-center justify-between p-4 border border-gray-700 rounded-lg hover:border-gray-600 transition-colors cursor-pointer"
            :class="{
              'opacity-50': !isMetaMaskAvailable && !isConnecting,
              'bg-gray-800/50': isConnecting && connectingWallet === 'metamask'
            }"
            @click="handleWalletClick('metamask')"
          >
            <div class="flex items-center gap-3">
              <div class="w-8 h-8 rounded-lg bg-orange-500/20 flex items-center justify-center text-orange-400">
                <img src="/icons/wallets/metamask-fox.svg" alt="MetaMask" width="20" height="20" />
              </div>
              <div>
                <div class="font-medium text-white">MetaMask</div>
                <div class="text-sm text-gray-400">{{ isMetaMaskAvailable ? 'Available' : 'Not installed' }}</div>
              </div>
            </div>
            <div class="flex items-center">
              <div v-if="isConnecting && connectingWallet === 'metamask'" class="w-4 h-4 border-2 border-circular-primary border-t-transparent rounded-full animate-spin"></div>
              <svg v-else-if="isMetaMaskAvailable" width="16" height="16" fill="currentColor" class="text-green-400" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
              <svg v-else width="16" height="16" fill="currentColor" class="text-gray-500" viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
            </div>
          </div>

          <!-- Phantom Option -->
          <div 
            class="flex items-center justify-between p-4 border border-gray-700 rounded-lg hover:border-gray-600 transition-colors cursor-pointer"
            :class="{
              'opacity-50': !isPhantomAvailable && !isConnecting,
              'bg-gray-800/50': isConnecting && connectingWallet === 'phantom'
            }"
            @click="handleWalletClick('phantom')"
          >
            <div class="flex items-center gap-3">
              <div class="w-8 h-8 rounded-lg bg-purple-500/20 flex items-center justify-center text-purple-400">
                <img src="/icons/wallets/phantom-icon.svg" alt="Phantom" width="20" height="20" />
              </div>
              <div>
                <div class="font-medium text-white">Phantom</div>
                <div class="text-sm text-gray-400">{{ isPhantomAvailable ? 'Available' : 'Not installed' }}</div>
              </div>
            </div>
            <div class="flex items-center">
              <div v-if="isConnecting && connectingWallet === 'phantom'" class="w-4 h-4 border-2 border-circular-primary border-t-transparent rounded-full animate-spin"></div>
              <svg v-else-if="isPhantomAvailable" width="16" height="16" fill="currentColor" class="text-green-400" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
              <svg v-else width="16" height="16" fill="currentColor" class="text-gray-500" viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
            </div>
          </div>

          <!-- WalletConnect (disabled/coming soon) -->
          <div class="p-4 border border-gray-800 rounded-lg flex items-center justify-between opacity-50 cursor-not-allowed">
            <div class="flex items-center gap-3">
              <div class="w-8 h-8 rounded-lg bg-blue-500/20 flex items-center justify-center text-blue-400">
                <img src="/icons/wallets/walletconnect.svg" alt="WalletConnect" width="20" height="20" />
              </div>
              <div>
                <div class="font-medium text-white">WalletConnect</div>
                <div class="text-sm text-gray-500">Coming soon</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="mt-6 flex items-center justify-between">
          <p class="text-sm text-gray-400">New to wallets? <a href="https://ethereum.org/en/wallets/" target="_blank" rel="noopener" class="text-circular-primary hover:text-circular-primary-hover">Learn more</a></p>
          <div class="flex gap-2">
            <button class="px-3 py-1.5 text-xs rounded bg-gray-800 hover:bg-gray-700 text-white" @click="walletStore.hardReset()">Hard Reset</button>
            <button class="px-3 py-1.5 text-xs rounded bg-gray-800 hover:bg-gray-700 text-white" @click="walletStore.clearError()" v-if="error">Clear Error</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Error Display -->
    <div
      v-if="error"
      class="absolute top-full left-0 right-0 mt-2 p-3 bg-red-900/90 border border-red-700 rounded-lg text-red-200 text-sm z-10"
    >
      {{ error }}
      <button
        @click="clearError"
        class="ml-2 text-red-400 hover:text-red-200"
      >
        ✕
      </button>
    </div>

    <!-- Network Warning -->
    <div
      v-if="isConnected && !isOnSupportedChain"
      class="absolute top-full left-0 right-0 mt-2 p-3 bg-yellow-900/90 border border-yellow-700 rounded-lg text-yellow-200 text-sm z-10"
    >
      <div class="flex items-center justify-between">
        <span>Unsupported network detected</span>
        <button
          v-if="connectedWallet === 'metamask'"
          @click="switchToMainnet"
          class="ml-2 px-2 py-1 bg-yellow-600 hover:bg-yellow-700 text-yellow-100 rounded text-xs transition-colors"
        >
          Switch
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useWalletStore } from '~/stores/wallet'

// Use wallet store
const walletStore = useWalletStore()

// Computed properties from store and composables
const isConnected = computed(() => walletStore.isConnected)
const isConnecting = computed(() => walletStore.isConnecting)
const error = computed(() => walletStore.currentError)
const connectedWallet = computed(() => walletStore.activeWallet?.type)
const shortAddress = computed(() => {
  if (!walletStore.activeWallet?.address) return ''
  const addr = walletStore.activeWallet.address
  return `${addr.slice(0, 6)}...${addr.slice(-4)}`
})

// Auto-set selected token based on connected wallet
const selectedTokenForHeader = computed(() => {
  // If user manually selected a token, use that
  if (walletStore.selectedToken) {
    return walletStore.selectedToken
  }
  
  // Auto-select based on wallet type
  if (connectedWallet.value === 'phantom') {
    return 'SOL'
  } else if (connectedWallet.value === 'metamask') {
    return 'ETH'
  }
  
  // Default fallback
  return 'ETH'
})

const headerBalance = computed(() => {
  try {
    // Use the balance directly from the active wallet in the store
    if (connectedWallet.value === 'metamask' && walletStore.metaMaskWallet) {
      return walletStore.metaMaskWallet.getTokenBalance(selectedTokenForHeader.value)
    } else if (connectedWallet.value === 'phantom' && walletStore.solanaWallet) {
      return walletStore.solanaWallet.balance.value // solanaWallet exposes formattedBalance
    }
    return '0.0'
  } catch {
    return '0.0'
  }
})

// Check wallet availability using store instances
const isMetaMaskAvailable = computed(() => {
  // First check if MetaMask wallet instance exists and has the availability check
  if (walletStore.metaMaskWallet?.isMetaMaskInstalled) {
    return walletStore.metaMaskWallet.isMetaMaskInstalled.value
  }
  // Fallback to direct window check if store instance not ready
  if (typeof window === 'undefined') return false
  return typeof window.ethereum !== 'undefined' && window.ethereum.isMetaMask
})

const isPhantomAvailable = computed(() => {
  // Check direct window property since Phantom doesn't have a complex composable like MetaMask
  if (typeof window === 'undefined') return false
  return typeof window.solana !== 'undefined' && window.solana.isPhantom
})

const isOnSupportedChain = computed(() => {
  if (connectedWallet.value === 'metamask' && walletStore.metaMaskWallet) {
    return walletStore.metaMaskWallet.isOnSupportedChain?.value || false
  }
  return true
})

// Modal state (centralized)
const connectingWallet = ref(null)
const openModal = () => walletStore.openWalletModal()
const closeModal = () => walletStore.closeWalletModal()

// Wallet connection handlers
const handleWalletClick = async (walletType) => {
  console.log('🔘 UI DEBUG: handleWalletClick called with:', walletType)
  
  const isAvailable = getWalletAvailability(walletType)
  console.log('🔘 UI DEBUG: wallet availability:', isAvailable)
  
  if (!isAvailable.available) {
    console.log('🔘 UI DEBUG: Wallet not available, opening install URL')
    window.open(isAvailable.installUrl, '_blank')
    return
  }
  
  try {
    console.log('🔘 UI DEBUG: Starting connection process')
    connectingWallet.value = walletType
    
    if (isConnected.value) {
      console.log('🔘 UI DEBUG: Already connected, disconnecting first')
      await walletStore.disconnectWallet(false)
    }
    
    console.log('🔘 UI DEBUG: Calling connectWallet function')
    await connectWallet(walletType)
    console.log('🔘 UI DEBUG: Connection successful, closing modal')
    closeModal()
  } catch (error) {
    console.error('❌ UI DEBUG: Connection failed:', {
      walletType,
      error: error.message,
      code: error.code,
      stack: error.stack
    })
  } finally {
    console.log('🔘 UI DEBUG: Clearing connectingWallet')
    connectingWallet.value = null
  }
}

// Availability metadata
const getWalletAvailability = (walletType) => {
  switch (walletType) {
    case 'metamask':
      return { available: isMetaMaskAvailable.value, installUrl: 'https://metamask.io/download/' }
    case 'phantom':
      return { available: isPhantomAvailable.value, installUrl: 'https://phantom.app/download' }
    default:
      return { available: false, installUrl: '' }
  }
}

// Connect to specific wallet
const connectWallet = async (walletType) => {
  console.log('🔌 UI DEBUG: connectWallet called with:', walletType)
  console.log('🔌 UI DEBUG: walletStore exists?', !!walletStore)
  console.log('🔌 UI DEBUG: walletStore.connectWallet exists?', typeof walletStore.connectWallet)
  
  switch (walletType) {
    case 'metamask':
      console.log('🔌 UI DEBUG: Calling walletStore.connectWallet for MetaMask')
      await walletStore.connectWallet('metamask', 'ethereum')
      console.log('🔌 UI DEBUG: MetaMask connection completed')
      break
    case 'phantom':
      console.log('🔌 UI DEBUG: Calling walletStore.connectWallet for Phantom')
      await walletStore.connectWallet('phantom', 'solana')
      console.log('🔌 UI DEBUG: Phantom connection completed')
      break
    default:
      console.log('🔌 UI DEBUG: Unknown wallet type:', walletType)
      throw new Error(`Unknown wallet type: ${walletType}`)
  }
}

const handleDisconnect = async () => {
  try { await walletStore.disconnectWallet() } catch (e) { console.error('Disconnect failed:', e) }
}

const switchToMainnet = async () => {
  try { await walletStore.switchChain(1) } catch (e) { console.error('Network switch failed:', e) }
}

const clearError = () => {
  walletStore.clearError()
}

// Single wallet enforcement remains
const enforceeSingleWallet = () => {
  const connectedWallets = []
  if (walletStore.metaMaskWallet?.isConnected?.value) connectedWallets.push('metamask')
  if (walletStore.phantomWallet?.isConnected?.value) connectedWallets.push('phantom')
  if (connectedWallets.length > 1) {
    const activeWalletType = walletStore.activeWallet?.type
    connectedWallets.forEach(async (wt) => { if (wt !== activeWalletType) await walletStore.disconnectSpecificWallet(wt) })
  }
}

// ESC key handler for modal
const handleEscKey = (event) => {
  if (event.key === 'Escape' && walletStore.isWalletModalOpen) {
    closeModal()
  }
}

// On mount, initialize and prompt for connection if no preference saved
onMounted(async () => {
  try {
    await walletStore.initialize()
    enforceeSingleWallet()
    watch([() => walletStore.metaMaskWallet?.isConnected?.value, () => walletStore.phantomWallet?.isConnected?.value], () => enforceeSingleWallet())
    
    // Add ESC key listener
    window.addEventListener('keydown', handleEscKey)
  } catch (e) { console.error('Wallet init failed:', e) }
})

// Cleanup on unmount
onUnmounted(() => {
  window.removeEventListener('keydown', handleEscKey)
})
</script>