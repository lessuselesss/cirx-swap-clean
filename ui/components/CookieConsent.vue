<template>
  <div
    v-if="showConsentModal"
    class="fixed inset-0 bg-black/80 backdrop-blur-sm flex items-center justify-center z-[10000] p-4"
    style="min-height: 100vh; min-height: 100dvh;"
  >
    <div class="bg-gradient-to-br from-circular-bg-secondary to-circular-bg-secondary/95 border border-gray-700 rounded-2xl p-6 sm:p-8 w-full max-w-4xl max-h-[90vh] overflow-y-auto shadow-2xl backdrop-blur-sm">
      <!-- Header -->
      <header class="text-center mb-8">
        <h1 class="font-michroma text-2xl sm:text-3xl text-circular-primary mb-2">
          Circular CIRX
        </h1>
        <p class="text-sm text-gray-400">OTC Trading Platform</p>
      </header>

      <!-- Hero Content -->
      <div class="text-center mb-8">
        <h2 class="text-3xl md:text-4xl font-bold mb-4 text-white font-michroma">
          CIRX OTC Trading
        </h2>
        <p class="text-lg md:text-xl mb-3 text-circular-primary">
          Buy CIRX tokens with instant delivery or OTC discounts up to 12%
        </p>
        <p class="text-sm mb-6 max-w-2xl mx-auto text-gray-400">
          Professional over-the-counter trading platform with 6-month linear vesting for discounted purchases.
        </p>
      </div>

      <!-- Feature Cards -->
      <div class="grid md:grid-cols-3 gap-4 mb-8">
        <div class="bg-gray-800/50 border border-gray-600 rounded-xl p-4 text-center">
          <div class="w-10 h-10 bg-circular-primary/20 rounded-full flex items-center justify-center mx-auto mb-3">
            <span class="text-circular-primary text-lg">⚡</span>
          </div>
          <h3 class="text-sm font-semibold mb-2 text-white font-michroma">Instant Delivery</h3>
          <p class="text-xs text-gray-400">Buy CIRX tokens with immediate delivery at market rates.</p>
        </div>

        <div class="bg-gray-800/50 border border-gray-600 rounded-xl p-4 text-center">
          <div class="w-10 h-10 bg-purple-500/20 rounded-full flex items-center justify-center mx-auto mb-3">
            <span class="text-purple-400 text-lg">%</span>
          </div>
          <h3 class="text-sm font-semibold mb-2 text-white font-michroma">OTC Discounts</h3>
          <p class="text-xs text-gray-400">Get 5-12% discounts with 6-month linear vesting.</p>
        </div>

        <div class="bg-gray-800/50 border border-gray-600 rounded-xl p-4 text-center">
          <div class="w-10 h-10 bg-green-500/20 rounded-full flex items-center justify-center mx-auto mb-3">
            <span class="text-green-400 text-lg">✓</span>
          </div>
          <h3 class="text-sm font-semibold mb-2 text-white font-michroma">Secure & Efficient</h3>
          <p class="text-xs text-gray-400">Built with advanced DeFi infrastructure for optimal security.</p>
        </div>
      </div>

      <!-- OTC Discount Tiers -->
      <div class="bg-purple-500/10 border border-purple-500/30 rounded-xl p-6 mb-8">
        <h3 class="text-lg font-bold text-center mb-4 text-purple-400 font-michroma">
          OTC Discount Tiers
        </h3>
        <div class="grid grid-cols-3 gap-4 text-center">
          <div>
            <div class="text-2xl font-bold mb-1 text-purple-400">5%</div>
            <div class="text-xs font-medium mb-1 text-white">$1K - $10K</div>
            <div class="text-xs text-gray-400">Entry level</div>
          </div>
          <div>
            <div class="text-2xl font-bold mb-1 text-purple-400">8%</div>
            <div class="text-xs font-medium mb-1 text-white">$10K - $50K</div>
            <div class="text-xs text-gray-400">Preferred</div>
          </div>
          <div>
            <div class="text-2xl font-bold mb-1 text-purple-400">12%</div>
            <div class="text-xs font-medium mb-1 text-white">$50K+</div>
            <div class="text-xs text-gray-400">Maximum</div>
          </div>
        </div>
      </div>

      <!-- Cookie Consent Section -->
      <div class="bg-yellow-500/10 border border-yellow-500/30 rounded-xl p-6 mb-6">
        <div class="flex items-start gap-3">
          <div class="w-6 h-6 bg-yellow-500/20 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
            <svg width="12" height="12" fill="currentColor" viewBox="0 0 24 24" class="text-yellow-400">
              <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
            </svg>
          </div>
          <div class="flex-1">
            <h4 class="text-sm font-semibold text-yellow-400 mb-2">Cookie Consent Required</h4>
            <p class="text-xs text-gray-300 mb-4 leading-relaxed">
              We use essential cookies to ensure our platform works correctly and analytics cookies to understand how you use our service. 
              By continuing, you agree to our use of cookies for essential functionality and analytics.
            </p>
            <div class="text-xs text-gray-400">
              <strong>Essential cookies:</strong> Session management, security, wallet connections<br>
              <strong>Analytics cookies:</strong> Usage statistics, performance monitoring (anonymous)
            </div>
          </div>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex flex-col sm:flex-row gap-3 justify-center">
        <button
          @click="acceptCookies"
          class="px-6 py-3 bg-circular-primary text-gray-900 rounded-xl font-semibold hover:bg-circular-primary-hover transition-colors"
        >
          Accept & Launch Platform
        </button>
        <button
          @click="acceptEssentialOnly"
          class="px-6 py-3 border border-gray-600 text-white rounded-xl font-medium hover:bg-gray-800/50 hover:border-gray-500 transition-colors"
        >
          Essential Cookies Only
        </button>
      </div>

      <!-- Privacy Links -->
      <div class="text-center mt-6 text-xs text-gray-400">
        <a href="https://circularlabs.io/privacy" target="_blank" class="hover:text-white transition-colors">
          Privacy Policy
        </a>
        <span class="mx-2">•</span>
        <a href="https://circularlabs.io/cookies" target="_blank" class="hover:text-white transition-colors">
          Cookie Policy
        </a>
      </div>
    </div>
  </div>
