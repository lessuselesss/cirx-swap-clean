import { createAppKit } from '@reown/appkit/vue'
import { defineNuxtPlugin, useRuntimeConfig } from 'nuxt/app'
import { VueQueryPlugin, QueryClient } from '@tanstack/vue-query'
import { WagmiPlugin } from '@wagmi/vue'
import { WagmiAdapter } from '@reown/appkit-adapter-wagmi'
import { mainnet, sepolia } from '@reown/appkit/networks'

export default defineNuxtPlugin((nuxtApp) => {
  // Get runtime config for environment variables
  const config = useRuntimeConfig()
  const projectId = config.public.reownProjectId
  
  // Validate project ID
  if (!projectId || projectId === 'your_reown_project_id') {
    console.error('❌ AppKit Project ID is missing!')
    console.error('Please set NUXT_PUBLIC_REOWN_PROJECT_ID in your .env file')
    return
  }

  try {
    console.log('⚙️ Initializing Wagmi + AppKit...')
    console.log('Project ID:', projectId.slice(0, 8) + '...')
    
    // Environment-driven network configuration
    const isDevelopment = config.public.testnetMode !== false
    const networks = isDevelopment 
      ? [sepolia, mainnet]
      : [mainnet, sepolia]

    console.log('Networks:', networks.map(n => n.name), isDevelopment ? '(testnet mode)' : '(mainnet mode)')

    // Create wagmi adapter
    const wagmiAdapter = new WagmiAdapter({
      networks,
      projectId,
      ssr: false,
      metadata: {
        name: 'CIRX OTC Platform',
        description: 'Trade CIRX tokens',
        url: typeof window !== 'undefined' ? window.location.origin : 'https://circularprotocol.io/buy',
        icons: ['https://avatars.githubusercontent.com/u/179229932?s=200&v=4']
      }
    })

    // Create a QueryClient instance for wagmi
    const queryClient = new QueryClient({
      defaultOptions: {
        queries: {
          gcTime: 1_000 * 60 * 60 * 24, // 24 hours
          networkMode: 'offlineFirst',
          refetchOnWindowFocus: false,
          retry: 0,
        },
        mutations: {
          networkMode: 'offlineFirst',
        },
      },
    })
    
    // Install VueQuery plugin first
    nuxtApp.vueApp.use(VueQueryPlugin, {
      queryClient,
      enableDevtoolsV6Plugin: true
    })

    // Install Wagmi plugin with the adapter's config
    nuxtApp.vueApp.use(WagmiPlugin, {
      config: wagmiAdapter.wagmiConfig
    })
    
    console.log('✅ Wagmi + VueQuery plugins initialized')
    
    // Application metadata
    const metadata = {
      name: 'Circular CIRX OTC Platform',
      description: 'Professional OTC trading platform for CIRX tokens with instant delivery and discounted vesting options.',
      url: typeof window !== 'undefined' ? window.location.origin : 'https://circularprotocol.io/buy',
      icons: ['https://avatars.githubusercontent.com/u/179229932?s=200&v=4']
    }
    
    // Create AppKit with the wagmi adapter
    const appKit = createAppKit({
      adapters: [wagmiAdapter],
      networks,
      projectId,
      metadata,
      themeMode: 'dark',
      features: {
        analytics: false,
        email: false,
        socials: []
      }
    })
    
    // Make AppKit available globally for debugging
    if (typeof window !== 'undefined') {
      window.$appKit = appKit
      
      // Add event listeners for debugging connection issues
      appKit.subscribeState((state) => {
        console.log('🔄 AppKit state change:', {
          open: state.open,
          selectedNetworkId: state.selectedNetworkId,
          loading: state.loading
        })
      })
      
      // Simple debugging without aggressive cleanup
      setTimeout(() => {
        console.log('🔍 AppKit debugging:')
        console.log('- window.ethereum present:', !!window.ethereum)
        console.log('- MetaMask detected:', !!window.ethereum?.isMetaMask)
        console.log('- Current account:', appKit.getAccount?.()?.address || 'None')
      }, 1000)
    }
    
    console.log('✅ Reown AppKit plugin initialized successfully')
    
    return {
      provide: {
        appkit: appKit
      }
    }
  } catch (error) {
    console.error('❌ Failed to initialize Reown AppKit plugin:', error)
    console.error('Error details:', error)
  }
})