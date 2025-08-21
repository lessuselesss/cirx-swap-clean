import { createAppKit } from '@reown/appkit/vue'
import { defineNuxtPlugin } from 'nuxt/app'
import { wagmiAdapter, solanaAdapter, networks, projectId, metadata } from '~/config/appkit.js'

// Debug logging for adapter state
console.log('🔧 Adapters imported:', {
  wagmiNetworks: networks.filter(n => n.id && typeof n.id === 'string' && !n.id.includes('solana')).length,
  solanaNetworks: networks.filter(n => n.id && typeof n.id === 'string' && n.id.includes('solana')).length
})

// Store adapters globally for debugging
if (typeof window !== 'undefined') {
  window.__wagmiAdapter = wagmiAdapter
  window.__solanaAdapter = solanaAdapter
}

export default defineNuxtPlugin(() => {
  try {
    console.log('⚙️ Initializing Reown AppKit plugin...')
    console.log('Project ID:', projectId)
    console.log('Networks configured:', networks.length)
    
    // Create the AppKit instance with corrected configuration
    const appKit = createAppKit({
      adapters: [wagmiAdapter, solanaAdapter],
      networks,
      projectId,
      metadata,
      features: {
        analytics: false,
        email: false,
        socials: [],
        onramp: false,
        swaps: false
      },
      themeMode: 'dark',
      themeVariables: {
        '--w3m-accent': '#00D4FF'
      },
      // Prevent auto-connection attempts
      autoConnect: false,
      defaultChain: 'ethereum'
    })
    
    // Make AppKit instance globally accessible
    if (typeof window !== 'undefined') {
      window.$appKit = appKit
      
      // Listen for custom events from components
      window.addEventListener('openWalletModal', () => {
        appKit.open()
      })
      
      // Add disconnect functionality
      window.$disconnect = async () => {
        try {
          await appKit.disconnect()
          // Also clear any cached connections
          localStorage.removeItem('wagmi.wallet')
          localStorage.removeItem('wagmi.store')
          window.location.reload()
          console.log('✅ Wallet disconnected and page refreshed')
        } catch (error) {
          console.error('❌ Error disconnecting wallet:', error)
          // Force disconnect by clearing storage and refreshing
          localStorage.clear()
          window.location.reload()
        }
      }
      
      // Debug AppKit state changes and force balance refresh
      appKit.subscribeAccount((account) => {
        console.log('🔍 AppKit Account State:', {
          ...account,
          balanceSymbol: account.balanceSymbol,
          balance: account.balance,
          profileName: account.profileName
        })
        
        // Store AppKit account state for debugging
        window.__appKitAccountState = account
        
        // Force balance refresh when account changes
        if (account.isConnected && account.address) {
          console.log('🔄 Account connected, forcing balance refresh...')
          // Give it a moment then force balance refresh
          setTimeout(() => {
            // Trigger a balance refresh by emitting a custom event
            window.dispatchEvent(new CustomEvent('forceBalanceRefresh', {
              detail: { address: account.address, chainId: account.chainId }
            }))
          }, 1000)
        }
        
        // If AppKit thinks we're disconnected but Wagmi shows connected, log warning
        const wagmiStore = localStorage.getItem('wagmi.store')
        if (wagmiStore && !account.isConnected && window.ethereum?.selectedAddress) {
          console.warn('⚠️ State mismatch detected: Wagmi connected but AppKit disconnected')
          console.warn('Wagmi storage:', wagmiStore)
          console.warn('Selected address:', window.ethereum?.selectedAddress)
        }
      })
      
      appKit.subscribeNetwork((network) => {
        console.log('🔍 AppKit Network State:', network)
      })
      
      // Force sync AppKit with any existing Wagmi connection
      setTimeout(() => {
        try {
          // Check if wagmiAdapter and wagmiConfig exist
          if (!wagmiAdapter?.wagmiConfig) {
            console.log('⚠️ WagmiConfig not yet available, skipping sync')
            return
          }
          
          // Use the getAccount function if available, otherwise check state
          let account = {}
          if (typeof wagmiAdapter.wagmiConfig.getAccount === 'function') {
            account = wagmiAdapter.wagmiConfig.getAccount()
          } else if (wagmiAdapter.wagmiConfig.state?.current) {
            account = wagmiAdapter.wagmiConfig.state.current
          }
          
          console.log('🔄 Checking for existing Wagmi connection:', account)
          
          if (account?.isConnected && account?.address) {
            console.log('🔧 Wagmi is connected, forcing AppKit to recognize this connection')
            
            // Force trigger AppKit's internal state update by simulating adapter events
            const connectEvent = new CustomEvent('wagmi:accountChanged', {
              detail: {
                account: account.address,
                chainId: account.chainId,
                isConnected: true
              }
            })
            
            // Dispatch to both window and appKit if it has event handling
            window.dispatchEvent(connectEvent)
            
            // Also try to trigger AppKit's account subscription manually
            setTimeout(() => {
              console.log('🔄 Second sync attempt - checking AppKit state...')
              if (window.__appKitAccountState) {
                console.log('Current AppKit state:', window.__appKitAccountState)
                if (!window.__appKitAccountState.isConnected) {
                  console.warn('❌ AppKit still not synced after manual trigger')
                } else {
                  console.log('✅ AppKit is now synced!')
                }
              }
            }, 2000)
          } else {
            console.log('ℹ️ No existing Wagmi connection to sync')
          }
        } catch (error) {
          console.error('❌ Error during AppKit sync:', error)
        }
      }, 1000) // Give time for initial setup
    }
    
    console.log('✅ Reown AppKit plugin initialized successfully')
  } catch (error) {
    console.error('❌ Failed to initialize Reown AppKit plugin:', error)
    console.error('Error details:', error)
  }
})