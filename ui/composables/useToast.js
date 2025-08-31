/**
 * Safe toast notification utility
 * Provides defensive toast functionality that works even if ToastNotifications isn't mounted yet
 */

// Safe toast functions that check for availability
export const safeToast = {
    success: (message, options = {}) => {
      if (typeof window !== 'undefined' && window.$toast) {
        window.$toast.success(message, options)
      } else {
        console.log('✅ SUCCESS:', message)
      }
    },
  
    error: (message, options = {}) => {
      if (typeof window !== 'undefined' && window.$toast) {
        window.$toast.error(message, options)
      } else {
        console.error('❌ ERROR:', message)
      }
    },
  
    warning: (message, options = {}) => {
      if (typeof window !== 'undefined' && window.$toast) {
        window.$toast.warning(message, options)
      } else {
        console.warn('⚠️ WARNING:', message)
      }
    },
  
    info: (message, options = {}) => {
      if (typeof window !== 'undefined' && window.$toast) {
        window.$toast.info(message, options)
      } else {
        console.info('ℹ️ INFO:', message)
      }
    }
  }
  
  // Global fallback for backward compatibility
  if (typeof window !== 'undefined') {
    // Create a safe global toast function that won't throw errors
    window.toast = safeToast.error // Default to error for backward compatibility
    
    // Override with proper toast when available
    const checkForToast = () => {
      if (window.$toast) {
        console.log('✅ Toast system initialized')
        clearInterval(toastCheckInterval)
      }
    }
    
    const toastCheckInterval = setInterval(checkForToast, 100)
    
    // Stop checking after 10 seconds
    setTimeout(() => {
      clearInterval(toastCheckInterval)
    }, 10000)
  }