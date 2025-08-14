import { WagmiPlugin } from '@wagmi/vue'
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query'
import { defineNuxtPlugin } from 'nuxt/app'
import { wagmiConfig } from '~/config/appkit.js'

export default defineNuxtPlugin(nuxtApp => {
  // Set up Vue Query client
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: 2,
        refetchOnWindowFocus: false,
      },
    },
  })

  // Register Wagmi plugin
  nuxtApp.vueApp.use(WagmiPlugin, { 
    config: wagmiConfig 
  })

  // Register Vue Query plugin  
  nuxtApp.vueApp.use(VueQueryPlugin, { 
    queryClient 
  })
})