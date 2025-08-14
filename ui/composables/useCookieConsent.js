export const useCookieConsent = () => {
  // Check if user has given cookie consent
  const hasConsent = () => {
    if (!process.client) return false
    
    // Check localStorage first
    const consent = localStorage.getItem('circular-cookie-consent')
    if (consent) {
      try {
        const consentData = JSON.parse(consent)
        // Check if consent is less than 1 year old
        const oneYear = 365 * 24 * 60 * 60 * 1000
        if (Date.now() - consentData.timestamp < oneYear) {
          return consentData.level
        }
      } catch (e) {
        // Invalid consent data
      }
    }
    
    // Check cookie as fallback
    const cookieConsent = document.cookie
      .split('; ')
      .find(row => row.startsWith('circular-consent='))
    
    if (cookieConsent) {
      return cookieConsent.split('=')[1]
    }
    
    return false
  }

  // Check if analytics are enabled
  const hasAnalyticsConsent = () => {
    const consentLevel = hasConsent()
    return consentLevel === 'all'
  }

  // Set cookie consent
  const setConsent = (level) => {
    if (!process.client) return
    
    const consentData = {
      timestamp: Date.now(),
      level: level, // 'all' or 'essential'
      version: '1.0'
    }
    
    // Store in localStorage
    localStorage.setItem('circular-cookie-consent', JSON.stringify(consentData))
    
    // Set cookie with 1 year expiration
    const expires = new Date()
    expires.setFullYear(expires.getFullYear() + 1)
    document.cookie = `circular-consent=${level}; expires=${expires.toUTCString()}; path=/; SameSite=Strict`
    
    // Set analytics flag
    if (level === 'all') {
      document.cookie = `circular-analytics=true; expires=${expires.toUTCString()}; path=/; SameSite=Strict`
    } else {
      // Remove analytics cookie if only essential
      document.cookie = `circular-analytics=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;`
    }
  }

  // Clear all consent
  const clearConsent = () => {
    if (!process.client) return
    
    localStorage.removeItem('circular-cookie-consent')
    document.cookie = `circular-consent=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;`
    document.cookie = `circular-analytics=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;`
  }

  // Get consent level details
  const getConsentDetails = () => {
    const level = hasConsent()
    if (!level) return null
    
    const consent = localStorage.getItem('circular-cookie-consent')
    if (consent) {
      try {
        return JSON.parse(consent)
      } catch (e) {
        return { level, timestamp: Date.now(), version: '1.0' }
      }
    }
    
    return { level, timestamp: Date.now(), version: '1.0' }
  }

  return {
    hasConsent,
    hasAnalyticsConsent,
    setConsent,
    clearConsent,
    getConsentDetails
  }
}