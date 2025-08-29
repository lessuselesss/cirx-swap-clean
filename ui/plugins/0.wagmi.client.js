import { defineNuxtPlugin, useRuntimeConfig } from 'nuxt/app'
import { VueQueryPlugin, QueryClient } from '@tanstack/vue-query'
import { WagmiPlugin } from '@wagmi/vue'
import { WagmiAdapter } from '@reown/appkit-adapter-wagmi'
import { mainnet, arbitrum, sepolia, base, optimism, polygon } from '@reown/appkit/networks'

export default defineNuxtPlugin((nuxtApp) => {
  // Get runtime config for environment variables
  const config = useRuntimeConfig()
  const projectId = config.public.reownProjectId || config.public.walletConnectProjectId
  
  if (!projectId) {
    console.warn('⚠️ Wagmi plugin: Project ID missing - some features may not work')
    return
  }

  // Environment-driven network configuration
  const isDevelopment = config.public.testnetMode || false
  const networks = isDevelopment 
    ? [sepolia, mainnet, polygon, arbitrum, optimism, base]
    : [mainnet, polygon, arbitrum, optimism, base, sepolia]

  // Create wagmi adapter for AppKit
  const wagmiAdapter = new WagmiAdapter({
    networks,
    projectId,
    ssr: false
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
})