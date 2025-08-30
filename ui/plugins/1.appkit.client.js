import { createAppKit } from '@reown/appkit/vue'
import { mainnet, arbitrum, sepolia, base, optimism, polygon } from '@reown/appkit/networks'
import { defineNuxtPlugin, useRuntimeConfig } from 'nuxt/app'

export default defineNuxtPlugin((nuxtApp) => {
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
    
    // Get the wagmi adapter from the wagmi plugin that already initialized
    // This prevents conflicts between multiple WagmiAdapter instances
    const wagmiAdapter = nuxtApp.$wagmiAdapter
    
    if (!wagmiAdapter) {
      console.error('❌ Wagmi adapter not found - make sure wagmi plugin loads first')
      return
    }
    
    console.log('✅ Using shared wagmi adapter for AppKit')
    
    // Create AppKit with complete configuration
    const appKit = createAppKit({
      adapters: [wagmiAdapter],
      networks,
      projectId,
      metadata,
      themeMode: 'dark',
      features: {
        // Disable features we don't need
        history: true,
        analytics: true,
        allWallets: false, // Only show featured wallets
        socials: []
      },
      // Define which wallets to include/exclude
      includeWalletIds: [
        'c57ca95b47569778a828d19178114f4db188b89b763c899ba0be274e97267d96', // MetaMask
        '4622a2b2d6af1c9844944291e5e7351a6aa24cd7b23099efac1b2fd875da31a0', // Trust Wallet
        'fd20dc426fb37566d803205b19bbc1d4096b248ac04548e3cfb6b3a38bd033aa', // Coinbase Wallet
        '225affb176778569276e484e1b92637ad061b01e13a048b35a9d280c3b58970f', // Safe Wallet
        '38f5d18bd8522c244bdd70cb4a68e0e718865155811c043f052fb9f1c51de662'  // Bitget Wallet
      ],
      // Exclude wallets that might cause confusion
      excludeWalletIds: [
        // Phantom wallet ID - for Solana wallets that shouldn't appear in Ethereum-only interface
        'a797aa35c0fadbfc1a53e7f675162ed5226968b44a19ee3d24385c64d1d3c393'
      ]
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