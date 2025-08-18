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
    preset: 'static'
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
    'floating-vue/nuxt'
  ],

  // Configure unplugin-icons
  icons: {
    // Enable auto-install of icon collections
    autoInstall: true
  },

  

  // CSS configuration
  css: ['~/assets/css/main.css'],

  // App configuration
  app: {
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
      // Backend API configuration
      apiBaseUrl: process.env.NUXT_PUBLIC_API_BASE_URL || 'http://localhost:8081/api',
      apiKey: process.env.NUXT_PUBLIC_API_KEY || '',
    }
  },

})
