<template>
  <button
    @click="handleClick"
    :disabled="isConnecting"
    :class="[
      'px-4 py-2 bg-transparent gradient-border text-white rounded-lg',
      'font-medium transition-all duration-200',
      'disabled:opacity-50 disabled:cursor-not-allowed',
      'focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2'
    ]"
  >
    <span v-if="isConnecting" class="flex items-center gap-2">
      <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
      Connecting...
    </span>
    <span v-else-if="!isConnected">Connect Wallet</span>
    <span v-else class="flex items-center gap-2">
      <span class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></span>
      {{ formatAddress(address) }}
    </span>
  </button>
</template>

<script setup>
import { ref } from 'vue'
import { useAppKitWallet } from '~/composables/useAppKitWallet.js'
import { useAppKit, useAppKitAccount } from '@reown/appkit/vue'

// Use defensive destructuring with default values to prevent undefined errors
const walletComposable = useAppKitWallet()
const address = walletComposable?.address || ref(null)
const isConnected = walletComposable?.isConnected || ref(false)
const syncWithMetaMask = walletComposable?.syncWithMetaMask || (() => console.warn('MetaMask sync not available'))

// Use proper AppKit hooks for modal control
const { open: openModal } = useAppKit()

// Debug logging
console.log('🔍 AppKit modal function:', {
  hasOpenModal: !!openModal,
  openModalType: typeof openModal,
  timestamp: new Date().toISOString()
})

const isConnecting = ref(false)

// Debug: Log wallet state to diagnose issues
console.log('🔍 AppKitButton - Wallet composable state:', {
  hasComposable: !!walletComposable,
  hasAddress: !!address,
  hasIsConnected: !!isConnected,
  hasOpen: !!open,
  addressValue: address?.value,
  isConnectedValue: isConnected?.value,
  timestamp: new Date().toISOString()
})

// Throttle connection attempts to prevent Lit component update scheduling issues
let lastConnectionAttempt = 0
const CONNECTION_THROTTLE = 1000 // 1 second

