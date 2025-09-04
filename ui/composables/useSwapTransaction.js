import { ref } from 'vue'
import { useRuntimeConfig, navigateTo } from '#app'
import { useBlockchainTransaction } from './useBlockchainTransaction'
import { useCirxUtils } from './useCirxUtils'
import { useApiClient } from './core/useApiClient'
import { useTransactionStatus } from './features/useTransactionData'
import { safeToast } from './useToast'

/**
 * Unified swap transaction handler that consolidates all transaction logic
 * This replaces the duplicate implementations in:
 * - index.vue (lines 1931-1991)
 * - SwapForm.vue (lines 540-580)
 * - useSwapHandler.js (multiple transaction methods)
 */
export function useSwapTransaction() {
  const config = useRuntimeConfig()
  const blockchainTx = useBlockchainTransaction()
  const { createSwapTransaction } = useCirxUtils()
  const apiClient = useApiClient()
  const { trackTransaction } = useTransactionStatus()
  // safeToast is imported directly
  
  // Transaction state
  const isProcessing = ref(false)
  const loadingText = ref('')
  const currentTransaction = ref(null)
  const lastError = ref(null)
  
  // Backend health checks removed per user request
  
  /**
   * Execute a complete swap transaction (blockchain + backend)
   * This is the single source of truth for all swap transactions
   * 
   * @param {Object} params - Transaction parameters
   * @param {string} params.tokenSymbol - Token to send (ETH, USDC, USDT)
   * @param {string} params.amount - Amount to send
   * @param {string} params.recipientAddress - CIRX recipient address
   * @param {string} params.cirxAmount - Expected CIRX amount
   * @param {boolean} params.isOTC - Whether this is an OTC transaction
   * @param {Object} params.callbacks - Optional callbacks
   * @returns {Object} Transaction result
   */
  const executeSwap = async ({
    tokenSymbol,
    amount,
    recipientAddress,
    cirxAmount,
    isOTC = false,
    callbacks = {}
  }) => {
    isProcessing.value = true
    lastError.value = null
    currentTransaction.value = null
    
    try {
      // Execute blockchain transaction directly (health checks removed)
      loadingText.value = `Sending ${amount} ${tokenSymbol}...`
      callbacks.onStatusUpdate?.('executing_blockchain')
      
      console.log('Executing swap transaction:', {
        tokenSymbol,
        amount,
        recipientAddress
      })
      
      // Use the unified blockchain transaction handler
      const txResult = await blockchainTx.executeCompleteTransaction(
        tokenSymbol,
        amount,
        recipientAddress
      )
      
      if (!txResult.success) {
        throw new Error(txResult.error || 'Transaction failed')
      }
      
      // Track transaction status
      if (txResult.swapId) {
        callbacks.onStatusUpdate?.('tracking_status')
        
        // Start real-time tracking
        currentTransaction.value = trackTransaction(txResult.swapId, {
          showToasts: true,
          onStatusChange: (statusData, previousPhase) => {
            console.log(`Transaction ${txResult.swapId} moved from ${previousPhase} to ${statusData.phase}`)
            callbacks.onStatusChange?.(statusData, previousPhase)
          },
          onComplete: (statusData) => {
            callbacks.onComplete?.(statusData)
            safeToast.success('CIRX tokens successfully transferred!', {
              title: 'Swap Complete',
              autoTimeoutMs: 10000
            })
          },
          onError: (error) => {
            console.error('Transaction tracking error:', error)
            callbacks.onError?.(error)
          }
        })
      }
      
      // Store swap ID for later reference
      if (txResult.swapId) {
        localStorage.setItem('lastSwapId', txResult.swapId)
      }
      
      isProcessing.value = false
      loadingText.value = ''
      
      return {
        success: true,
        txHash: txResult.txHash,
        swapId: txResult.swapId,
        message: `Successfully initiated swap of ${amount} ${tokenSymbol} for ${cirxAmount} CIRX`
      }
      
    } catch (error) {
      console.error('Swap transaction failed:', error)
      lastError.value = error.message
      
      // Show user-friendly error message
      const errorMessage = error.message || 'Transaction failed'
      safeToast.error(errorMessage, {
        title: 'Transaction Failed',
        autoTimeoutMs: 8000
      })
      
      callbacks.onError?.(error)
      
      return {
        success: false,
        error: errorMessage
      }
      
    } finally {
      isProcessing.value = false
      loadingText.value = ''
    }
  }
  
  /**
   * Navigate to transaction status page
   */
  const navigateToStatus = async (swapId) => {
    if (swapId) {
      await navigateTo(`/transactions?swapId=${swapId}`)
    } else {
      await navigateTo('/transactions')
    }
  }
  
  /**
   * Cancel current transaction tracking
   */
  const cancelTracking = () => {
    if (currentTransaction.value?.unsubscribe) {
      currentTransaction.value.unsubscribe()
      currentTransaction.value = null
    }
  }
  
  return {
    // State
    isProcessing,
    loadingText,
    currentTransaction,
    lastError,
    
    // Methods
    executeSwap,
    navigateToStatus,
    cancelTracking
  }
}