</template>

<script setup>
// Props and emits
defineEmits(['accepted'])

// Reactive state
const showConsentModal = ref(true)

// Cookie management
const setCookieConsent = (level) => {
  if (typeof window === 'undefined') return
  
  // Set cookie consent in localStorage and as a cookie
  const consentData = {
    timestamp: Date.now(),
    level: level, // 'all' or 'essential'
    version: '1.0'
  }
  
  try {
    // Store in localStorage
    localStorage.setItem('circular-cookie-consent', JSON.stringify(consentData))
    
    // Set cookie with 1 year expiration
    const expires = new Date()
    expires.setFullYear(expires.getFullYear() + 1)
    document.cookie = `circular-consent=${level}; expires=${expires.toUTCString()}; path=/; SameSite=Strict`
    
    // Set analytics flag
    if (level === 'all') {
      document.cookie = `circular-analytics=true; expires=${expires.toUTCString()}; path=/; SameSite=Strict`
    }
  } catch (error) {
    console.warn('Error setting cookie consent:', error)
  }
}

// Accept all cookies
const acceptCookies = () => {
  setCookieConsent('all')
  showConsentModal.value = false
  
  // Navigate to swap page
  navigateTo('/swap')
}

// Accept essential cookies only
const acceptEssentialOnly = () => {
  setCookieConsent('essential')
  showConsentModal.value = false
  
  // Navigate to swap page
  navigateTo('/swap')
}

// Check if consent already given (for when component mounts)
const checkExistingConsent = () => {
  if (typeof window === 'undefined') return false
  
  try {
    const consent = localStorage.getItem('circular-cookie-consent')
    if (consent) {
      const consentData = JSON.parse(consent)
      // Check if consent is less than 1 year old
      const oneYear = 365 * 24 * 60 * 60 * 1000
      if (Date.now() - consentData.timestamp < oneYear) {
        showConsentModal.value = false
        return true
      }
    }
  } catch (error) {
    console.warn('Error checking existing consent:', error)
  }
  
  return false
}

// Check consent on mount
onMounted(() => {
  const hasConsent = checkExistingConsent()
  if (hasConsent) {
    // User already has consent, redirect to swap page
    navigateTo('/swap')
  }
})
</script>