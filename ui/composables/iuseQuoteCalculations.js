// Quote calculations async wrappers composable
import { ref } from 'vue'

export function iuseQuoteCalculations() {
  const quoteLoading = ref(false)
  const lastQuoteRequestId = ref(0)
  const reverseQuoteLoading = ref(false)
  const lastReverseQuoteRequestId = ref(0)
  
  // Force loading states to false periodically to unblock UI
  const forceUnblockLoadingStates = () => {
    setInterval(() => {
      quoteLoading.value = false
      reverseQuoteLoading.value = false
    }, 1000)
  }
  
  const calculateQuoteAsync = async (amount, token, isOTC, calculateQuoteFn) => {
    if (!amount || parseFloat(amount) <= 0) return null
    const requestId = ++lastQuoteRequestId.value
    quoteLoading.value = true
    try {
      await new Promise(resolve => setTimeout(resolve, 300))
      if (requestId !== lastQuoteRequestId.value) return null
      return await calculateQuoteFn(amount, token, isOTC)
    } finally {
      if (requestId === lastQuoteRequestId.value) {
        quoteLoading.value = false
      }
    }
  }
  
  const calculateReverseQuoteAsync = async (cirxAmt, token, isOTC, calculateReverseQuoteFn) => {
    if (!cirxAmt || parseFloat(cirxAmt) <= 0) return null
    const requestId = ++lastReverseQuoteRequestId.value
    reverseQuoteLoading.value = true
    try {
      await new Promise(resolve => setTimeout(resolve, 300))
      if (requestId !== lastReverseQuoteRequestId.value) return null
      return calculateReverseQuoteFn(cirxAmt, token, isOTC)
    } finally {
      if (requestId === lastReverseQuoteRequestId.value) {
        reverseQuoteLoading.value = false
      }
    }
  }
  
  return {
    quoteLoading,
    reverseQuoteLoading,
    forceUnblockLoadingStates,
    calculateQuoteAsync,
    calculateReverseQuoteAsync
  }
}