const handleClick = async () => {
  const now = Date.now()
  if (now - lastConnectionAttempt < CONNECTION_THROTTLE) {
    console.log('🔗 Connection attempt throttled')
    return
  }
  lastConnectionAttempt = now

  // If already connected, open the AppKit account/profile modal
  if (isConnected.value) {
    console.log('🔗 Already connected - opening AppKit account modal:', formatAddress(address.value))
    
    try {
      // For connected users, we want to open the account modal, not the connect modal
      // Try different approaches for opening the account modal
      
      // Method 1: Try AppKit state/router methods for navigation
      if (window.$appKit) {
        console.log('📋 Trying AppKit navigation methods')
        console.log('Available methods:', Object.keys(window.$appKit))
        
        // Look for methods that might open account/profile view
        const potentialMethods = Object.keys(window.$appKit).filter(key => 
          key.toLowerCase().includes('open') || 
          key.toLowerCase().includes('show') || 
          key.toLowerCase().includes('navigate') ||
          key.toLowerCase().includes('account') ||
          key.toLowerCase().includes('profile')
        )
        console.log('Potential navigation methods:', potentialMethods)
        
        // Try some common AppKit methods for opening account view
        const methodsToTry = ['openAccount', 'showAccount', 'openProfile', 'showProfile']
        for (const method of methodsToTry) {
          if (typeof window.$appKit[method] === 'function') {
            console.log(`✅ Trying ${method}()`)
            try {
              window.$appKit[method]()
              setTimeout(() => { isConnecting.value = false }, 500)
              return
            } catch (err) {
              console.warn(`❌ ${method}() failed:`, err)
            }
          }
        }
      }
      
      // Method 2: Look for appkit-modal element and trigger account view
      const modal = document.querySelector('appkit-modal, w3m-modal')
      if (modal) {
        console.log('📋 Found modal element, opening account view')
        console.log('Modal methods:', Object.getOwnPropertyNames(modal).filter(prop => typeof modal[prop] === 'function'))
        
        // Try different ways to open the modal
        if (typeof modal.open === 'function') {
          console.log('Calling modal.open()')
          modal.open()
        } else if (modal.show && typeof modal.show === 'function') {
          console.log('Calling modal.show()')
          modal.show()
        } else if (modal.setAttribute) {
          console.log('Setting modal attributes')
          modal.setAttribute('open', 'true')
        }
        
        setTimeout(() => { isConnecting.value = false }, 500)
        return
      }
      
      // Method 2: Try creating w3m-account-button instead of w3m-button
      console.log('🔄 Trying w3m-account-button for connected users')
      const accountButton = document.createElement('w3m-account-button')
      if (accountButton) {
        accountButton.style.position = 'absolute'
        accountButton.style.left = '-9999px'
        accountButton.style.visibility = 'hidden'
        document.body.appendChild(accountButton)
        
        setTimeout(() => {
          accountButton.click()
          document.body.removeChild(accountButton)
        }, 100)
        
        setTimeout(() => {
          isConnecting.value = false
        }, 500)
        return
      }
      
    } catch (error) {
      console.error('❌ Error opening account modal:', error)
    }
  }

  // Check if MetaMask is connected but AppKit doesn't know about it
  if (window.ethereum && window.ethereum.selectedAddress && !isConnected.value) {
    console.log('🔄 MetaMask connected but AppKit not synced - attempting sync first...')
    try {
      await syncWithMetaMask()
      // Wait a moment for the sync to take effect
      await new Promise(resolve => setTimeout(resolve, 500))
      
      // Check again if sync worked
      if (isConnected.value) {
        console.log('✅ Sync successful - wallet now connected!')
        return
      }
    } catch (error) {
      console.warn('⚠️ MetaMask sync failed, proceeding with modal:', error)
    }
  }

  try {
    isConnecting.value = true
    console.log('🔗 Opening AppKit modal using openModal function...')
    
    // Try using the openModal function from useAppKit
    if (openModal && typeof openModal === 'function') {
      console.log('✅ Calling openModal()')
      await openModal()
      console.log('✅ Modal opened successfully')
      setTimeout(() => {
        isConnecting.value = false
      }, 500)
      return
    }
    
    console.warn('⚠️ openModal function not available, trying fallback methods...')
    
    // Fallback: try the modal directly
    const modal = document.querySelector('appkit-modal, w3m-modal')
    if (modal) {
      console.log('📋 Found modal, trying different approaches')
      
      // Try dispatching a custom event to open the modal
      try {
        const openEvent = new CustomEvent('open', { detail: { view: 'Account' } })
        modal.dispatchEvent(openEvent)
        console.log('✅ Dispatched open event')
      } catch (err) {
        console.warn('❌ Custom event failed:', err)
      }
      
      // Try setting multiple attributes
      try {
        modal.style.display = 'block'
        modal.setAttribute('open', '')
        modal.setAttribute('data-state', 'open')
        console.log('✅ Set modal attributes and style')
      } catch (err) {
        console.warn('❌ Attribute setting failed:', err)
      }
      
      // Try calling any available methods that might open it
      if (modal.show && typeof modal.show === 'function') {
        modal.show()
        console.log('✅ Called modal.show()')
      }
      
      // Try triggering a click event on the modal itself
      try {
        modal.click()
        console.log('✅ Triggered modal click')
      } catch (err) {
        console.warn('❌ Modal click failed:', err)
      }
      
    } else {
      console.error('❌ No modal element found')
      
      // As a last resort, try to create and add a modal
      try {
        const newModal = document.createElement('appkit-modal')
        newModal.setAttribute('open', '')
        document.body.appendChild(newModal)
        console.log('✅ Created new modal')
      } catch (err) {
        console.warn('❌ Modal creation failed:', err)
      }
    }
    
  } catch (error) {
    console.error('❌ Error opening AppKit modal:', error)
  } finally {
    // Reset connecting state after a delay
    setTimeout(() => {
      isConnecting.value = false
    }, 1500)
  }
}

const formatAddress = (addr) => {
  if (!addr || typeof addr !== 'string') return ''
  if (addr.length < 10) return addr
  return `${addr.slice(0, 6)}...${addr.slice(-4)}`
}

// Expose for parent components if needed
defineExpose({
  handleClick,
  isConnected,
  address,
  isConnecting
})
</script>
<style scoped>
/* Gradient border effect - matches the swap form */
.gradient-border {
  position: relative;
  border: 1px solid rgba(55, 65, 81, 0.5);
  border-radius: 0.5rem;
  transition: all 0.3s ease;
}

.gradient-border:hover {
  border: 1px solid #00ff88;
  animation: border-color-cycle 24s ease infinite;
}

@keyframes border-color-cycle {
  0% { border-color: #00ff88; }
  25% { border-color: #00d9ff; }
  50% { border-color: #8b5cf6; }
  75% { border-color: #a855f7; }
  100% { border-color: #00ff88; }
}
</style>
