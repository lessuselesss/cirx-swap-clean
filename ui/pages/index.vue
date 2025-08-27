<template>
  <div class="min-h-screen bg-circular-bg-primary">
    <div :class="['transition-all duration-300', showCookieConsent ? 'blur-sm pointer-events-none' : '']">
      <header class="bg-transparent backdrop-blur-sm border-b border-gray-800/30 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div class="flex justify-between items-center h-16">
            <div class="flex items-center gap-2 sm:gap-4">
              <img 
                src="/circular-logo.svg" 
                alt="Circular Protocol" 
                class="h-8 w-auto"
              />
              <span class="text-xs sm:text-sm hidden sm:block text-gray-400">Swap</span>
            </div>
            <div class="flex items-center gap-2 sm:gap-4">
              <NuxtLink 
                to="/transactions" 
                class="px-3 py-2 text-gray-400 hover:text-white transition-colors text-sm font-medium"
              >
                Transactions
              </NuxtLink>
                <WalletButton />
            </div>
          </div>
        </div>
      </header>

      <div class="flex items-center justify-center min-h-[calc(100vh-4rem)] p-8">
        <div class="text-center max-w-2xl">
          <div class="w-16 h-16 bg-circular-primary/20 rounded-2xl flex items-center justify-center mx-auto mb-6">
            <span class="text-circular-primary text-2xl font-bold">C</span>
          </div>
          <h2 class="text-3xl font-bold text-white mb-4 font-michroma">
            Welcome to Circular CIRX
          </h2>
          <p class="text-gray-400 mb-8">
            Professional OTC trading platform for CIRX tokens. Please accept our cookie policy to continue.
          </p>
          <div class="flex justify-center">
            <div class="animate-pulse w-8 h-8 bg-circular-primary/30 rounded-full"></div>
          </div>
        </div>
      </div>
    </div>

    <CookieConsent v-if="showCookieConsent" @accepted="handleConsentAccepted" />
  </div>
</template>

<script setup>
// Page metadata
definePageMeta({
  title: 'Circular CIRX OTC Trading Platform',
  description: 'Buy CIRX tokens with instant delivery or OTC discounts up to 12%. Professional OTC trading platform for the Circular Protocol ecosystem.',
  ssr: false // Disable SSR for this page due to client-only dependencies
})

// Reactive state for cookie consent
const showCookieConsent = ref(true)

// Check if user has already given consent
const checkCookieConsent = () => {
  if (typeof window === 'undefined') {
    // Server-side rendering, keep modal visible
    return false
  }
  
  try {
    // Check localStorage for consent
    const consent = localStorage.getItem('circular-cookie-consent')
    if (consent) {
      const consentData = JSON.parse(consent)
      // Check if consent is less than 1 year old
      const oneYear = 365 * 24 * 60 * 60 * 1000
      if (Date.now() - consentData.timestamp < oneYear) {
        showCookieConsent.value = false
        // Redirect to swap page if consent already given
        setTimeout(() => {
          navigateTo('/swap')
        }, 100)
        return true
      }
    }
    
    // Also check cookie as fallback
    const cookieConsent = document.cookie
      .split('; ')
      .find(row => row.startsWith('circular-consent='))
    
    if (cookieConsent) {
      const level = cookieConsent.split('=')[1]
      if (level === 'all' || level === 'essential') {
        showCookieConsent.value = false
        setTimeout(() => {
          navigateTo('/swap')
        }, 100)
        return true
      }
    }
  } catch (error) {
    console.warn('Error checking cookie consent:', error)
  }
  
  return false
}

// Handle consent acceptance
const handleConsentAccepted = () => {
  showCookieConsent.value = false
  navigateTo('/swap')
}

// Check consent on mount
onMounted(() => {
  checkCookieConsent()
})

// Head configuration
useHead({
  title: 'Circular CIRX OTC Platform - Professional Token Trading',
  meta: [
    { 
      name: 'description', 
      content: 'Professional CIRX OTC trading platform with instant delivery and discounted vesting options.' 
    },
    { 
      name: 'keywords', 
      content: 'CIRX, OTC trading, crypto, tokens, vesting, discounts, DeFi' 
    }
  ]
})
</script>

<style scoped>
@keyframes fade-in {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.animate-fade-in {
  animation: fade-in 1s ease-out;
}
</style>