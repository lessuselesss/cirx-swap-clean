import { createAppKit } from '@reown/appkit/vue'
import { defineNuxtPlugin, useRuntimeConfig } from 'nuxt/app'
import { WagmiAdapter } from '@reown/appkit-adapter-wagmi'
import { mainnet, arbitrum, sepolia, base, optimism, polygon } from '@reown/appkit/networks'

export default defineNuxtPlugin(() => {
  // Get project ID from runtime config
  const config = useRuntimeConfig()
  const projectId = config.public.reownProjectId || config.public.walletConnectProjectId
  
  // Validate project ID
  if (!projectId) {
    console.error('❌ AppKit Project ID is missing!')
    console.error('Please set NUXT_PUBLIC_REOWN_PROJECT_ID in your .env file')
    return
  }

  try {
    console.log('⚙️ Initializing Reown AppKit...')
    console.log('Project ID:', projectId.slice(0, 8) + '...')
    
    // Environment-driven network configuration
    const isDevelopment = config.public.testnetMode || false
    
    // EVM networks - prioritize based on environment
    const networks = isDevelopment 
      ? [sepolia, mainnet, polygon, arbitrum, optimism, base]  // Testnet first in development
      : [mainnet, polygon, arbitrum, optimism, base, sepolia] // Mainnet first in production
    
    console.log('Networks:', networks.length, isDevelopment ? '(testnet mode)' : '(mainnet mode)')
    
    // Application metadata
    const metadata = {
      name: 'Circular CIRX OTC Platform',
      description: 'Professional OTC trading platform for CIRX tokens with instant delivery and discounted vesting options.',
      url: typeof window !== 'undefined' ? window.location.origin : 'https://circular.io',
      icons: ['https://circular.io/circular-logo.svg']
    }
    
    // Create wagmi adapter - note: this will be the AppKit's adapter instance
    const wagmiAdapter = new WagmiAdapter({
      networks,
      projectId,
      ssr: false
    })
    
    // Create AppKit with complete configuration
    const appKit = createAppKit({
      adapters: [wagmiAdapter],
      networks,
      projectId,
      metadata,
      themeMode: 'dark'
    })
    
    // Make AppKit available globally for debugging
    if (typeof window !== 'undefined') {
      window.$appKit = appKit
    }
    
    console.log('✅ Reown AppKit plugin initialized successfully')
  } catch (error) {
    console.error('❌ Failed to initialize Reown AppKit plugin:', error)
    console.error('Error details:', error)
  }
})