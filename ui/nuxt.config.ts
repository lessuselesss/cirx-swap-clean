import { defineNuxtConfig } from 'nuxt/config'

// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },
  
  // Development server configuration
  devServer: {
    host: '127.0.0.1',
    port: 3000
  },
  
  // Configure for static generation
  nitro: {
    preset: 'static',
    // Disable app manifest to avoid 403 errors
    experimental: {
      appManifest: false
    }
  },
  
  // Disable app manifest
  experimental: {
    appManifest: false
  },
  
  // Disable SSR for Web3 compatibility
  ssr: false,

  modules: [
    '@nuxtjs/tailwindcss', 
    '@pinia/nuxt',
    '@wagmi/vue/nuxt',
    // '@nuxt/ui' // Temporarily disabled - causing build issues
    'nuxt-icon',
    'unplugin-icons/nuxt',
    'floating-vue/nuxt',
    'nuxt-tradingview'
  ],

  // Configure unplugin-icons
  icon: {
    // Enable auto-install of icon collections
    autoInstall: true
  },

  

  // CSS configuration
  css: ['~/assets/css/main.css'],

  // App configuration
  app: {
    baseURL: '/buy/',
    buildAssetsDir: '/_nuxt/',
    head: {
      title: 'Circular CIRX OTC Platform',
      meta: [
        { charset: 'utf-8' },
        { name: 'viewport', content: 'width=device-width, initial-scale=1' },
        { name: 'description', content: 'Circular CIRX OTC Trading Platform - Buy CIRX tokens with instant delivery or OTC discounts up to 12%' }
      ],
      link: [
        { rel: 'icon', type: 'image/x-icon', href: '/favicon.ico' }
      ]
    }
  },

  // Vue configuration for custom elements (AppKit web components)
  vue: {
    compilerOptions: {
      isCustomElement: (tag) => tag.startsWith('w3m-') || tag.startsWith('wui-') || tag.startsWith('appkit-')
    }
  },

  // Runtime configuration for environment variables
  runtimeConfig: {
    // Private keys (only available on server-side)
    // Public keys (exposed to client-side)
    public: {
      walletConnectProjectId: process.env.WALLETCONNECT_PROJECT_ID || '2585d3b6fd8a214ece0e26b344957169',
      reownProjectId: '2585d3b6fd8a214ece0e26b344957169',
      appName: 'Circular CIRX OTC Platform',
      appDescription: 'Circular CIRX OTC Trading Platform - Buy CIRX tokens with instant delivery or OTC discounts up to 12%',
      appUrl: process.env.APP_URL || 'https://circular.io',
      // Network configuration
      testnetMode: process.env.NUXT_PUBLIC_TESTNET_MODE === 'true',
      ethereumNetwork: process.env.NUXT_PUBLIC_ETHEREUM_NETWORK || 'mainnet',
      ethereumChainId: process.env.NUXT_PUBLIC_ETHEREUM_CHAIN_ID || '1',
      // Backend API configuration  
      apiBaseUrl: process.env.NUXT_PUBLIC_API_BASE_URL || 'https://circularprotocol.io/buy/api/v1',
      apiKey: process.env.NUXT_PUBLIC_API_KEY || '',
      // IROH networking configuration
      irohEnabled: process.env.NUXT_PUBLIC_IROH_ENABLED === 'true',
      irohBridgeUrl: process.env.NUXT_PUBLIC_IROH_BRIDGE_URL || 'http://localhost:9090',
      // Platform wallet addresses (CRITICAL: must match backend PLATFORM_FEE_WALLET)
      ethDepositAddress: process.env.NUXT_PUBLIC_ETH_DEPOSIT_ADDRESS || '0x834244d016f29d6acb42c1b054a88e2e9b1c9228',
      usdcDepositAddress: process.env.NUXT_PUBLIC_USDC_DEPOSIT_ADDRESS || '0x834244d016f29d6acb42c1b054a88e2e9b1c9228',
      usdtDepositAddress: process.env.NUXT_PUBLIC_USDT_DEPOSIT_ADDRESS || '0x834244d016f29d6acb42c1b054a88e2e9b1c9228',
      polygonDepositAddress: process.env.NUXT_PUBLIC_POLYGON_DEPOSIT_ADDRESS || '0x834244d016f29d6acb42c1b054a88e2e9b1c9228',
      bscDepositAddress: process.env.NUXT_PUBLIC_BSC_DEPOSIT_ADDRESS || '0x834244d016f29d6acb42c1b054a88e2e9b1c9228',
    }
  },

})
