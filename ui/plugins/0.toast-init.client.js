/**
 * Toast initialization plugin
 * Loads safe toast functionality immediately to prevent "toast is not defined" errors
 */
import { safeToast } from '~/composables/useToast'

export default defineNuxtPlugin(() => {
  // Immediately make safe toast available globally
  if (typeof window !== 'undefined') {
    window.safeToast = safeToast
    
    // Create a defensive toast function for backward compatibility
    window.toast = safeToast.error
    
    console.log('🍞 Safe toast system initialized')
  }
})